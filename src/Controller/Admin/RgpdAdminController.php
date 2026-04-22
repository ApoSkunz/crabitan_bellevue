<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\AccountModel;
use Model\RgpdExportLogModel;
use Model\RgpdRequestModel;
use Service\MailService;
use Service\RgpdExportService;

/**
 * Contrôleur back-office — gestion des demandes d'exercice de droits RGPD.
 *
 * Couvre les droits : accès (Art. 15), rectification (Art. 16),
 * effacement (Art. 17), portabilité (Art. 20), opposition (Art. 21).
 * Délai légal de traitement : 1 mois (Art. 12 RGPD).
 */
class RgpdAdminController extends AdminController
{
    public const DEFAULT_PER_PAGE = 20;
    private const ALLOWED_PER_PAGE = [10, 20, 50];
    private const ROUTE_LIST = '/admin/rgpd/demandes';
    private const ROUTE_ADD  = '/admin/rgpd/demandes/ajouter';

    /**
     * Instancie le RgpdExportService (seam de testabilité).
     *
     * @return RgpdExportService
     */
    protected function newRgpdExportService(): RgpdExportService
    {
        return new RgpdExportService();
    }

    /**
     * Instancie le MailService (seam de testabilité).
     *
     * @return MailService
     */
    protected function newMailService(): MailService
    {
        return new MailService();
    }

    /**
     * Instancie le AccountModel (seam de testabilité).
     *
     * @return AccountModel
     */
    protected function newAccountModel(): AccountModel
    {
        return new AccountModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/rgpd/demandes
    // ----------------------------------------------------------------

    /**
     * Liste paginée des demandes RGPD avec filtres statut / type.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser   = $this->requireSuperAdmin();
        $model       = new RgpdRequestModel();
        $exportModel = new RgpdExportLogModel();

        // Demandes manuelles
        $page       = max(1, (int) $this->request->get('page', 1));
        $perPageReq = (int) $this->request->get('per_page', self::DEFAULT_PER_PAGE);
        $perPage    = in_array($perPageReq, self::ALLOWED_PER_PAGE, true) ? $perPageReq : self::DEFAULT_PER_PAGE;
        $status     = $this->request->get('status') ?: null;
        $type       = $this->request->get('type')   ?: null;

        $total    = $model->count($status, $type);
        $requests = $model->getAll($perPage, ($page - 1) * $perPage, $status, $type);
        $overdue  = $model->countOverdue();

        // Journal exports automatiques
        $logPage     = max(1, (int) $this->request->get('log_page', 1));
        $logEmail    = $this->request->get('log_email') ?: null;
        $logDateFrom = $this->request->get('log_date_from') ?: null;
        $logDateTo   = $this->request->get('log_date_to')   ?: null;
        $logTotal    = $exportModel->count($logEmail, $logDateFrom, $logDateTo);
        $exportLogs  = $exportModel->getAll(self::DEFAULT_PER_PAGE, ($logPage - 1) * self::DEFAULT_PER_PAGE, $logEmail, $logDateFrom, $logDateTo);

        $this->view('admin/rgpd/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'dpo',
            'pageTitle'    => 'RGPD — Demandes & Exports',
            'breadcrumbs'  => [
                ['label' => 'Admin',       'url' => '/admin'],
                ['label' => 'DPO — RGPD',  'url' => '/admin/dpo'],
                ['label' => 'Demandes RGPD'],
            ],
            'requests'     => $requests,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'overdue'      => $overdue,
            'statusFilter' => $status,
            'typeFilter'   => $type,
            'exportLogs'   => $exportLogs,
            'logTotal'     => $logTotal,
            'logPage'      => $logPage,
            'logEmail'     => $logEmail,
            'logDateFrom'  => $logDateFrom,
            'logDateTo'    => $logDateTo,
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/rgpd/demandes/ajouter
    // ----------------------------------------------------------------

    /**
     * Formulaire de saisie d'une nouvelle demande RGPD reçue par email.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function create(array $_params): void // NOSONAR — php:S1172
    {
        $adminUser = $this->requireAdmin();

        $this->view('admin/rgpd/create', [
            'adminUser'    => $adminUser,
            'adminSection' => 'dpo',
            'pageTitle'    => 'Nouvelle demande RGPD',
            'breadcrumbs'  => [
                ['label' => 'Admin',          'url' => '/admin'],
                ['label' => 'DPO — RGPD',     'url' => '/admin/dpo'],
                ['label' => 'Demandes RGPD',  'url' => self::ROUTE_LIST],
                ['label' => 'Nouvelle demande'],
            ],
            'flashError' => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/rgpd/demandes/ajouter
    // ----------------------------------------------------------------

    /**
     * Enregistre une nouvelle demande RGPD saisie par un admin.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function store(array $_params): void // NOSONAR — php:S1172
    {
        $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::ROUTE_ADD);
        }

        $type       = trim($this->request->post('type', ''));
        $email      = trim($this->request->post('requester_email', ''));
        $name       = trim($this->request->post('requester_name', ''));
        $receivedAt = trim($this->request->post('received_at', ''));

        if (!in_array($type, RgpdRequestModel::VALID_TYPES, true)) {
            $this->flash('error', 'Type de demande invalide.');
            Response::redirect(self::ROUTE_ADD);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Adresse email invalide.');
            Response::redirect(self::ROUTE_ADD);
        }

        if ($name === '') {
            $this->flash('error', 'Le nom du demandeur est requis.');
            Response::redirect(self::ROUTE_ADD);
        }

        $parsedDate = null;
        if ($receivedAt !== '') {
            $ts = strtotime($receivedAt);
            if ($ts === false) {
                $this->flash('error', 'Date de réception invalide.');
                Response::redirect(self::ROUTE_ADD);
            }
            $parsedDate = date('Y-m-d H:i:s', $ts);
        }

        $model = new RgpdRequestModel();
        $model->create($type, $email, $name, $parsedDate);

        $this->flash('success', 'Demande RGPD enregistrée avec succès.');
        Response::redirect(self::ROUTE_LIST);
    }

    // ----------------------------------------------------------------
    // POST /admin/rgpd/demandes/{id}/statut
    // ----------------------------------------------------------------

    /**
     * Met à jour le statut d'une demande RGPD.
     * Pour le type "effacement" et statut "traitee", déclenche l'anonymisation du compte.
     * Pour le type "portabilite" et statut "traitee", déclenche l'export RGPD par email.
     *
     * @param array<string, string> $params Paramètres de route (id)
     * @return void
     */
    public function updateStatus(array $params): void
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::ROUTE_LIST);
        }

        $id     = (int) ($params['id'] ?? 0);
        $status = trim($this->request->post('status', ''));
        $note   = trim($this->request->post('response_note', '')) ?: null;

        $model   = new RgpdRequestModel();
        $request = $model->findById($id);

        if (!$request) {
            Response::abort(404);
        }

        if (!in_array($status, RgpdRequestModel::VALID_STATUSES, true)) {
            $this->flash('error', 'Statut invalide.');
            Response::redirect(self::ROUTE_LIST);
        }

        $ok = $model->updateStatus($id, $status, $adminUser['id'], $note);

        if (!$ok) {
            $this->flash('error', 'Erreur lors de la mise à jour.');
            Response::redirect(self::ROUTE_LIST);
        }

        if ($status === 'traitee') {
            if ($request['type'] === 'effacement') {
                $this->handleEffacement($request);
            } elseif ($request['type'] === 'portabilite') {
                $this->handlePortabilite($request);
            }
        }

        $this->flash('success', 'Statut mis à jour.');
        Response::redirect(self::ROUTE_LIST);
    }

    // ----------------------------------------------------------------
    // GET /admin/rgpd/demandes/{id}/export-portabilite
    // ----------------------------------------------------------------

    /**
     * Déclenche manuellement un export RGPD pour une demande de portabilité.
     *
     * @param array<string, string> $params Paramètres de route (id)
     * @return void
     */
    public function exportPortabilite(array $params): void
    {
        $this->requireAdmin();

        $id    = (int) ($params['id'] ?? 0);
        $model = new RgpdRequestModel();
        $req   = $model->findById($id);

        if (!$req) {
            Response::abort(404);
        }

        if ($req['type'] !== 'portabilite') {
            $this->flash('error', 'Cette action est réservée aux demandes de portabilité.');
            Response::redirect(self::ROUTE_LIST);
        }

        $this->handlePortabilite($req);
        $this->flash('success', 'Export RGPD envoyé à ' . htmlspecialchars($req['requester_email']) . '.');
        Response::redirect(self::ROUTE_LIST);
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Gère l'effacement : recherche le compte par email et déclenche le soft-delete.
     *
     * @param array<string, mixed> $request Demande RGPD
     * @return void
     */
    private function handleEffacement(array $request): void // NOSONAR — php:S1172 : adminId retiré (non utilisé)
    {
        $email        = (string) ($request['requester_email'] ?? '');
        $accountModel = new AccountModel();
        $account      = $accountModel->findByEmail($email);

        if ($account) {
            $accountModel->delete((int) $account['id']);
        }
    }

    /**
     * Gère la portabilité : envoie l'export RGPD par email au demandeur.
     *
     * @param array<string, mixed> $request Demande RGPD
     * @return void
     */
    private function handlePortabilite(array $request): void
    {
        $email  = (string) ($request['requester_email'] ?? '');
        $name   = (string) ($request['requester_name']  ?? $email);
        $svc    = $this->newRgpdExportService();

        $accountModel = $this->newAccountModel();
        $account      = $accountModel->findByEmail($email);
        $sub          = null;

        if ($account) {
            $exportData = $svc->buildForAccount((int) $account['id']);
        } else {
            $sub = \Core\Database::getInstance()->fetchOne(
                'SELECT email, newsletter_confirmed, newsletter_consent_date,
                        newsletter_consent_ip, consent_ip, lang, created_at
                 FROM newsletter_subscriptions
                 WHERE email = ?',
                [$email]
            );
            $exportData = $sub
                ? $svc->buildForSubscriber($sub)
                : ['exported_at' => date('c'), 'email' => $email, 'note' => 'Aucune donnée trouvée'];
        }

        $basename             = 'export-rgpd-' . date('Y-m-d');
        [$content, $filename] = $svc->buildZip($exportData, $basename);

        try {
            $mailLang    = (string) ($account['lang'] ?? $sub['lang'] ?? 'fr');
            $mailService = $this->newMailService();
            $mailService->sendRgpdExport($email, $name, $content, $filename, $mailLang);
        } catch (\Exception $e) {
            \Core\ErrorLogger::warning($e);
        }
    }
}
