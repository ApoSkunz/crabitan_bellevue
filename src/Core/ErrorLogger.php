<?php

declare(strict_types=1);

namespace Core;

/**
 * Gestionnaire de logs d'erreurs PHP côté serveur.
 *
 * Capture toutes les erreurs PHP et exceptions non catchées,
 * les écrit au format JSON dans storage/logs/php-error-YYYY-MM-DD.log
 * avec une rétention de 30 jours.
 */
class ErrorLogger
{
    private const LOG_DIR_NAME    = 'storage/logs';
    private const RETENTION_DAYS  = 30;
    private const TRACE_MAX_LEN   = 500;

    /**
     * Enregistre le handler d'erreurs et d'exceptions PHP.
     *
     * @return void
     */
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    /**
     * Handler pour les erreurs PHP (E_ALL).
     *
     * @param int    $errno   Niveau d'erreur
     * @param string $errstr  Message d'erreur
     * @param string $errfile Fichier source
     * @param int    $errline Numéro de ligne
     * @return bool False pour déléguer au handler natif PHP
     */
    public static function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        $level = self::errorLevelName($errno);

        self::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => $level,
            'message'   => $errstr,
            'file'      => $errfile,
            'line'      => $errline,
            'trace'     => '',
            'url'       => $_SERVER['REQUEST_URI'] ?? '',
            'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
        ]);

        // false = le handler natif PHP continue à s'exécuter (display_errors en dev)
        return false;
    }

    /**
     * Handler pour les exceptions non catchées.
     *
     * @param \Throwable $e Exception ou Error
     * @return void
     */
    public static function handleException(\Throwable $e): void
    {
        $trace = substr($e->getTraceAsString(), 0, self::TRACE_MAX_LEN);

        self::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'EXCEPTION',
            'message'   => get_class($e) . ': ' . $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $trace,
            'url'       => $_SERVER['REQUEST_URI'] ?? '',
            'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
        ]);
    }

    /**
     * Journalise un événement opérationnel avec le niveau INFO.
     *
     * À utiliser pour les traces d'audit non critiques (ex: vérification d'âge,
     * actions utilisateur significatives) qui doivent apparaître dans le log
     * backend sans être traitées comme des erreurs.
     *
     * @param string $message Message descriptif de l'événement
     * @return void
     */
    public static function info(string $message): void
    {
        self::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'INFO',
            'message'   => $message,
            'file'      => '',
            'line'      => 0,
            'trace'     => '',
            'url'       => $_SERVER['REQUEST_URI'] ?? '',
            'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
        ]);
    }

    /**
     * Journalise une exception catchée et gérée avec le niveau WARNING.
     *
     * À utiliser dans les catch non bloquants (SMTP, services secondaires…)
     * où l'erreur ne doit pas interrompre le flux principal.
     *
     * @param \Throwable $e       Exception catchée
     * @param string     $context Préfixe de contexte (ex: '[IPN]', '[RefundService]')
     * @return void
     */
    public static function warning(\Throwable $e, string $context = ''): void
    {
        $prefix = $context !== '' ? $context . ' ' : '';

        self::write([
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => 'WARNING',
            'message'   => $prefix . get_class($e) . ': ' . $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => substr($e->getTraceAsString(), 0, self::TRACE_MAX_LEN),
            'url'       => $_SERVER['REQUEST_URI'] ?? '',
            'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
        ]);
    }

    /**
     * Écrit une entrée JSON dans le fichier de log du jour.
     *
     * @param array<string, mixed> $entry
     * @return void
     */
    public static function write(array $entry): void
    {
        $logDir = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/' . self::LOG_DIR_NAME;

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $filename = $logDir . '/php-error-' . date('Y-m-d') . '.log';
        $line     = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

        file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);

        self::purgeOldLogs($logDir);
    }

    /**
     * Supprime les fichiers php-error-*.log âgés de plus de 30 jours.
     *
     * @param string $logDir Chemin du répertoire de logs
     * @return void
     */
    private static function purgeOldLogs(string $logDir): void
    {
        $cutoff = time() - (self::RETENTION_DAYS * 86400);

        foreach (glob($logDir . '/php-error-*.log') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }

    /**
     * Retourne le nom lisible d'un niveau d'erreur PHP.
     *
     * @param int $errno Constante E_*
     * @return string
     */
    public static function errorLevelName(int $errno): string
    {
        return match ($errno) {
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
            default             => 'E_UNKNOWN',
        };
    }
}
