<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\CookieHelper;
use Core\Response;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\TrustedDeviceModel;

class ProfileAdminController extends AdminController
{
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

    public function changePassword(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Jeton CSRF invalide.');
            Response::redirect('/admin/securite');
        }

        $current = $this->request->post('current_password', '');
        $new     = $this->request->post('new_password', '');
        $confirm = $this->request->post('new_password_confirm', '');

        $account = $this->accounts->findById($adminUser['id']);

        if (!$account || !password_verify($current, $account['password'] ?? '')) {
            $this->flash('error', 'Mot de passe actuel incorrect.');
            Response::redirect('/admin/securite');
        }

        if (strlen($new) < 12) {
            $this->flash('error', 'Le nouveau mot de passe doit contenir au moins 12 caractères.');
            Response::redirect('/admin/securite');
        }

        if ($new !== $confirm) {
            $this->flash('error', 'Les mots de passe ne correspondent pas.');
            Response::redirect('/admin/securite');
        }

        $this->accounts->updatePassword($adminUser['id'], password_hash($new, PASSWORD_BCRYPT));
        $this->flash('success', 'Mot de passe mis à jour avec succès.');
        Response::redirect('/admin/securite');
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
                Response::redirect('/admin');
            }
        }

        Response::redirect('/admin/securite');
    }

    // ----------------------------------------------------------------
    // POST /admin/securite/sessions/revoquer-toutes
    // ----------------------------------------------------------------

    public function revokeAllSessions(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            Response::redirect('/admin/securite');
        }

        $this->accounts->revokeAllSessions($adminUser['id']);
        CookieHelper::clear();
        Response::redirect('/admin');
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

        Response::redirect('/admin/securite#appareils');
    }

    // ----------------------------------------------------------------
    // POST /admin/securite/appareils/supprimer-toutes
    // ----------------------------------------------------------------

    public function untrustAllDevices(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            Response::redirect('/admin/securite');
        }

        $this->trustedDevices->deleteAllForUser($adminUser['id']);
        $this->flash('success', 'Tous les appareils de confiance ont été supprimés.');
        Response::redirect('/admin/securite#appareils');
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
            Response::redirect('/admin/securite');
        }

        $password = $this->request->post('password', '');
        $account  = $this->accounts->findById($userId);

        if (
            !$account
            || $account['password'] === null
            || !password_verify($password, (string) $account['password'])
        ) {
            $this->flash('error', 'Mot de passe incorrect.');
            Response::redirect('/admin/securite');
        }

        $this->accounts->revokeAllSessions($userId);
        $this->trustedDevices->deleteAllForUser($userId);
        CookieHelper::clear();

        Response::redirect('/admin');
    }
}
