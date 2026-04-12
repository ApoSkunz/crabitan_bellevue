<?php

declare(strict_types=1);

namespace Controller\Account;

use Core\Response;
use Model\AccountModel;
use Model\RgpdExportLogModel;
use Repository\MysqlUserRepository;
use Repository\UserRepository;
use Service\RateLimiterService;
use Service\RgpdExportService;

/**
 * Gère l'export RGPD des données personnelles (Art. 20) depuis l'espace client.
 */
class AccountExportController extends AbstractAccountController
{
    /** Rate limiting export RGPD : 3 exports / 24 h / utilisateur. */
    private const MAX_EXPORT_USER = 3;
    private const WINDOW_EXPORT   = 86400;

    private UserRepository $userRepo;
    private RateLimiterService $rateLimiter;
    private RgpdExportLogModel $rgpdExportLogs;
    private RgpdExportService $exportService;

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
        $this->exportService  = new RgpdExportService();
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
        return $this->exportService->buildForAccount($userId);
    }

    /**
     * Construit le contenu binaire de l'export RGPD (ZIP JSON+PDF ou JSON fallback).
     * Délègue à RgpdExportService — source unique de vérité.
     *
     * @param array<string, mixed> $export   Données à exporter
     * @param string               $date     Date au format Y-m-d
     * @param string               $basename Nom de base du fichier (ex : mes-donnees-2026-04-01)
     * @return array{0: string, 1: bool} [content, isZip]
     */
    protected function buildExportContent(array $export, string $date, string $basename): array
    {
        return $this->exportService->buildExportContent($export, $date, $basename);
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
}
