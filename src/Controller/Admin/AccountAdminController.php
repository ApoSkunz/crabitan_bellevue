<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\AccountModel;
use Model\PasswordResetModel;
use Service\AuditLogger;
use Service\MailService;

class AccountAdminController extends AdminController
{
    private const ALLOWED_PER_PAGE = [10, 25, 50];
    private const DEFAULT_PER_PAGE = 10;

    // ----------------------------------------------------------------
    // GET /admin/comptes
    // ----------------------------------------------------------------

    /**
     * Affiche la liste paginée des comptes clients.
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser  = $this->requireAdmin();
        $accounts   = new AccountModel();
        $page       = max(1, (int) $this->request->get('page', 1));
        $role       = $this->request->get('role') ?: null;
        $type       = $this->request->get('type') ?: null;
        $search     = trim($this->request->get('search', ''));
        $perPageReq = (int) $this->request->get('per_page', self::DEFAULT_PER_PAGE);
        $perPage    = in_array($perPageReq, self::ALLOWED_PER_PAGE, true) ? $perPageReq : self::DEFAULT_PER_PAGE;

        $allowedSorts = ['created_at_desc', 'created_at_asc', 'last_connection_desc', 'last_connection_asc'];
        $sortReq      = $this->request->get('sort') ?: null;
        $sort         = ($sortReq !== null && in_array($sortReq, $allowedSorts, true)) ? $sortReq : null;

        $total = $accounts->countForAdmin($role, $search ?: null, $type);
        $list  = $accounts->getForAdmin($perPage, ($page - 1) * $perPage, $role, $search ?: null, $type, $sort);

        $this->view('admin/accounts/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'accounts',
            'pageTitle'    => 'Comptes clients',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Comptes']],
            'accounts'     => $list,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'role'         => $role,
            'type'         => $type,
            'search'       => $search,
            'sort'         => $sort,
            'currentRole'  => $adminUser['role'] ?? '',
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/comptes/{id}/verifier  (super_admin uniquement)
    // ----------------------------------------------------------------

    /**
     * Force la vérification email d'un compte (super_admin uniquement).
     *
     * @param array<string, mixed> $params Paramètres de route (id)
     * @return void
     */
    public function verify(array $params): void
    {
        $adminUser = $this->requireSuperAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/comptes');
        }

        $id      = (int) $params['id'];
        $account = new AccountModel();
        $account->verifyEmail($id);

        AuditLogger::log(
            $adminUser['id'],
            'account.email_verified',
            'account',
            $id,
            [],
            AuditLogger::getIp()
        );

        $this->flash('success', "Compte #{$id} vérifié avec succès.");
        Response::redirect('/admin/comptes');
    }

    // ----------------------------------------------------------------
    // POST /admin/comptes/{id}/reset-password  (super_admin uniquement)
    // ----------------------------------------------------------------

    /**
     * Envoie un lien de réinitialisation de mot de passe à un compte (super_admin uniquement).
     *
     * @param array<string, mixed> $params Paramètres de route (id)
     * @return void
     */
    public function resetPassword(array $params): void
    {
        $adminUser = $this->requireSuperAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/comptes');
        }

        $id      = (int) $params['id'];
        $account = new AccountModel();
        $row     = $account->findById($id);

        if (!$row) {
            $this->flash('error', "Compte #{$id} introuvable.");
            Response::redirect('/admin/comptes');
        }

        if ($row['password'] === null) {
            $this->flash('error', "Compte #{$id} est un compte Google — pas de mot de passe à réinitialiser.");
            Response::redirect('/admin/comptes');
        }

        $lang        = $row['lang'] ?? 'fr';
        $displayName = $row['account_type'] === 'company'
            ? ($row['company_name'] ?? 'Client')
            : trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));

        $token    = bin2hex(random_bytes(32));
        $resets   = new PasswordResetModel();
        $resets->create($id, $token);
        $resetUrl = ($_ENV['APP_URL'] ?? '') . "/{$lang}/reinitialisation/{$token}";

        try {
            $mail = new MailService();
            $mail->sendPasswordReset($row['email'], $displayName, $resetUrl, $lang);
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e);
            $this->flash('error', "Erreur envoi email pour compte #{$id}.");
            Response::redirect('/admin/comptes');
        }

        AuditLogger::log(
            $adminUser['id'],
            'account.admin_password_reset_sent',
            'account',
            $id,
            [],
            AuditLogger::getIp()
        );

        $this->flash('success', "Lien de réinitialisation envoyé à {$row['email']}.");
        Response::redirect('/admin/comptes');
    }
}
