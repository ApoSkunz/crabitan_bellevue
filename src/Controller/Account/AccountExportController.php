<?php

declare(strict_types=1);

namespace Controller\Account;

use Core\Controller;
use Core\Response;
use Middleware\AuthMiddleware;
use Model\AccountModel;
use Model\RgpdExportLogModel;
use Repository\MysqlUserRepository;
use Repository\UserRepository;
use Service\RateLimiterService;

/**
 * Gère l'export RGPD des données personnelles (Art. 20) depuis l'espace client.
 */
class AccountExportController extends Controller
{
    /** Rate limiting export RGPD : 3 exports / 24 h / utilisateur. */
    private const MAX_EXPORT_USER = 3;
    private const WINDOW_EXPORT   = 86400;

    private UserRepository $userRepo;
    private RateLimiterService $rateLimiter;
    private RgpdExportLogModel $rgpdExportLogs;

    /**
     * Initialise le contrôleur et ses dépendances.
     *
     * @param \Core\Request       $request  Requête HTTP courante
     * @param UserRepository|null $userRepo Dépôt utilisateur (null → MysqlUserRepository par défaut)
     */
    public function __construct(\Core\Request $request, ?UserRepository $userRepo = null)
    {
        parent::__construct($request);
        $this->userRepo       = $userRepo ?? new MysqlUserRepository(new AccountModel());
        $this->rateLimiter    = new RateLimiterService();
        $this->rgpdExportLogs = new RgpdExportLogModel();
    }

    /**
     * Instancie le MailService (seam de testabilité).
     *
     * @return \Service\MailService
     */
    protected function newMailService(): \Service\MailService
    {
        return new \Service\MailService();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/export
    // ----------------------------------------------------------------

    /**
     * Affiche la page de demande d'export RGPD.
     *
     * @param array<string, mixed> $params Paramètres de route (lang)
     * @return void
     */
    public function exportPage(array $params): void
    {
        $payload = $this->requireCustomer();
        $lang    = $params['lang'];

        $sent  = $_SESSION['flash']['export_sent']  ?? null;
        $error = $_SESSION['flash']['export_error'] ?? null;
        unset($_SESSION['flash']['export_sent'], $_SESSION['flash']['export_error']);

        $userId  = (int) $payload['sub'];
        $account = $this->userRepo->findById($userId);

        $lastExportTs = $this->rateLimiter->recall('rgpd_export_ts:' . $userId);

        $this->view('account/export', [
            'lang'         => $lang,
            'account'      => $account,
            'maskedEmail'  => AccountProfileController::maskEmail((string) ($account['email'] ?? '')),
            'sent'         => $sent,
            'error'        => $error,
            'lastExportAt' => $lastExportTs !== null ? date('d/m/Y à H:i', (int) $lastExportTs) : null,
            'csrf'         => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/export/envoyer
    // ----------------------------------------------------------------

    /**
     * Génère l'archive RGPD et l'envoie par email au titulaire du compte.
     *
     * @param array<string, mixed> $params Paramètres de route (lang)
     * @return void
     */
    public function exportSend(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['export_error'] = __('error.csrf');
            Response::redirect("/{$lang}/mon-compte/export");
        }

        $rlKey = 'rgpd_export:' . $userId;
        if (!$this->rateLimiter->checkLimit($rlKey, self::MAX_EXPORT_USER, self::WINDOW_EXPORT)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($rlKey) / 3600);
            $_SESSION['flash']['export_error'] = sprintf(__('auth.too_many_attempts'), max(1, $wait) . 'h');
            Response::redirect("/{$lang}/mon-compte/export");
        }
        $this->rateLimiter->recordAttempt($rlKey, self::WINDOW_EXPORT);

        $account  = $this->userRepo->findById($userId);
        $email    = (string) ($account['email'] ?? '');
        $name     = (string) ($account['firstname'] ?? ($account['company_name'] ?? $email));
        $export   = $this->buildExportData($userId);
        $date     = date('Y-m-d');
        $basename = 'mes-donnees-' . $date;

        [$content, $isZip] = $this->buildExportContent($export, $date, $basename);
        $filename = $basename . ($isZip ? '.zip' : '.json');

        try {
            $this->newMailService()->sendRgpdExport($email, $name, $content, $filename, $lang);
            $this->rateLimiter->remember('rgpd_export_ts:' . $userId, time(), self::WINDOW_EXPORT);
            $this->rgpdExportLogs->log($email, \Service\AuditLogger::getIp(), $filename, $userId);
            $_SESSION['flash']['export_sent'] = AccountProfileController::maskEmail($email);
        } catch (\Exception $e) {
            \Core\ErrorLogger::warning($e);
            $_SESSION['flash']['export_error'] = __('account.export_error');
        }

        Response::redirect("/{$lang}/mon-compte/export");
    }

    /**
     * Assemble les données export RGPD pour un utilisateur.
     *
     * @param int $userId Identifiant du compte
     * @return array<string, mixed>
     */
    protected function buildExportData(int $userId): array
    {
        return (new \Service\RgpdExportService())->buildForAccount($userId);
    }

    /**
     * Construit le contenu binaire de l'export RGPD (ZIP JSON+PDF ou JSON fallback).
     *
     * @param array<string, mixed> $export   Données à exporter
     * @param string               $date     Date au format Y-m-d
     * @param string               $basename Nom de base du fichier (ex : mes-donnees-2026-04-01)
     * @return array{0: string, 1: bool} [content, isZip]
     */
    protected function buildExportContent(array $export, string $date, string $basename): array
    {
        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        if (class_exists('ZipArchive')) {
            $pdf    = $this->buildExportPdf($export, $date);
            $tmpZip = (string) tempnam(sys_get_temp_dir(), 'cbv_export_');
            $zip    = new \ZipArchive();
            $zip->open($tmpZip, \ZipArchive::OVERWRITE);
            $zip->addFromString($basename . '.json', $json);
            $zip->addFromString($basename . '.pdf', $pdf);
            $zip->close();

            $content = (string) file_get_contents($tmpZip);
            unlink($tmpZip);
            return [$content, true];
        }

        return [$json, false];
    }

    /**
     * Construit les headers HTTP de téléchargement de l'export RGPD.
     *
     * @param string $basename Nom de base du fichier sans extension
     * @param bool   $isZip   true → extension .zip, false → extension .json
     * @return array<int, string>
     */
    protected function buildExportHeaders(string $basename, bool $isZip): array
    {
        $ext = $isZip ? 'zip' : 'json';
        return [
            'Cache-Control: no-store, no-cache',
            'Content-Disposition: attachment; filename="' . $basename . '.' . $ext . '"',
        ];
    }

    /**
     * Génère un PDF lisible (RGPD Art. 20 — portabilité) via TCPDF.
     *
     * @param array<string, mixed> $export Données exportées
     * @param string               $date   Date de l'export (Y-m-d)
     * @return string Contenu binaire du PDF
     */
    private function buildExportPdf(array $export, string $date): string
    {
        require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Crabitan Bellevue');
        $pdf->SetAuthor('Crabitan Bellevue');
        $pdf->SetTitle('Mes données personnelles — ' . $date);
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $h = '<style>
            body { font-family: dejavusans; font-size: 10pt; color: #1e1e1e; }
            h1 { font-size: 16pt; color: #c1a14b; margin-bottom: 4px; }
            h2 { font-size: 11pt; color: #c1a14b; margin-top: 14px;'
            . ' margin-bottom: 4px; border-bottom: 1px solid #c1a14b; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
            th { background: #f5f0e8; font-weight: bold; padding: 4px 6px; text-align: left; }
            td { padding: 4px 6px; border-bottom: 1px solid #e8e0d0; }
            .muted { color: #888; font-size: 9pt; }
        </style>';

        $acc = $export['account'];

        $h .= '<h1>Mes données personnelles</h1>';
        $h .= '<p class="muted">Exporté le ' . htmlspecialchars($date) . ' — RGPD Art. 20</p>';

        $h .= '<h2>Compte</h2><table><tr><th>Champ</th><th>Valeur</th></tr>';
        foreach ($acc as $k => $v) {
            $h .= '<tr><td>' . htmlspecialchars($k) . '</td>'
                . '<td>' . htmlspecialchars((string) ($v ?? '—')) . '</td></tr>';
        }
        $h .= '</table>';

        $h .= $this->buildPdfTableSection(
            'Commandes',
            $export['orders'],
            '<tr><th>Référence</th><th>Statut</th><th>Total</th><th>Date</th></tr>',
            static fn(array $o) => '<tr><td>' . htmlspecialchars($o['reference']) . '</td>'
                . '<td>' . htmlspecialchars($o['status']) . '</td>'
                . '<td>' . htmlspecialchars((string) $o['price']) . ' €</td>'
                . '<td>' . htmlspecialchars((string) $o['ordered_at']) . '</td></tr>',
            'Aucune commande.'
        );

        $h .= $this->buildPdfTableSection(
            'Adresses',
            $export['addresses'],
            '<tr><th>Type</th><th>Nom</th><th>Adresse</th><th>Ville</th><th>Pays</th></tr>',
            static fn(array $a) => '<tr><td>' . htmlspecialchars($a['type']) . '</td>'
                . '<td>' . htmlspecialchars($a['firstname'] . ' ' . $a['lastname']) . '</td>'
                . '<td>' . htmlspecialchars($a['street']) . '</td>'
                . '<td>' . htmlspecialchars($a['zip_code'] . ' ' . $a['city']) . '</td>'
                . '<td>' . htmlspecialchars($a['country']) . '</td></tr>',
            'Aucune adresse.'
        );

        /** @var array<int, array<string, mixed>> $adressesCommandes */
        $adressesCommandes = $export['adresses_commandes'] ?? [];
        $h .= $this->buildPdfTableSection(
            'Adresses utilisées sur commandes',
            $adressesCommandes,
            '<tr><th>Commande</th><th>Type</th><th>Nom</th><th>Adresse</th><th>Ville</th><th>Pays</th></tr>',
            static fn(array $s) => '<tr><td>' . htmlspecialchars($s['order_reference'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($s['address_type'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars(($s['firstname'] ?? '') . ' ' . ($s['lastname'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars($s['street'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars(($s['zip'] ?? '') . ' ' . ($s['city'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars($s['country'] ?? '—') . '</td></tr>',
            'Aucune adresse de commande.'
        );

        $h .= $this->buildPdfTableSection(
            'Favoris',
            $export['favorites'],
            '<tr><th>Vin</th><th>Millésime</th></tr>',
            static fn(array $f) => '<tr><td>' . htmlspecialchars($f['name'] ?? '') . '</td>'
                . '<td>' . htmlspecialchars((string) ($f['vintage'] ?? '')) . '</td></tr>',
            'Aucun favori.'
        );

        $h .= $this->buildPdfTableSection(
            'Appareils de confiance (MFA)',
            $export['trusted_devices'],
            '<tr><th>Appareil</th><th>Confirmé le</th><th>Dernière activité</th><th>User-Agent</th></tr>',
            static fn(array $d) => '<tr><td>' . htmlspecialchars($d['device_name'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars((string) ($d['confirmed_at'] ?? '—')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($d['last_seen'] ?? '—')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($d['user_agent'] ?? '—')) . '</td></tr>',
            'Aucun appareil de confiance.'
        );

        $h .= $this->buildPdfTableSection(
            'Sessions actives',
            $export['active_sessions'],
            '<tr><th>Appareil</th><th>Adresse IP</th><th>Connecté le</th><th>Expire le</th></tr>',
            static fn(array $s) => '<tr><td>' . htmlspecialchars($s['device_name'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($s['ip_address'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars((string) ($s['created_at'] ?? '—')) . '</td>'
                . '<td>' . htmlspecialchars((string) ($s['expired_at'] ?? '—')) . '</td></tr>',
            'Aucune session active.'
        );

        /** @var array<int, array<string, mixed>> $connectionLogs */
        $connectionLogs = $export['connection_logs'] ?? [];
        $h .= $this->buildPdfTableSection(
            'Logs de connexion (90 derniers jours)',
            $connectionLogs,
            '<tr><th>Appareil</th><th>IP</th><th>Méthode</th><th>Statut</th><th>Date</th></tr>',
            static fn(array $l) => '<tr><td>' . htmlspecialchars($l['device_name'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($l['ip_address'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($l['auth_method'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars($l['status'] ?? '—') . '</td>'
                . '<td>' . htmlspecialchars((string) ($l['created_at'] ?? '—')) . '</td></tr>',
            'Aucun log de connexion.'
        );

        /** @var array<string, mixed> $consent */
        $consent     = $export['consentements'] ?? [];
        /** @var array<string, mixed> $consentNl */
        $consentNl   = $consent['newsletter'] ?? [];
        /** @var array<int, string> $cgvVersions */
        $cgvVersions = $consent['cgv_versions_acceptees'] ?? [];
        $h .= '<h2>Consentements</h2>';
        $h .= '<table><tr><th>Newsletter abonné</th><th>Email vérifié le</th></tr>';
        $h .= '<tr><td>' . htmlspecialchars((string) ($consentNl['abonne'] ?? '—')) . '</td>'
            . '<td>' . htmlspecialchars((string) ($consentNl['email_verifie_le'] ?? '—')) . '</td></tr>';
        $h .= '</table>';

        if ($cgvVersions !== []) {
            $h .= '<h2>Versions CGV acceptées</h2><table><tr><th>Version</th></tr>';
            foreach ($cgvVersions as $version) {
                $h .= '<tr><td>' . htmlspecialchars((string) $version) . '</td></tr>';
            }
            $h .= '</table>';
        } else {
            $h .= '<h2>Versions CGV acceptées (0)</h2><p class="muted">Aucune commande passée.</p>';
        }

        $pdf->writeHTML($h, true, false, true, false, '');
        return $pdf->Output('', 'S');
    }

    /**
     * Génère une section HTML tableau pour le PDF d'export RGPD.
     *
     * @param array<mixed> $rows
     * @param callable     $rowFn function(array $row): string
     * @return string
     */
    private function buildPdfTableSection(
        string $label,
        array $rows,
        string $header,
        callable $rowFn,
        string $emptyMsg
    ): string {
        $h = '<h2>' . $label . ' (' . count($rows) . ')</h2>';
        if ($rows === []) {
            return $h . '<p class="muted">' . $emptyMsg . '</p>';
        }
        $h .= '<table>' . $header;
        foreach ($rows as $row) {
            $h .= $rowFn($row);
        }
        return $h . '</table>';
    }

    /**
     * Vérifie le token CSRF de la requête.
     *
     * @return bool
     */
    private function verifyCsrf(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /**
     * Vérifie que la requête provient d'un customer authentifié.
     *
     * @return array<string, mixed>
     */
    private function requireCustomer(): array
    {
        $payload = AuthMiddleware::handle(redirectToLogin: true);
        if (($payload['role'] ?? '') !== 'customer') {
            Response::abort(404);
        }
        return $payload;
    }
}
