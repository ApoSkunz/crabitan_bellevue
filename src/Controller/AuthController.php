<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\CookieHelper;
use Core\Jwt;
use Core\Response;
use Middleware\GuestMiddleware;
use Model\AccountModel;
use Model\PasswordResetModel;
use Model\ConnectionModel;
use Model\TrustedDeviceModel;
use Model\DeviceConfirmTokenModel;
use Service\MailService;
use Service\PasswordValidator;
use Service\RateLimiterService;

/**
 * Gère l'authentification des utilisateurs : connexion, déconnexion,
 * inscription, vérification d'email et réinitialisation de mot de passe.
 *
 * Intègre un rate limiting par IP sur les routes POST sensibles (R1/R2)
 * ainsi qu'un lockout de compte après 5 échecs consécutifs (BT2).
 */
class AuthController extends Controller
{
    /** Fenêtre de rate limiting pour la connexion et l'inscription (15 min). */
    private const WINDOW_LOGIN    = 900;

    /** Fenêtre de rate limiting pour mot-de-passe oublié (15 min). */
    private const WINDOW_FORGOT   = 900;

    /** Fenêtre de rate limiting pour l'inscription (1 heure). */
    private const WINDOW_REGISTER = 3600;

    /** Nombre maximum de tentatives de connexion par IP par fenêtre. */
    private const MAX_LOGIN_IP    = 5;

    /** Nombre maximum de demandes mot-de-passe oublié par IP par fenêtre. */
    private const MAX_FORGOT_IP   = 3;

    /** Nombre maximum d'inscriptions par IP par fenêtre. */
    private const MAX_REGISTER_IP = 5;

    /** Nombre max d'échecs consécutifs avant lockout du compte (BT2). */
    private const MAX_ACCOUNT_FAILURES = 5;

    /** Durée du lockout compte en secondes (15 min). */
    private const ACCOUNT_LOCKOUT_WINDOW = 900;

    private AccountModel $accounts;
    private PasswordResetModel $resets;
    private ConnectionModel $connections;
    private TrustedDeviceModel $trustedDevices;
    private DeviceConfirmTokenModel $deviceConfirmTokens;
    private RateLimiterService $rateLimiter;

    /**
     * Initialise les dépendances du contrôleur.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts            = new AccountModel();
        $this->resets              = new PasswordResetModel();
        $this->connections         = new ConnectionModel();
        $this->deviceConfirmTokens = new DeviceConfirmTokenModel();
        $this->trustedDevices      = new TrustedDeviceModel();
        $this->rateLimiter         = new RateLimiterService();
    }

    // ----------------------------------------------------------------
    // POST /{lang}/connexion
    // ----------------------------------------------------------------

    /**
     * Authentifie un utilisateur et pose le cookie JWT.
     *
     * Applique deux niveaux de rate limiting :
     *   - R1 : max 5 tentatives par IP par 15 minutes
     *   - BT2 : lockout du compte après 5 échecs consécutifs (15 min)
     *
     * Si la case "Se souvenir de moi" est cochée, le JWT et le cookie
     * ont une durée de vie de 30 jours ; sinon, le cookie expire après 24 h
     * (86 400 s) et le JWT conserve son TTL court défini par JWT_EXPIRY.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function login(array $params): void
    {
        GuestMiddleware::handle();
        $lang = $params['lang'];

        $rawBack   = $this->request->post('redirect_back', '');
        $validBack = preg_match('#^/[^/]#', $rawBack) && !str_contains($rawBack, '://');
        $safeBack  = $validBack ? $rawBack : "/{$lang}";

        if (!$this->verifyCsrf()) {
            $this->flash('modal_error', __('error.csrf'));
            Response::redirect($safeBack);
        }

        // R1 — Rate limiting par IP
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ipKey   = 'login:' . $ip;
        if (!$this->rateLimiter->checkLimit($ipKey, self::MAX_LOGIN_IP, self::WINDOW_LOGIN)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($ipKey) / 60);
            $this->flash('modal_error', sprintf(__('auth.too_many_attempts'), max(1, $wait)));
            Response::redirect($safeBack);
        }

        $email    = strtolower(trim($this->request->post('email', '')));
        $password = $this->request->post('password', '');
        $account  = $this->accounts->findByEmail($email);

        // BT2 — Compte déjà verrouillé (tentatives 6+) : bloquer sans email (déjà envoyé à la 5ème)
        if ($account) {
            $accountKey = 'account_lockout:' . (int) $account['id'];
            if (!$this->rateLimiter->checkLimit($accountKey, self::MAX_ACCOUNT_FAILURES, self::ACCOUNT_LOCKOUT_WINDOW)) {
                $wait = (int) ceil($this->rateLimiter->getRetryAfter($accountKey) / 60);
                $this->rateLimiter->recordAttempt($ipKey, self::WINDOW_LOGIN);
                $this->flash('modal_error', sprintf(__('auth.account_locked'), max(1, $wait)));
                Response::redirect($safeBack);
            }
        }

        if (!$account || $account['password'] === null || !password_verify($password, $account['password'])) {
            $this->recordLoginFailure($ipKey, $account);

            // BT2 — Détecter si ce dernier échec vient de déclencher le lockout (5ème tentative)
            if ($account) {
                $accountKey = 'account_lockout:' . (int) $account['id'];
                if (!$this->rateLimiter->checkLimit($accountKey, self::MAX_ACCOUNT_FAILURES, self::ACCOUNT_LOCKOUT_WINDOW)) {
                    $wait        = (int) ceil($this->rateLimiter->getRetryAfter($accountKey) / 60);
                    $displayName = $account['account_type'] === 'company'
                        ? ($account['company_name'] ?? '')
                        : trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));
                    $resetUrl = APP_URL . "/{$lang}/mot-de-passe-oublie";
                    try {
                        $mail = new MailService();
                        $mail->sendAccountLocked($account['email'], $displayName, $lang, $resetUrl);
                    } catch (\Throwable $e) {
                        error_log('Mail account locked error: ' . $e->getMessage()); // NOSONAR php:S4792 — log d'erreur non sensible
                    }
                    $this->flash('modal_error', sprintf(__('auth.account_locked'), max(1, $wait)));
                    Response::redirect($safeBack);
                }
            }

            $this->flash('modal_error', __('auth.invalid_credentials'));
            Response::redirect($safeBack);
        }

        if (!$account['email_verified_at']) {
            $this->rateLimiter->recordAttempt($ipKey, self::WINDOW_LOGIN);
            $this->flash('modal_error', __('auth.account_inactive'));
            Response::redirect($safeBack);
        }

        // Connexion réussie — réinitialiser les compteurs
        $this->rateLimiter->reset($ipKey);
        $this->rateLimiter->reset('account_lockout:' . (int) $account['id']);

        $deviceToken = $this->resolveDeviceToken();

        $ua               = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $deviceName       = $this->deriveDeviceName($ua);
        $isFirstEverLogin = !(bool) ($account['has_connected'] ?? false);
        $isAlreadyTrusted = $this->trustedDevices->isTrusted((int) $account['id'], $deviceToken);
        $trusted          = $isFirstEverLogin || $isAlreadyTrusted;

        // MFA requis : appareil non de confiance, qu'il soit connu ou non.
        // Le JWT n'est PAS émis ici — la connexion est bloquée jusqu'à validation du lien email.
        if (!$trusted) {
            $this->handleUntrustedDevice($account, $deviceToken, $deviceName, $lang, $safeBack);
        }

        // Appareil connu ou de confiance : émission du JWT et connexion immédiate.
        $rememberMe   = $this->request->post('remember_me', '') === '1';
        $jwtExpiry    = $rememberMe ? (30 * 24 * 3600) : (int) ($_ENV['JWT_EXPIRY'] ?? 3600);
        $cookieExpiry = $rememberMe ? $jwtExpiry : 86400;
        $token        = Jwt::generate((int) $account['id'], $account['role'], $jwtExpiry);

        CookieHelper::set($token, $cookieExpiry);

        if ($account['lang'] !== $lang) {
            $this->accounts->updateLang((int) $account['id'], $lang);
        }

        $this->connections->create(
            (int) $account['id'],
            $token,
            $deviceToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $ua,
            $deviceName,
            'password',
            $jwtExpiry
        );

        $this->updateDeviceTrust((int) $account['id'], $deviceToken, $deviceName, $isFirstEverLogin, $isAlreadyTrusted);

        Response::redirect($safeBack);
    }

    // ----------------------------------------------------------------
    // GET  /{lang}/deconnexion → 405 (protection CSRF : aucun logout sur GET)
    // POST /{lang}/deconnexion → logout avec vérification CSRF
    // ----------------------------------------------------------------

    /**
     * Déconnecte l'utilisateur : révoque le JWT et efface le cookie.
     *
     * La déconnexion n'est autorisée que via POST avec un token CSRF valide.
     * Un GET retourne 405 Method Not Allowed afin d'empêcher les attaques
     * CSRF par image forgée ou lien tiers (`<img src="/fr/deconnexion">`).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     * @throws \Core\Exception\HttpException 405 si GET et utilisateur connecté, 404 si GET et non connecté
     */
    public function logout(array $params): void
    {
        // Refuser toute méthode autre que POST (protection CSRF passive)
        // Connecté → 405 (sait que la route existe via le header)
        // Non connecté → 404 (ne révèle pas l'existence de la route)
        if ($this->request->method !== 'POST') {
            $token = $_COOKIE['auth_token'] ?? null;
            Response::abort($token ? 405 : 404);
        }

        // Vérifier le token CSRF avant toute action de déconnexion
        if (!$this->verifyCsrf()) {
            Response::redirect('/' . $params['lang']);
        }

        $token = $_COOKIE['auth_token'] ?? null;

        if ($token) {
            $this->connections->revoke($token);
            CookieHelper::clear();
        }

        Response::redirect('/' . $params['lang']);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/inscription
    // ----------------------------------------------------------------

    /**
     * Crée un nouveau compte utilisateur et envoie l'email de vérification.
     *
     * Applique un rate limiting R2 : max 5 inscriptions par IP par heure.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function register(array $params): void
    {
        GuestMiddleware::handle();
        $lang = $params['lang'];

        if (!$this->verifyCsrf()) {
            $this->flash('modal_error', __('error.csrf'));
            Response::redirect("/{$lang}");
        }

        // R2 — Rate limiting inscription par IP
        $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $registerKey = 'register:' . $ip;
        if (!$this->rateLimiter->checkLimit($registerKey, self::MAX_REGISTER_IP, self::WINDOW_REGISTER)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($registerKey) / 60);
            $this->flash('modal_error', sprintf(__('auth.too_many_registrations'), max(1, $wait)));
            Response::redirect("/{$lang}");
        }

        $accountType = $this->request->post('account_type', '');
        $email       = strtolower(trim($this->request->post('email', '')));
        $password    = $this->request->post('password', '');
        $confirm     = $this->request->post('password_confirm', '');
        $civility    = $this->request->post('civility', '');
        $lastname    = trim($this->request->post('lastname', ''));
        $firstname   = trim($this->request->post('firstname', ''));
        $company     = trim($this->request->post('company_name', ''));
        $newsletter  = $this->request->post('newsletter', '0') === '1' ? 1 : 0;

        $errors = $this->validateRegister(
            $accountType,
            $email,
            $password,
            $confirm,
            $civility,
            $lastname,
            $firstname,
            $company
        );
        $old = compact('accountType', 'civility', 'lastname', 'firstname', 'email', 'company', 'newsletter');

        if ($errors) {
            $_SESSION['flash']['register_errors'] = $errors;
            $_SESSION['flash']['register_old']    = $old;
            Response::redirect("/{$lang}");
        }

        // Anti-énumération (R5) : si l'email est déjà pris, on affiche le même message de
        // confirmation générique qu'une inscription réussie.
        // Critère 2 : un email informatif est envoyé au titulaire du compte.
        $existingAccount = $this->accounts->findByEmail($email);
        if ($existingAccount) {
            $displayName = $existingAccount['account_type'] === 'company'
                ? ($existingAccount['company_name'] ?? '')
                : trim(($existingAccount['firstname'] ?? '') . ' ' . ($existingAccount['lastname'] ?? ''));
            $loginUrl = APP_URL . "/{$lang}?login=1";
            $resetUrl = APP_URL . "/{$lang}/mot-de-passe-oublie";
            try {
                $mail = new MailService();
                $mail->sendEmailAlreadyExists($email, $displayName, $lang, $loginUrl, $resetUrl);
            } catch (\Throwable $e) {
                error_log('Mail duplicate register error: ' . $e->getMessage()); // NOSONAR php:S4792 — log d'erreur non sensible
            }
            $this->flash('register_success', '1');
            Response::redirect("/{$lang}");
        }

        // Enregistrer la tentative d'inscription (après validation pour ne pas comptabiliser les bots)
        $this->rateLimiter->recordAttempt($registerKey, self::WINDOW_REGISTER);

        $verificationToken = bin2hex(random_bytes(32));
        $displayName       = $accountType === 'company' ? $company : "{$firstname} {$lastname}";

        $this->accounts->create(
            $accountType,
            $email,
            password_hash($password, PASSWORD_BCRYPT),
            $lang,
            $newsletter,
            $verificationToken,
            $civility,
            $lastname,
            $firstname,
            $company
        );

        $verifyUrl = APP_URL . "/{$lang}/verification/{$verificationToken}";

        try {
            $mail = new MailService();
            $mail->sendEmailVerification($email, $displayName, $verifyUrl, $lang);
        } catch (\Throwable $e) {
            error_log('Mail verification error: ' . $e->getMessage()); // NOSONAR php:S4792 — log d'erreur non sensible
        }

        $this->flash('register_success', '1');
        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/verification/{token}
    // ----------------------------------------------------------------

    /**
     * Vérifie l'email via le token de confirmation.
     *
     * @param array<string, string> $params Paramètres de route (lang, token)
     * @return void
     */
    public function verifyEmail(array $params): void
    {
        $lang    = $params['lang'];
        $token   = $params['token'] ?? '';
        $account = $this->accounts->findByVerificationToken($token);

        if (!$account) {
            $this->view('auth/verify', [
                'lang'    => $lang,
                'success' => false,
                'message' => __('auth.verify_invalid'),
            ]);
            return;
        }

        if ($account['email_verified_at']) {
            $this->flash('info', __('auth.already_verified'));
            Response::redirect("/{$lang}");
        }

        $this->accounts->verifyEmail((int) $account['id']);

        if (!empty($account['newsletter_optin_pending'])) {
            $this->accounts->activateNewsletterFromPending((int) $account['id']);
        }

        $this->view('auth/verify', [
            'lang'    => $lang,
            'success' => true,
            'message' => __('auth.verify_success'),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mot-de-passe-oublie
    // ----------------------------------------------------------------

    /**
     * Affiche le formulaire mot-de-passe oublié (redirige vers la page d'accueil).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function forgotForm(array $params): void
    {
        GuestMiddleware::handle();
        Response::redirect('/' . $params['lang']);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mot-de-passe-oublie
    // ----------------------------------------------------------------

    /**
     * Traite la demande de réinitialisation de mot de passe.
     *
     * Applique un rate limiting R1 : max 3 demandes par IP par 15 minutes.
     * Affiche toujours un message de succès (anti-énumération d'email).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function forgot(array $params): void
    {
        GuestMiddleware::handle();
        $lang  = $params['lang'];

        if (!$this->verifyCsrf()) {
            Response::redirect("/{$lang}/mot-de-passe-oublie");
        }

        // R1 — Rate limiting mot-de-passe oublié par IP
        $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $forgotKey = 'forgot:' . $ip;
        if (!$this->rateLimiter->checkLimit($forgotKey, self::MAX_FORGOT_IP, self::WINDOW_FORGOT)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($forgotKey) / 60);
            $this->flash('modal_error', sprintf(__('auth.too_many_reset_requests'), max(1, $wait)));
            Response::redirect("/{$lang}");
        }

        $this->rateLimiter->recordAttempt($forgotKey, self::WINDOW_FORGOT);

        $email   = strtolower(trim($this->request->post('email', '')));
        $account = $this->accounts->findByEmail($email);

        // Toujours afficher le succès (anti-énumération)
        $this->flash('forgot_success', '1');

        if ($account && $account['email_verified_at'] && $account['role'] === 'customer') {
            $displayName = $account['account_type'] === 'company'
                ? ($account['company_name'] ?? 'Client')
                : (($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));

            try {
                $mail = new MailService();

                if ($account['password'] === null) {
                    // Compte Google-only : pas de mot de passe, informer l'utilisateur
                    $mail->sendGoogleAccountInfo($account['email'], $displayName, $lang);
                } else {
                    $token    = bin2hex(random_bytes(32));
                    $this->resets->create((int) $account['id'], $token);
                    $resetUrl = APP_URL . "/{$lang}/reinitialisation/{$token}";
                    $mail->sendPasswordReset($account['email'], $displayName, $resetUrl, $lang);
                }
            } catch (\Throwable $e) {
                error_log('Mail reset error: ' . $e->getMessage()); // NOSONAR php:S4792 — log d'erreur non sensible
            }
        }

        Response::redirect("/{$lang}");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/reinitialisation/{token}
    // ----------------------------------------------------------------

    /**
     * Affiche le formulaire de réinitialisation de mot de passe.
     *
     * @param array<string, string> $params Paramètres de route (lang, token)
     * @return void
     */
    public function resetForm(array $params): void
    {
        $lang  = $params['lang'];
        $token = $params['token'] ?? '';
        $reset = $this->resets->findByToken($token);

        $_SESSION['reset_modal'] = [
            'token' => $token,
            'valid' => (bool) $reset,
            'error' => null,
        ];

        Response::redirect("/{$lang}?modal=reset");
    }

    // ----------------------------------------------------------------
    // POST /{lang}/reinitialisation/{token}
    // ----------------------------------------------------------------

    /**
     * Traite la réinitialisation du mot de passe.
     *
     * @param array<string, string> $params Paramètres de route (lang, token)
     * @return void
     */
    public function reset(array $params): void
    {
        $lang  = $params['lang'];
        $token = $params['token'] ?? '';

        if (!$this->verifyCsrf()) {
            Response::redirect("/{$lang}/reinitialisation/{$token}");
        }

        $reset = $this->resets->findByToken($token);

        if (!$reset) {
            $_SESSION['reset_modal'] = ['token' => $token, 'valid' => false, 'error' => null];
            Response::redirect("/{$lang}?modal=reset");
        }

        $password = $this->request->post('password', '');
        $confirm  = $this->request->post('password_confirm', '');

        $pwErrors = PasswordValidator::getErrors($password);
        if ($pwErrors !== []) {
            $errorMessages = implode(' ', array_map('__', $pwErrors));
            $_SESSION['reset_modal'] = ['token' => $token, 'valid' => true, 'error' => $errorMessages];
            Response::redirect("/{$lang}?modal=reset");
        }

        if ($password !== $confirm) {
            $_SESSION['reset_modal'] = ['token' => $token, 'valid' => true, 'error' => __('validation.password_match')];
            Response::redirect("/{$lang}?modal=reset");
        }

        $this->accounts->updatePassword((int) $reset['user_id'], password_hash($password, PASSWORD_BCRYPT));
        $this->resets->deleteByUserId((int) $reset['user_id']);

        unset($_SESSION['reset_modal']);
        $this->flash('info', __('auth.password_updated'));
        Response::redirect("/{$lang}?login=1");
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Valide les champs du formulaire d'inscription.
     *
     * @param string $accountType  Type de compte (individual|company)
     * @param string $email        Adresse email
     * @param string $password     Mot de passe
     * @param string $confirm      Confirmation du mot de passe
     * @param string $civility     Civilité (M|F|other)
     * @param string $lastname     Nom de famille
     * @param string $firstname    Prénom
     * @param string $company      Nom de l'entreprise
     * @return array<string, string> Tableau d'erreurs indexé par champ (vide si valide)
     */
    private function validateRegister(
        string $accountType,
        string $email,
        string $password,
        string $confirm,
        string $civility,
        string $lastname,
        string $firstname,
        string $company
    ): array {
        $errors = [];

        if (!in_array($accountType, ['individual', 'company'], true)) {
            $errors['account_type'] = __('validation.required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = __('validation.email');
        }
        $pwErrors = PasswordValidator::getErrors($password);
        if ($pwErrors !== []) {
            $errors['password'] = implode(' ', array_map('__', $pwErrors));
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = __('validation.password_match');
        }
        if ($accountType === 'individual') {
            if (strlen($lastname) < 2) {
                $errors['lastname'] = __('validation.required');
            }
            if (strlen($firstname) < 2) {
                $errors['firstname'] = __('validation.required');
            }
            if (!in_array($civility, ['M', 'F', 'other'], true)) {
                $errors['civility'] = __('validation.required');
            }
        }
        if ($accountType === 'company' && strlen($company) < 2) {
            $errors['company_name'] = __('validation.required');
        }

        return $errors;
    }

    /**
     * Dérive un nom lisible depuis la chaîne User-Agent.
     *
     * @param string $ua Chaîne User-Agent
     * @return string Nom du navigateur et du système (ex. "Chrome · Windows")
     */
    private function deriveDeviceName(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg')                                        => 'Edge',
            str_contains($ua, 'Chrome') && !str_contains($ua, 'Chromium')  => 'Chrome',
            str_contains($ua, 'Firefox')                                    => 'Firefox',
            str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')    => 'Safari',
            default                                                         => 'Browser',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android')                              => 'Android',
            str_contains($ua, 'Windows')                              => 'Windows',
            str_contains($ua, 'Macintosh')                            => 'macOS',
            str_contains($ua, 'Linux')                                => 'Linux',
            default                                                   => 'Unknown',
        };
        return "{$browser} · {$os}";
    }

    /**
     * Vérifie la validité du token CSRF.
     *
     * @return bool True si le token est valide
     */
    private function verifyCsrf(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    /**
     * Ajoute un message flash en session.
     *
     * @param string $key     Clé du message (ex. "modal_error", "info")
     * @param string $message Contenu du message
     * @return void
     */
    private function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    /**
     * Retourne le device token existant depuis le cookie, ou en génère un nouveau.
     *
     * @return string Token de 64 caractères hexadécimaux
     */
    private function resolveDeviceToken(): string
    {
        $token = $_COOKIE['device_token'] ?? null;
        if ($token !== null) {
            return $token;
        }
        $token = bin2hex(random_bytes(32));
        setcookie('device_token', $token, [
            'expires'  => time() + (90 * 24 * 3600),
            'path'     => '/',
            'secure'   => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return $token;
    }

    /**
     * Enregistre un échec de connexion sur le bucket IP et, si le compte existe, sur le bucket compte.
     *
     * @param string       $ipKey   Clé de rate limiting de l'IP
     * @param array|false  $account Données du compte (false si inconnu)
     * @return void
     */
    private function recordLoginFailure(string $ipKey, array|false $account): void
    {
        $this->rateLimiter->recordAttempt($ipKey, self::WINDOW_LOGIN);
        if ($account) {
            $accountKey = 'account_lockout:' . (int) $account['id'];
            $this->rateLimiter->recordAttempt($accountKey, self::ACCOUNT_LOCKOUT_WINDOW);
        }
    }

    /**
     * Met à jour la confiance de l'appareil après une connexion réussie.
     *
     * @param int    $accountId         Identifiant du compte
     * @param string $deviceToken       Token de l'appareil
     * @param string $deviceName        Nom lisible de l'appareil
     * @param bool   $isFirstEverLogin  Première connexion du compte
     * @param bool   $isAlreadyTrusted  Appareil déjà de confiance
     * @return void
     */
    private function updateDeviceTrust(
        int $accountId,
        string $deviceToken,
        string $deviceName,
        bool $isFirstEverLogin,
        bool $isAlreadyTrusted
    ): void {
        if ($isFirstEverLogin) {
            $this->accounts->markAsConnected($accountId);
            $this->trustedDevices->trust($accountId, $deviceToken, $deviceName);
        } elseif ($isAlreadyTrusted) {
            $this->trustedDevices->updateLastSeen($accountId, $deviceToken);
        }
    }

    /**
     * Crée un token de confirmation appareil, envoie l'email MFA et redirige.
     *
     * @param array<string, mixed> $account     Données du compte utilisateur
     * @param string               $deviceToken Token de l'appareil
     * @param string               $deviceName  Nom lisible de l'appareil
     * @param string               $lang        Langue courante
     * @param string               $safeBack    URL de redirection après confirmation
     * @return void
     */
    private function handleUntrustedDevice(
        array $account,
        string $deviceToken,
        string $deviceName,
        string $lang,
        string $safeBack
    ): void {
        $confirmToken = bin2hex(random_bytes(32));
        $this->deviceConfirmTokens->create(
            (int) $account['id'],
            $deviceToken,
            $deviceName,
            $confirmToken,
            $safeBack,
            $lang
        );

        try {
            $displayName = $account['account_type'] === 'company'
                ? ($account['company_name'] ?? 'Client')
                : (trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? '')));
            $mail = new MailService();
            $mail->sendNewDeviceAlert(
                $account['email'],
                $displayName,
                $deviceName,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $lang,
                $confirmToken
            );
        } catch (\Throwable $e) {
            error_log('Mail new device alert error: ' . $e->getMessage()); // NOSONAR php:S4792 — log d'erreur non sensible
        }

        $_SESSION['pending_device'] = [
            'device_name' => $deviceName,
            'mfa_token'   => $confirmToken,
        ];
        Response::redirect("/{$lang}/mon-compte/nouvel-appareil");
    }
}
