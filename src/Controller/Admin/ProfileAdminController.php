<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\CookieHelper;
use Core\Response;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\TrustedDeviceModel;
use Service\PasswordValidator;
use Service\RateLimiterService;
use Service\SecurityAlertService;

class ProfileAdminController extends AdminController
{
    private const ADMIN_URL    = '/admin';
    private const SECURITY_URL = '/admin/securite';

    /** Rate limiting changement de mot de passe admin : 1 tentative / jour / IP. */
    private const MAX_ADMIN_PW_IP      = 1;
    private const WINDOW_ADMIN_PW      = 86400;

    /** Rate limiting supplémentaire : max 2 changements de mdp / 24h / compte admin. */
    private const MAX_ADMIN_PW_ACCOUNT = 2;
    private const WINDOW_ADMIN_PW_ACC  = 86400;

    private AccountModel $accounts;
    private ConnectionModel $connections;
    private TrustedDeviceModel $trustedDevices;
    private RateLimiterService $rateLimiter;
    private SecurityAlertService $securityAlert;

    /**
     * Initialise le contrôleur profil admin avec ses dépendances.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts       = new AccountModel();
        $this->connections    = new ConnectionModel();
        $this->trustedDevices = new TrustedDeviceModel();
        $this->rateLimiter    = new RateLimiterService();
        $this->securityAlert  = new SecurityAlertService();
    }

    // ----------------------------------------------------------------
    // GET /admin/securite
    // ----------------------------------------------------------------

    /**
     * Affiche la page de sécurité/profil de l'admin courant.
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
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

        // Rate limiting : 1 tentative / jour / IP
        // checkLimit d'abord, recordAttempt ensuite (compté même si le changement réussit)
        $rlKey = 'admin_change_pw:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!$this->rateLimiter->checkLimit($rlKey, self::MAX_ADMIN_PW_IP, self::WINDOW_ADMIN_PW)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($rlKey) / 3600);
            $this->flash('error', sprintf(__('auth.too_many_attempts_hours'), max(1, $wait)));
            Response::redirect(self::SECURITY_URL);
        }
        $this->rateLimiter->recordAttempt($rlKey, self::WINDOW_ADMIN_PW);

        // Rate limiting supplémentaire : max 2 changements de mdp / 24h / compte admin
        $rlAccountKey = 'admin_change_pw_account:' . $adminUser['id'];
        if (!$this->rateLimiter->checkLimit($rlAccountKey, self::MAX_ADMIN_PW_ACCOUNT, self::WINDOW_ADMIN_PW_ACC)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($rlAccountKey) / 3600);
            $this->flash('error', sprintf(__('auth.too_many_attempts_hours'), max(1, $wait)));
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

        $this->rateLimiter->recordAttempt($rlAccountKey, self::WINDOW_ADMIN_PW_ACC);
        $this->accounts->updatePassword($adminUser['id'], password_hash($new, PASSWORD_BCRYPT));

        // Alerte email : changement de mot de passe admin (IP + user-agent)
        // $account est garanti non-null ici (la branche !$account redirige plus haut)
        $this->securityAlert->sendPasswordChangeAlert(
            $account,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );

        $this->flash('success', __('account.password_updated'));
        Response::redirect(self::SECURITY_URL);
    }

    // ----------------------------------------------------------------
    // POST /admin/securite/session/{id}/revoquer
    // ----------------------------------------------------------------

    /**
     * Révoque une session active de l'admin courant (POST).
     *
     * @param array<string, mixed> $params Paramètres de route (id)
     * @return void
     */
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

    /**
     * Révoque toutes les sessions actives de l'admin courant sauf la courante (POST).
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
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

    /**
     * Retire la confiance d'un appareil de l'admin courant (POST).
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
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

    /**
     * Retire la confiance de tous les appareils de l'admin courant (POST).
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
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

    /**
     * Réinitialise complètement la sécurité de l'admin (sessions + appareils de confiance) (POST).
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
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
