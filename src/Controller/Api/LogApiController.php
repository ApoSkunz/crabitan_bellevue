<?php

declare(strict_types=1);

namespace Controller\Api;

use Core\Controller;
use Service\RateLimiterService;

/**
 * Endpoint API pour la journalisation des erreurs JavaScript côté client.
 *
 * Reçoit les erreurs capturées par window.onerror et window.onunhandledrejection,
 * les valide, les persiste dans storage/logs/js-error-YYYY-MM-DD.log et
 * purge les fichiers de log antérieurs à 30 jours.
 */
class LogApiController extends Controller
{
    private const MAX_ATTEMPTS    = 10;
    private const WINDOW_SECONDS  = 60;
    private const MAX_DAILY       = 500;   // VULN-003 : budget quotidien par IP
    private const WINDOW_DAILY    = 86400;
    private const RETENTION_DAYS  = 30;
    private const LOG_DIR_NAME    = 'storage/logs';

    private RateLimiterService $rateLimiter;

    /**
     * Initialise le contrôleur avec le rate limiter.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->rateLimiter = new RateLimiterService();
    }

    /**
     * Reçoit et enregistre une erreur JavaScript cliente.
     *
     * Payload JSON attendu : {message, source, lineno, colno, stack, url, userAgent}.
     * Répond 204 si succès, 400 si payload invalide, 429 si rate limit dépassé.
     *
     * @param array<string, string> $params Paramètres de route (inutilisés)
     * @return void
     */
    public function clientError(array $params): void // NOSONAR — $params requis par le router
    {
        // VULN-002 : valider Content-Type — rejeter tout ce qui n'est pas JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($contentType, 'application/json')) {
            $this->json(['error' => 'Unsupported Media Type'], 415);
        }

        $ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rlKey       = 'js-log:' . $ip;
        $rlDailyKey  = 'js-log-daily:' . $ip;

        // Rate limit par minute
        if (!$this->rateLimiter->checkLimit($rlKey, self::MAX_ATTEMPTS, self::WINDOW_SECONDS)) {
            $this->json(['error' => 'Too Many Requests'], 429);
        }
        // VULN-003 : budget quotidien par IP (500 entrées/24h)
        if (!$this->rateLimiter->checkLimit($rlDailyKey, self::MAX_DAILY, self::WINDOW_DAILY)) {
            $this->json(['error' => 'Daily quota exceeded'], 429);
        }

        $this->rateLimiter->recordAttempt($rlKey, self::WINDOW_SECONDS);
        $this->rateLimiter->recordAttempt($rlDailyKey, self::WINDOW_DAILY);

        $raw = $this->readInput();
        /** @var array<string, mixed>|null $body */
        $body = $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($body) || empty($body['message'])) {
            $this->json(['error' => 'Bad Request'], 400);
        }

        // VULN-003 : supprimer les caractères de contrôle de tous les champs texte libres
        $sanitize = static fn(string $v, int $max): string =>
            mb_substr(preg_replace('/[\x00-\x1F\x7F]/', '', $v) ?? $v, 0, $max);

        $entry = [
            'ts'      => date('c'),
            'message' => $sanitize((string) ($body['message'] ?? ''), 1000),
            'source'  => $sanitize((string) ($body['source']  ?? ''), 500),
            'lineno'  => (int) ($body['lineno'] ?? 0),
            'colno'   => (int) ($body['colno']  ?? 0),
            'stack'   => $sanitize((string) ($body['stack']   ?? ''), 5000),
            'url'     => $sanitize((string) ($body['url']     ?? ''), 500),
            'ua'      => $sanitize((string) ($body['userAgent'] ?? ''), 500),
        ];

        $this->writeLog($entry);

        // VULN-011 : limiter purge à 1×/heure via APCu pour éviter la saturation I/O
        $purgeKey = 'js_log_purge_last';
        $lastPurge = function_exists('apcu_fetch') ? (int) @apcu_fetch($purgeKey) : 0;
        if ((time() - $lastPurge) > 3600) {
            $this->purgeOldLogs();
            if (function_exists('apcu_store')) {
                apcu_store($purgeKey, time(), 3600);
            }
        }

        $this->json(['ok' => true], 204);
    }

    /**
     * Lit le corps brut de la requête HTTP.
     *
     * Méthode protégée pour permettre la surcharge dans les tests unitaires.
     *
     * @return string
     */
    protected function readInput(): string
    {
        return (string) file_get_contents('php://input');
    }

    /**
     * Écrit une entrée JSON dans le fichier de log du jour.
     *
     * @param array<string, mixed> $entry Données à journaliser
     * @return void
     */
    private function writeLog(array $entry): void
    {
        $logDir = defined('ROOT_PATH') ? ROOT_PATH . '/' . self::LOG_DIR_NAME : self::LOG_DIR_NAME;

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // VULN-003 : logs JS dans fichier dédié — séparés des logs PHP critiques
        $entry['channel'] = 'client_js';
        unset($entry['level']); // refuser les champs de niveau fournis par le client
        $filename = $logDir . '/js-' . date('Y-m-d') . '.log';
        $line     = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Supprime les fichiers de log js-error-*.log âgés de plus de 30 jours.
     *
     * @return void
     */
    private function purgeOldLogs(): void
    {
        $logDir = defined('ROOT_PATH') ? ROOT_PATH . '/' . self::LOG_DIR_NAME : self::LOG_DIR_NAME;

        if (!is_dir($logDir)) {
            return;
        }

        $cutoff = time() - (self::RETENTION_DAYS * 86400);

        foreach (glob($logDir . '/js-*.log') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}
