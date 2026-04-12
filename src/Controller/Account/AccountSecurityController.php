<?php

declare(strict_types=1);

namespace Controller\Account;

use Core\CookieHelper;
use Core\Response;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\DeviceConfirmTokenModel;
use Model\OrderModel;
use Model\TrustedDeviceModel;
use Repository\MysqlUserRepository;
use Repository\UserRepository;
use Service\PasswordValidator;
use Service\RateLimiterService;

/**
 * Gère la sécurité du compte : mot de passe, MFA, sessions, appareils, suppression, réactivation.
 */
class AccountSecurityController extends AbstractAccountController
{
    /** Rate limiting changement de mot de passe : 3 tentatives / 15 min / IP. */
    private const MAX_CHANGE_PW_IP = 3;
    private const WINDOW_CHANGE_PW = 900;

    private const VIEW_REACTIVATE       = 'account/reactivate';
    private const VIEW_SECURITY_CONFIRM = 'account/security-confirm';
    private const VIEW_SECURITY_DENY    = 'account/security-deny';

    private UserRepository $userRepo;
    /** AccountModel conservé pour les méthodes hors interface (getReactivationToken, getPendingSessionToken). */
    private AccountModel $accounts;
    private ConnectionModel $connections;
    private TrustedDeviceModel $trustedDevices;
    private DeviceConfirmTokenModel $deviceConfirmTokens;
    private OrderModel $orders;
    private RateLimiterService $rateLimiter;

    /**
     * Initialise le contrôleur et ses dépendances.
     *
     * @param \Core\Request       $request  Requête HTTP courante
     * @param UserRepository|null $userRepo Dépôt utilisateur (null → MysqlUserRepository par défaut)
     */
    public function __construct(\Core\Request $request, ?UserRepository $userRepo = null)
    {
        parent::__construct($request);
        $this->accounts            = new AccountModel();
        $this->userRepo            = $userRepo ?? new MysqlUserRepository($this->accounts);
        $this->connections         = new ConnectionModel();
        $this->trustedDevices      = new TrustedDeviceModel();
        $this->deviceConfirmTokens = new DeviceConfirmTokenModel();
        $this->orders              = new OrderModel();
        $this->rateLimiter         = new RateLimiterService();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/securite
    // ----------------------------------------------------------------

    /**
     * Affiche la page sécurité du compte.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function security(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $success = $_SESSION['flash']['security_success'] ?? null;
        $errors  = $_SESSION['flash']['security_errors']  ?? [];
        unset($_SESSION['flash']['security_success'], $_SESSION['flash']['security_errors']);

        $account = $this->userRepo->findById($userId);

        $this->view('account/security', [
            'lang'               => $lang,
            'account'            => $account,
            'hasPassword'        => $account && $account['password'] !== null,
            'sessions'           => $this->connections->getActiveForUser($userId),
            'trustedDevices'     => $this->trustedDevices->getForUser($userId),
            'currentToken'       => $_COOKIE['auth_token'] ?? null,
            'currentDeviceToken' => $_COOKIE['device_token'] ?? null,
            'errors'             => $errors,
            'success'            => $success,
            'csrf'               => $_SESSION['csrf'] ?? '',
            'activeOrdersCount'  => $this->orders->countActiveOrdersForUser($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/mot-de-passe
    // ----------------------------------------------------------------

    /**
     * Traite le formulaire de changement de mot de passe depuis l'espace compte.
     *
     * @param array<string, string> $params Paramètres de route (contient 'lang')
     * @return void
     */
    public function changePassword(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $back    = "/{$lang}/mon-compte/securite";

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['security_errors'] = ['csrf' => __('error.csrf')];
            Response::redirect($back);
        }

        $rlKey = 'change_pw:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!$this->rateLimiter->checkLimit($rlKey, self::MAX_CHANGE_PW_IP, self::WINDOW_CHANGE_PW)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($rlKey) / 60);
            $msg = sprintf(__('auth.too_many_attempts'), max(1, $wait));
            $_SESSION['flash']['security_errors'] = ['rate_limit' => $msg];
            Response::redirect($back);
        }
        $this->rateLimiter->recordAttempt($rlKey, self::WINDOW_CHANGE_PW);

        $current = $this->request->post('current_password', '');
        $new     = $this->request->post('new_password', '');
        $confirm = $this->request->post('new_password_confirm', '');

        $account = $this->userRepo->findById($userId);
        $errors  = [];

        if (
            !$account
            || $account['password'] === null
            || !password_verify($current, $account['password'])
        ) {
            $errors['current_password'] = __('account.wrong_current_password');
        }

        if (
            empty($errors['current_password'])
            && $account !== false
            && password_verify($new, (string) $account['password'])
        ) {
            $errors['new_password'] = __('account.password_same_as_current');
        }

        if (!PasswordValidator::isStrong($new)) {
            $errors['new_password'] = $errors['new_password'] ?? __('auth.password_too_weak');
        }

        if ($new !== $confirm) {
            $errors['new_password_confirm'] = __('validation.password_match');
        }

        if ($errors !== []) {
            $_SESSION['flash']['security_errors'] = $errors;
            Response::redirect($back);
        }

        $pendingHash  = password_hash($new, PASSWORD_BCRYPT);
        $expiresAt    = date('Y-m-d H:i:s', time() + 3600);
        $sessionToken = $_COOKIE['auth_token'] ?? '';
        $this->userRepo->savePendingPassword($userId, $pendingHash, $expiresAt, $sessionToken);

        if ($account && isset($account['email'])) {
            $notifName = trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''))
                ?: ($account['company_name'] ?? 'Client');
            $rawToken  = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $this->userRepo->saveSecurityConfirmToken($userId, $tokenHash, $expiresAt);
            try {
                (new \Service\MailService())->sendPasswordChangeConfirmation(
                    (string) $account['email'],
                    (string) $notifName,
                    $lang,
                    $rawToken
                );
            } catch (\Throwable $e) {
                \Core\ErrorLogger::warning($e);
            }
        }

        $_SESSION['flash']['security_success'] = __('account.password_change_pending');
        Response::redirect($back);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/sessions/revoquer-toutes
    // ----------------------------------------------------------------

    /**
     * Révoque toutes les sessions actives et déconnecte l'utilisateur.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function revokeAllUserSessions(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        if (!$this->verifyCsrf()) {
            Response::redirect("/{$lang}/mon-compte/securite");
        }

        $this->userRepo->revokeAllSessions($userId);

        CookieHelper::clear();
        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/session/{id}/revoquer
    // ----------------------------------------------------------------

    /**
     * Révoque une session active spécifique.
     *
     * @param array<string, string> $params Paramètres de route (lang, id)
     * @return void
     */
    public function revokeSession(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $id      = (int) ($params['id'] ?? 0);

        if ($this->verifyCsrf() && $id > 0) {
            $tokenOfRevoked = $this->connections->getTokenById($id, $userId);
            $this->connections->revokeById($id, $userId);

            $currentToken = $_COOKIE['auth_token'] ?? null;
            if ($tokenOfRevoked !== null && $currentToken !== null && $tokenOfRevoked === $currentToken) {
                CookieHelper::clear();
                Response::redirect("/{$lang}");
            }
        }

        Response::redirect("/{$lang}/mon-compte/securite");
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/appareils/retirer-confiance
    // ----------------------------------------------------------------

    /**
     * Retire la confiance d'un appareil spécifique.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function untrustDevice(array $params): void
    {
        $payload     = $this->requireCustomer();
        $userId      = (int) $payload['sub'];
        $lang        = $params['lang'];
        $deviceToken = $this->request->post('device_token', '');

        if ($this->verifyCsrf() && $deviceToken !== '') {
            $this->trustedDevices->untrust($userId, $deviceToken);
        }

        Response::redirect("/{$lang}/mon-compte/securite#appareils");
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/appareils/supprimer-toutes
    // ----------------------------------------------------------------

    /**
     * Supprime tous les appareils de confiance.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function untrustAllDevices(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['security_errors'] = ['csrf' => __('error.csrf')];
            Response::redirect("/{$lang}/mon-compte/securite");
        }

        $this->trustedDevices->deleteAllForUser($userId);
        $_SESSION['flash']['security_success'] = __('account.untrust_all_done');
        Response::redirect("/{$lang}/mon-compte/securite#appareils");
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/reinitialiser
    // ----------------------------------------------------------------

    /**
     * Réinitialise la sécurité : révoque toutes sessions et appareils.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function resetSecurity(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['security_errors'] = ['csrf' => __('error.csrf')];
            Response::redirect("/{$lang}/mon-compte/securite");
        }

        $password = $this->request->post('password', '');
        $account  = $this->userRepo->findById($userId);

        if (
            !$account
            || $account['password'] === null
            || !password_verify($password, (string) $account['password'])
        ) {
            $_SESSION['flash']['security_errors'] = ['reset_password' => __('account.wrong_current_password')];
            Response::redirect("/{$lang}/mon-compte/securite");
        }

        $this->userRepo->revokeAllSessions($userId);
        $this->trustedDevices->deleteAllForUser($userId);
        CookieHelper::clear();

        $_SESSION['flash']['info'] = __('account.security_reset_done');
        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/securite/supprimer-compte
    // ----------------------------------------------------------------

    /**
     * Supprime (soft-delete) le compte utilisateur après confirmation.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function deleteAccount(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];
        $back    = "/{$lang}/mon-compte/securite";

        if (!$this->verifyCsrf()) {
            $_SESSION['flash']['security_errors'] = ['csrf' => __('error.csrf')];
            Response::redirect($back);
        }

        $confirmText = $this->request->post('confirm_text', '');
        if ($confirmText !== 'SUPPRESSION') {
            $_SESSION['flash']['security_errors'] = ['delete' => __('account.delete_wrong_confirm_text')];
            Response::redirect($back);
        }

        $account = $this->userRepo->findById($userId);

        if (!$account) {
            $_SESSION['flash']['security_errors'] = ['delete' => __('account.delete_wrong_password')];
            Response::redirect($back);
        }

        if ($account['password'] !== null) {
            $confirmPwd = $this->request->post('confirm_password', '');
            if (!password_verify($confirmPwd, (string) $account['password'])) {
                $_SESSION['flash']['security_errors'] = ['delete' => __('account.delete_wrong_password')];
                Response::redirect($back);
            }
        }

        $this->userRepo->revokeAllSessions($userId);
        $this->userRepo->delete($userId);

        $name             = $account['firstname'] ?? $account['company_name'] ?? 'Client';
        $reactivationToken = $this->accounts->getReactivationToken($userId) ?? '';
        try {
            (new \Service\MailService())->sendAccountDeletionConfirmation(
                (string) $account['email'],
                (string) $name,
                $lang,
                $reactivationToken
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e);
        }

        CookieHelper::clear();
        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/nouvel-appareil
    // ----------------------------------------------------------------

    /**
     * Affiche la page d'attente MFA pour un nouvel appareil.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function newDevice(array $params): void
    {
        $lang    = $params['lang'];
        $pending = $_SESSION['pending_device'] ?? null;

        if (!$pending) {
            Response::redirect("/{$lang}");
        }

        $this->view('account/new_device', [
            'lang'       => $lang,
            'deviceName' => $pending['device_name'] ?? '',
            'mfaToken'   => $pending['mfa_token']   ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/appareil/confirmer  (lien email MFA)
    // ----------------------------------------------------------------

    /**
     * Confirme un nouvel appareil depuis le lien email MFA.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function confirmDevice(array $params): void
    {
        $lang  = $params['lang'];
        $token = $_GET['token'] ?? '';

        $confirmed = $this->deviceConfirmTokens->confirm($token);

        $this->view('account/device_confirmed', [
            'lang'    => $lang,
            'success' => $confirmed,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/appareil/annuler  (lien "Ce n'était pas moi")
    // ----------------------------------------------------------------

    /**
     * Annule une demande MFA en révoquant le token de confirmation.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function cancelMfa(array $params): void
    {
        $lang  = $params['lang'];
        $token = $_GET['token'] ?? '';

        $revoked = false;
        if ($token !== '') {
            $record = $this->deviceConfirmTokens->findByToken($token);
            if ($record) {
                $this->deviceConfirmTokens->deleteByToken($token);
                $revoked = true;
            }
        }

        $this->view('account/mfa_cancelled', [
            'lang'    => $lang,
            'revoked' => $revoked,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/securite/confirmer?token=xxx
    // ----------------------------------------------------------------

    /**
     * Confirme un changement de mot de passe en attente ("C'était moi").
     *
     * @param array<string, mixed> $params Paramètres de route (lang)
     * @return void
     */
    public function confirmSecurityAction(array $params): void
    {
        $lang  = $params['lang'];
        $token = $this->request->get('token', '');

        if ($token === '') {
            $this->view(self::VIEW_SECURITY_CONFIRM, ['lang' => $lang, 'success' => false, 'expired' => false]);
            return;
        }

        $hash    = hash('sha256', $token);
        $account = $this->userRepo->findBySecurityConfirmToken($hash);

        if ($account === false) {
            $this->view(self::VIEW_SECURITY_CONFIRM, ['lang' => $lang, 'success' => false, 'expired' => true]);
            return;
        }

        $userId = (int) $account['id'];

        $requestingToken = $this->accounts->getPendingSessionToken($userId); // hors interface

        $this->userRepo->applyPendingPassword($userId);
        $this->userRepo->consumeSecurityConfirmToken($userId);

        if ($requestingToken !== null && $requestingToken !== '') {
            $deviceToken = $this->connections->getDeviceTokenByToken($requestingToken);
            $this->connections->revoke($requestingToken);
            if ($deviceToken !== null) {
                $this->trustedDevices->untrust($userId, $deviceToken);
            }
        }

        $this->view(self::VIEW_SECURITY_CONFIRM, ['lang' => $lang, 'success' => true, 'expired' => false]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/securite/ce-netait-pas-moi?token=xxx
    // ----------------------------------------------------------------

    /**
     * Annule un changement de mot de passe en attente ("Ce n'était pas moi").
     *
     * @param array<string, mixed> $params Paramètres de route (lang)
     * @return void
     */
    public function denySecurityAction(array $params): void
    {
        $lang  = $params['lang'];
        $token = $this->request->get('token', '');

        if ($token === '') {
            $this->view(self::VIEW_SECURITY_DENY, ['lang' => $lang, 'success' => false, 'expired' => false]);
            return;
        }

        $hash    = hash('sha256', $token);
        $account = $this->userRepo->findBySecurityConfirmToken($hash);

        if ($account === false) {
            $this->view(self::VIEW_SECURITY_DENY, ['lang' => $lang, 'success' => false, 'expired' => true]);
            return;
        }

        $userId = (int) $account['id'];
        $this->userRepo->clearPendingPassword($userId);
        $this->userRepo->revokeAllSessions($userId);
        $this->trustedDevices->deleteAllForUser($userId);
        $this->userRepo->consumeSecurityConfirmToken($userId);

        try {
            (new \Service\MailService())->sendPasswordChangeDenied(
                (string) $account['email'],
                (string) $account['lang']
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e);
        }

        $this->view(self::VIEW_SECURITY_DENY, ['lang' => $lang, 'success' => true, 'expired' => false]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/compte/reactiver?token=xxx
    // ----------------------------------------------------------------

    /**
     * Réactive un compte supprimé via le lien de réactivation.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function reactivateAccount(array $params): void
    {
        $lang  = $params['lang'];
        $token = $this->request->get('token', '');

        if ($token === '') {
            $this->view(self::VIEW_REACTIVATE, ['lang' => $lang, 'success' => false]);
            return;
        }

        $account = $this->userRepo->findByReactivationToken($token);
        if ($account === false) {
            $this->view(self::VIEW_REACTIVATE, ['lang' => $lang, 'success' => false]);
            return;
        }

        $this->userRepo->reactivate((int) $account['id']);
        $this->view(self::VIEW_REACTIVATE, ['lang' => $lang, 'success' => true]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------
}
