<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\CookieHelper;
use Core\Response;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\TrustedDeviceModel;
use Service\PasswordValidator;

class ProfileAdminController extends AdminController
{
    private const ADMIN_URL    = '/admin';
    private const SECURITY_URL = '/admin/securite';

    private AccountModel $accounts;
    private ConnectionModel $connections;
    private TrustedDeviceModel $trustedDevices;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts       = new AccountModel();
        $this->connections    = new ConnectionModel();
        $this->trustedDevices = new TrustedDeviceModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/securite
    // ----------------------------------------------------------------

    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();
        $userId    = $adminUser['id'];

        $this->view('admin/profile/index', [
            'adminUser'          => $adminUser,
            'adminSection'       => 'profile',
            'pageTitle'          => 'Sécurité',
            'breadcrumbs'        => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Sécurité'],
            ],
            'success'            => $this->getFlash('success'),
            'error'              => $this->getFlash('error'),
            'csrfToken'          => $_SESSION['csrf'] ?? '',
            'sessions'           => $this->connections->getActiveForUser($userId),
            'trustedDevices'     => $this->trustedDevices->getForUser($userId),
            'currentToken'       => $_COOKIE['auth_token'] ?? null,
            'currentDeviceToken' => $_COOKIE['device_token'] ?? null,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/securite/mot-de-passe
    // ----------------------------------------------------------------

    /**
     * Traite le formulaire de changement de mot de passe depuis l'interface d'administration.
     *
     * Vérifie que l'ancien mot de passe est correct, que le nouveau respecte
     * la politique de sécurité et qu'il est différent de l'actuel.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function changePassword(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Jeton CSRF invalide.');
            Response::redirect(self::SECURITY_URL);
        }

        $current = $this->request->post('current_password', '');
        $new     = $this->request->post('new_password', '');
        $confirm = $this->request->post('new_password_confirm', '');

        $account = $this->accounts->findById($adminUser['id']);

        if (!$account || !password_verify($current, $account['password'] ?? '')) {
            $this->flash('error', __('account.wrong_current_password'));
            Response::redirect(self::SECURITY_URL);
        }

        if (password_verify($new, $account['password'] ?? '')) {
            $this->flash('error', __('account.password_same_as_current'));
            Response::redirect(self::SECURITY_URL);
        }

        if (!PasswordValidator::isStrong($new)) {
            $this->flash('error', __('auth.password_too_weak'));
            Response::redirect(self::SECURITY_URL);
        }

        if ($new !== $confirm) {
            $this->flash('error', __('validation.password_match'));
            Response::redirect(self::SECURITY_URL);
        }

        $this->accounts->updatePassword($adminUser['id'], password_hash($new, PASSWORD_BCRYPT));
        $this->flash('success', __('account.password_updated'));
        Response::redirect(self::SECURITY_URL);
    }

    // ----------------------------------------------------------------
    // POST /admin/securite/session/{id}/revoquer
    // ----------------------------------------------------------------

    public function revokeSession(array $params): void
    {
        $adminUser = $this->requireAdmin();
        $userId    = $adminUser['id'];
        $id        = (int) ($params['id'] ?? 0);

        if ($this->verifyCsrf() && $id > 0) {
            $tokenOfRevoked = $this->connections->getTokenById($id, $userId);
            $this->connections->revokeById($id, $userId);

            $currentToken = $_COOKIE['auth_token'] ?? null;
            if ($tokenOfRevoked !== null && $currentToken !== null && $tokenOfRevoked === $currentToken) {
                CookieHelper::clear();
                Response::redirect(self::ADMIN_URL);
            }
        }

        Response::redirect(self::SECURITY_URL);
    }

    // ----------------------------------------------------------------
    // POST /admin/securite/sessions/revoquer-toutes
    // ----------------------------------------------------------------

    public function revokeAllSessions(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            Response::redirect(self::SECURITY_URL);
        }

        $this->accounts->revokeAllSessions($adminUser['id']);
        CookieHelper::clear();
        Response::redirect(self::ADMIN_URL);
    }

    // ----------------------------------------------------------------
    // POST /admin/securite/appareils/retirer-confiance
    // ----------------------------------------------------------------

    public function untrustDevice(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser   = $this->requireAdmin();
        $userId      = $adminUser['id'];
        $deviceToken = $this->request->post('device_token', '');

        if ($this->verifyCsrf() && $deviceToken !== '') {
            $this->trustedDevices->untrust($userId, $deviceToken);
        }

        Response::redirect(self::SECURITY_URL . '#appareils');
    }

    // ----------------------------------------------------------------
    // POST /admin/securite/appareils/supprimer-toutes
    // ----------------------------------------------------------------

    public function untrustAllDevices(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            Response::redirect(self::SECURITY_URL);
        }

        $this->trustedDevices->deleteAllForUser($adminUser['id']);
        $this->flash('success', 'Tous les appareils de confiance ont été supprimés.');
        Response::redirect(self::SECURITY_URL . '#appareils');
    }

    // ----------------------------------------------------------------
    // POST /admin/securite/reinitialiser
    // ----------------------------------------------------------------

    public function resetSecurity(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();
        $userId    = $adminUser['id'];

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Jeton CSRF invalide.');
            Response::redirect(self::SECURITY_URL);
        }

        $password = $this->request->post('password', '');
        $account  = $this->accounts->findById($userId);

        if (
            !$account
            || $account['password'] === null
            || !password_verify($password, (string) $account['password'])
        ) {
            $this->flash('error', 'Mot de passe incorrect.');
            Response::redirect(self::SECURITY_URL);
        }

        $this->accounts->revokeAllSessions($userId);
        $this->trustedDevices->deleteAllForUser($userId);
        CookieHelper::clear();

        Response::redirect(self::ADMIN_URL);
    }
}
