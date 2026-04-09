<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\LegalDocumentModel;
use Service\AuditLogger;
use Service\MailService;

/**
 * Gestion du versioning des documents légaux depuis le back-office DPO.
 * Accès réservé au rôle super_admin uniquement.
 */
class LegalDocumentAdminController extends AdminController
{
    private const ADMIN_URL     = '/admin/dpo/pages-legales';
    private const VERSION_REGEX = '/^\d+\.\d+(\.\d+)?$/';

    /**
     * Correspondance slug → chemin public (sans la langue).
     *
     * @var array<string, string>
     */
    private const LEGAL_PUBLIC_PATHS = [
        'cgv'                    => '/conditions-generales-de-vente',
        'politique_confidentialite' => '/politique-de-confidentialite',
        'mentions_legales'       => '/mentions-legales',
    ];

    private LegalDocumentModel $model;

    /**
     * Initialise le contrôleur avec le modèle LegalDocument.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->model = new LegalDocumentModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/dpo/pages-legales
    // ----------------------------------------------------------------

    /**
     * Liste tous les documents légaux avec version et date courantes.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireSuperAdmin();

        $this->view('admin/dpo/pages-legales/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'dpo',
            'pageTitle'    => 'Documents légaux — Versioning',
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'DPO', 'url' => '/admin/dpo'],
                ['label' => 'Documents légaux'],
            ],
            'documents'  => $this->model->findAll(),
            'flash'      => $this->getFlash('success'),
            'flashError' => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/dpo/pages-legales/{slug}/modifier
    // ----------------------------------------------------------------

    /**
     * Formulaire de modification de version d'un document légal.
     *
     * @param array<string, string> $params Paramètres de route (slug)
     */
    public function edit(array $params): void
    {
        $adminUser = $this->requireSuperAdmin();
        $document  = $this->model->findBySlug($params['slug'] ?? '');

        if ($document === null) {
            $this->abort(404, 'Document introuvable');
        }

        $this->view('admin/dpo/pages-legales/edit', [
            'adminUser'    => $adminUser,
            'adminSection' => 'dpo',
            'pageTitle'    => 'Modifier — ' . htmlspecialchars($document['label']),
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'DPO', 'url' => '/admin/dpo'],
                ['label' => 'Documents légaux', 'url' => self::ADMIN_URL],
                ['label' => htmlspecialchars($document['label'])],
            ],
            'document'   => $document,
            'flashError' => $this->getFlash('error'),
            'csrfToken'  => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/dpo/pages-legales/{slug}/modifier
    // ----------------------------------------------------------------

    /**
     * Enregistre la nouvelle version d'un document légal.
     * Si la case "Envoyer notification" est cochée et que le document le requiert,
     * redirige vers la page de confirmation avant envoi du batch.
     *
     * @param array<string, string> $params Paramètres de route (slug)
     */
    public function store(array $params): void
    {
        $adminUser = $this->requireSuperAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::ADMIN_URL);
        }

        $slug     = $params['slug'] ?? '';
        $document = $this->model->findBySlug($slug);

        if ($document === null) {
            $this->abort(404, 'Document introuvable');
        }

        $version    = trim($this->request->post('version', ''));
        $updatedAt  = trim($this->request->post('updated_at', ''));
        $changeNote = trim($this->request->post('change_note', ''));

        if (!preg_match(self::VERSION_REGEX, $version)) {
            $this->flash('error', 'Format de version invalide (attendu : X.Y ou X.Y.Z, ex. 1.2 ou 1.2.3).');
            Response::redirect(self::ADMIN_URL . '/' . rawurlencode($slug) . '/modifier');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $updatedAt);
        if ($date === false || $date->format('Y-m-d') !== $updatedAt) {
            $this->flash('error', 'Date invalide.');
            Response::redirect(self::ADMIN_URL . '/' . rawurlencode($slug) . '/modifier');
        }

        if ($date > new \DateTimeImmutable('today')) {
            $this->flash('error', 'La date de mise à jour ne peut pas être dans le futur.');
            Response::redirect(self::ADMIN_URL . '/' . rawurlencode($slug) . '/modifier');
        }

        $this->model->update($slug, $version, $updatedAt, (int) $adminUser['id']);

        AuditLogger::log(
            (int) $adminUser['id'],
            'legal.version_updated',
            'legal_document',
            (int) $document['id'],
            ['slug' => $slug, 'version' => $version, 'updated_at' => $updatedAt],
            AuditLogger::getIp()
        );

        $sendNotification = $this->request->post('send_notification', '') === '1';
        $requiresNotif    = (int) $document['requires_notification'] === 1;

        if ($sendNotification && $requiresNotif) {
            $_SESSION['legal_notify_pending'] = [
                'slug'        => $slug,
                'version'     => $version,
                'doc_id'      => (int) $document['id'],
                'doc_label'   => $document['label'],
                'updated_at'  => $updatedAt,
                'change_note' => $changeNote,
            ];
            Response::redirect(self::ADMIN_URL . '/' . rawurlencode($slug) . '/confirmer-notification');
        }

        $this->flash('success', htmlspecialchars($document['label']) . ' mis à jour en v' . $version . '.');
        Response::redirect(self::ADMIN_URL);
    }

    // ----------------------------------------------------------------
    // GET /admin/dpo/pages-legales/{slug}/confirmer-notification
    // ----------------------------------------------------------------

    /**
     * Affiche la page de confirmation avant envoi du batch de notification.
     *
     * @param array<string, string> $params Paramètres de route (slug)
     */
    public function confirmNotify(array $params): void
    {
        $adminUser = $this->requireSuperAdmin();
        $slug      = $params['slug'] ?? '';
        $pending   = $_SESSION['legal_notify_pending'] ?? null;

        if ($pending === null || ($pending['slug'] ?? '') !== $slug) {
            Response::redirect(self::ADMIN_URL);
        }

        $count = count($this->model->findEligibleAccounts($slug));

        $this->view('admin/dpo/pages-legales/confirm-notification', [
            'adminUser'    => $adminUser,
            'adminSection' => 'dpo',
            'pageTitle'    => 'Confirmer la notification — ' . htmlspecialchars($pending['doc_label']),
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'DPO', 'url' => '/admin/dpo'],
                ['label' => 'Documents légaux', 'url' => self::ADMIN_URL],
                ['label' => 'Confirmer notification'],
            ],
            'pending'    => $pending,
            'count'      => $count,
            'csrfToken'  => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/dpo/pages-legales/{slug}/confirmer-notification
    // ----------------------------------------------------------------

    /**
     * Exécute le batch de notification email pour un document légal mis à jour.
     *
     * @param array<string, string> $params Paramètres de route (slug)
     */
    public function sendNotifications(array $params): void
    {
        $adminUser = $this->requireSuperAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::ADMIN_URL);
        }

        $slug    = $params['slug'] ?? '';
        $pending = $_SESSION['legal_notify_pending'] ?? null;

        if ($pending === null || ($pending['slug'] ?? '') !== $slug) {
            Response::redirect(self::ADMIN_URL);
        }

        unset($_SESSION['legal_notify_pending']);

        $version    = (string) $pending['version'];
        $docId      = (int) $pending['doc_id'];
        $docLabel   = (string) $pending['doc_label'];
        $updatedAt  = (string) $pending['updated_at'];
        $changeNote = (string) $pending['change_note'];
        $appUrl     = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');
        $publicPath = self::LEGAL_PUBLIC_PATHS[$slug] ?? null;

        $accounts = $this->model->findEligibleAccounts($slug);
        $mail     = new MailService();
        $success  = 0;
        $failure  = 0;

        foreach ($accounts as $account) {
            $lang   = in_array($account['lang'] ?? '', ['fr', 'en'], true) ? $account['lang'] : 'fr';
            $docUrl = $appUrl . '/' . $lang . ($publicPath ?? '');
            $name   = (string) ($account['display_name'] ?? $account['email']);

            try {
                $mail->sendLegalDocumentUpdate(
                    (string) $account['email'],
                    $name,
                    $lang,
                    $docLabel,
                    $docUrl,
                    $version,
                    $updatedAt,
                    $changeNote
                );
                $this->model->logNotification($docId, $version, (int) $account['id']);
                $success++;
            } catch (\Exception $e) {
                error_log('[Legal notify] Échec envoi account ' . $account['id'] . ' : ' . $e->getMessage());
                $failure++;
            }
        }

        if ($success > 0) {
            $this->model->updateNotificationStats($docId, $success);
        }

        AuditLogger::log(
            (int) $adminUser['id'],
            'legal.notification_sent',
            'legal_document',
            $docId,
            ['slug' => $slug, 'version' => $version, 'success' => $success, 'failure' => $failure],
            AuditLogger::getIp()
        );

        $msg = "Notification envoyée à {$success} utilisateur(s)";
        if ($failure > 0) {
            $msg .= " ({$failure} échec(s))";
        }
        $this->flash('success', $msg . '.');
        Response::redirect(self::ADMIN_URL);
    }
}
