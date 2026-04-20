<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\CookieHelper;
use Core\DeviceHelper;
use Core\Jwt;
use Core\Response;
use Middleware\GuestMiddleware;
use Model\AccountModel;
use Model\PasswordResetModel;
use Model\ConnectionModel;
use Model\TrustedDeviceModel;
use Model\DeviceConfirmTokenModel;
use Service\LoginService;
use Service\MailService;
use Service\PasswordValidator;
use Service\RateLimiterService;
use Service\SecurityAlertService;
use ValueObject\Email;

/**
 * Gère l'authentification des utilisateurs : connexion, déconnexion,
 * inscription, vérification d'email et réinitialisation de mot de passe.
 *
 * Intègre un rate limiting par IP sur les routes POST sensibles (R1/R2)
 * ainsi qu'un lockout de compte après 5 échecs consécutifs (BT2).
 */
class AuthController extends Controller // NOSONAR — php:S1448 — controller auth multi-flux nécessite >20 méthodes
{
    use DeviceHelper;

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

    /** Nombre maximum de renvois de token de vérification par compte par fenêtre. */
    private const MAX_RESEND_VERIF = 3;

    /** Budget email combiné par IP (forgot + resend activation) sur 15 min. */
    private const MAX_EMAIL_IP = 5;

    /** Fenêtre du budget email combiné par IP (15 min). */
    private const WINDOW_EMAIL_IP = 900;

    private AccountModel $accounts;
    private PasswordResetModel $resets;
    private ConnectionModel $connections;
    private TrustedDeviceModel $trustedDevices;
    private DeviceConfirmTokenModel $deviceConfirmTokens;
    private RateLimiterService $rateLimiter;
    private SecurityAlertService $securityAlert;
    private LoginService $loginService;

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
        $this->securityAlert       = new SecurityAlertService();
        $this->loginService        = new LoginService($this->accounts, $this->rateLimiter, $this->securityAlert);
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
    public function login(array $params): void // NOSONAR — php:S3776 — complexité cognitive inhérente au flux d'authentification multi-cas
    {
        GuestMiddleware::handle();
        $lang = $params['lang'] ?? 'fr';

        $rawBack   = $this->request->post('redirect_back', '');
        // VULN-009 : validation stricte — rejeter //, null-byte, javascript:, data:, vbscript:
        $validBack = $rawBack !== ''
            && preg_match('#^/[a-zA-Z0-9\-_/]#', $rawBack)
            && !str_contains($rawBack, '://')
            && !str_starts_with($rawBack, '//')
            && !str_contains($rawBack, "\0");
        $safeBack  = $validBack ? $rawBack : "/{$lang}";

        if (!$this->verifyCsrf()) {
            $this->flash('modal_error', __('error.csrf'));
            Response::redirect($safeBack);
        }

        // R1 — Rate limiting par IP
        $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ipKey = 'login:' . $ip;
        if (!$this->rateLimiter->checkLimit($ipKey, self::MAX_LOGIN_IP, self::WINDOW_LOGIN)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($ipKey) / 60);
            $this->flash('modal_error', sprintf(__('auth.too_many_attempts'), max(1, $wait)));
            Response::redirect($safeBack);
        }

        $email    = strtolower(trim($this->request->post('email', '')));
        $password = (string) $this->request->post('password', '');

        $attempt = $this->loginService->attempt($email, $password, $ipKey, $lang, $ip);

        match ($attempt['result']) {
            'locked'          => $this->handleLockedResult($attempt['wait'], $safeBack),
            'deleted'         => $this->handleDeletedResult($safeBack),
            'bad_credentials' => $this->handleBadCredentialsResult($attempt, $safeBack),
            'unverified'      => $this->handleUnverifiedResult($safeBack),
            default           => null,
        };

        /** @var array<string, mixed> $account */
        $account = $attempt['account'];

        // Connexion réussie — réinitialiser les compteurs
        $this->loginService->resetCounters($ipKey, $account);

        $deviceToken = $this->resolveDeviceToken();

        $ua               = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $deviceName       = $this->deriveDeviceName($ua);
        $isFirstEverLogin = !(bool) ($account['has_connected'] ?? false);
        $isAlreadyTrusted = $this->trustedDevices->isTrusted((int) $account['id'], $deviceToken);
        $trusted          = $isFirstEverLogin || $isAlreadyTrusted;

        // MFA requis : appareil non de confiance, qu'il soit connu ou non.
        // Le JWT n'est PAS émis ici — la connexion est bloquée jusqu'à validation du lien email.
        // MFA_EMAIL_ENABLED=false : auto-trust temporaire (limite SMTP IONOS < 30 jours).
        if (!$trusted) {
            $mfaEnabled = filter_var($_ENV['MFA_EMAIL_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
            if ($mfaEnabled) {
                $this->handleUntrustedDevice($account, $deviceToken, $deviceName, $lang, $safeBack);
            } else {
                \Core\ErrorLogger::warning(
                    new \RuntimeException('[MFA_DISABLED] Auto-trust device — MFA_EMAIL_ENABLED=false'),
                    '[MFA_DISABLED]'
                );
                $this->trustedDevices->trust((int) $account['id'], $deviceToken, $deviceName);
            }
        }

        // Appareil connu ou de confiance : émission du JWT et connexion immédiate.
        $rememberMe   = $this->request->post('remember_me', '') === '1';
        $jwtExpiry    = $rememberMe ? (30 * 24 * 3600) : (int) ($_ENV['JWT_EXPIRY'] ?? 7200);
        $cookieExpiry = $rememberMe ? $jwtExpiry : 86400;
        $token        = Jwt::generate((int) $account['id'], $account['role'], $jwtExpiry);

        session_regenerate_id(true); // VULN-005 : protection session fixation après connexion réussie
        CookieHelper::set($token, $cookieExpiry);

        // Respecte la langue préférée enregistrée — ne pas l'écraser avec la lang de l'URL.
        $accountLang = in_array((string) ($account['lang'] ?? ''), ['fr', 'en'], true)
            ? (string) $account['lang']
            : $lang;

        // Cookie cb-lang : rend la préférence de langue disponible côté PHP (config.php)
        // sans décodage JWT — utilisé comme fallback sur DEFAULT_LANG (langue navigateur).
        setcookie('cb-lang', $accountLang, [
            'expires'  => $cookieExpiry > 0 ? time() + $cookieExpiry : 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

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

        $this->accounts->resetInactivityWarning((int) $account['id']);
        $this->updateDeviceTrust((int) $account['id'], $deviceToken, $deviceName, $isFirstEverLogin, $isAlreadyTrusted);

        // Fusion panier cookie → BDD après connexion réussie
        $loginCartModel = new \Model\CartModel();
        $localCartJson  = $_COOKIE['cb-cart'] ?? '';
        if ($localCartJson !== '') {
            $localItems = json_decode($localCartJson, true) ?? [];
            if (!empty($localItems)) {
                $wineModel = new \Model\WineModel();
                $loginCartModel->mergeLocalCart(
                    (int) $account['id'],
                    $localItems,
                    static function (int $wineId) use ($wineModel): int {
                        $wine = $wineModel->getById($wineId);
                        if ($wine === null || !(bool) $wine['available']) {
                            return 0; // vin indisponible — item supprimé
                        }
                        $stock = (int) ($wine['stock_quantity'] ?? 0);
                        // Fallback 600 si stock non encore configuré (default 0)
                        return $stock > 0 ? $stock : 600;
                    }
                );
                // Effacer le cookie côté serveur (secure=false pour compatibilité HTTP dev)
                setcookie('cb-cart', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => false, 'httponly' => false, 'samesite' => 'Lax']); // phpcs:ignore Generic.Files.LineLength
            }
        }

        // Cookie badge count — évite le flash à 0 au chargement de page suivant
        $loginCart   = $loginCartModel->findByUserId((int) $account['id']);
        $loginCount  = $loginCart !== false ? (int) ($loginCart['total_quantity'] ?? 0) : 0;
        setcookie('cb-cart-count', (string) $loginCount, ['expires' => time() + 7 * 24 * 3600, 'path' => '/', 'secure' => APP_ENV === 'production', 'httponly' => false, 'samesite' => 'Lax']); // phpcs:ignore Generic.Files.LineLength

        // Déclaration de majorité — sync session avec l'état BDD
        if (!$this->accounts->hasDeclaredMajority((int) $account['id'])) {
            $_SESSION['majority_pending'] = true;
        } else {
            unset($_SESSION['majority_pending']);
        }

        // _intended — URL mémorisée par AuthMiddleware lors d'un accès à une page protégée sans auth.
        // Priorité : _intended (navigation directe) > redirect_back (modal header) > accueil.
        $intended = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);
        $validIntended = $intended !== null
            && is_string($intended)
            && preg_match('#^/[^/]#', $intended)
            && !str_contains($intended, '://');
        // À la connexion : rediriger vers la langue préférée du compte (une seule fois)
        $redirectTarget = $validIntended ? $intended : $safeBack;
        if ($accountLang !== $lang && preg_match('#^/(fr|en)(/.+)?$#', $redirectTarget, $m)) {
            $redirectTarget = '/' . $accountLang . ($m[2] ?? '');
        }
        Response::redirect($redirectTarget);
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

        // Effacer la préférence de langue mémorisée (cookie cb-lang)
        setcookie('cb-lang', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']); // phpcs:ignore Generic.Files.LineLength

        // Nettoyer _intended pour éviter toute redirection résiduelle après reconnexion
        unset($_SESSION['_intended']);

        Response::redirect('/' . $params['lang']);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/session-expiree
    // ----------------------------------------------------------------

    /**
     * Pose un flash "session expirée" et redirige vers l'accueil.
     * Appelé par le polling BroadcastChannel côté client après détection de révocation.
     *
     * @param  array<string, mixed> $params Paramètres de route (lang)
     * @return never
     */
    public function sessionExpired(array $params): never
    {
        $_SESSION['flash']['info'] = __('auth.session_expired');
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
        $lang = $params['lang'] ?? 'fr';

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
        $rawEmail    = $this->request->post('email', '');
        try {
            $email = (new Email($rawEmail))->getValue();
        } catch (\InvalidArgumentException) {
            $email = strtolower(trim($rawEmail)); // invalide — validateRegister() retournera l'erreur
        }
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

            try {
                $mail = new MailService();
                if (empty($existingAccount['email_verified_at'])) {
                    // Compte créé mais pas encore activé (double-submit, premier mail perdu, relance) :
                    // on rafraîchit le token et on renvoie le mail de vérification — pas le mail "tentative".
                    $newToken = bin2hex(random_bytes(32));
                    $this->accounts->refreshVerificationToken((int) $existingAccount['id'], $newToken);
                    $verifyUrl = APP_URL . "/{$lang}/verification/{$newToken}";
                    $mail->sendEmailVerification($email, $displayName, $verifyUrl, $lang);
                } else {
                    $loginUrl = APP_URL . "/{$lang}?login=1";
                    $resetUrl = APP_URL . "/{$lang}/mot-de-passe-oublie";
                    $mail->sendEmailAlreadyExists($email, $displayName, $lang, $loginUrl, $resetUrl);
                }
            } catch (\Throwable $e) {
                \Core\ErrorLogger::warning($e);
            }
            // Normalisation du temps de réponse (anti timing oracle R14/R15) :
            // le chemin nominal exécute password_hash() en ligne ~403 ; on le simule ici
            // pour que le fast-path "email déjà pris" ait le même temps de réponse.
            password_hash('dummy_timing_normalization', PASSWORD_BCRYPT); // NOSONAR php:S2053 — intentionnel : normalisation timing
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

        // Dédoublonnage newsletter : à la création de compte, toujours supprimer
        // l'entrée orpheline dans newsletter_subscriptions (visiteur sans compte).
        // L'email appartient désormais au compte — la préférence newsletter
        // est gérée par accounts.newsletter, pas par newsletter_subscriptions.
        $this->accounts->deleteNewsletterSubscriptionByEmail($email);

        $verifyUrl = APP_URL . "/{$lang}/verification/{$verificationToken}";

        try {
            $mail = new MailService();
            $mail->sendEmailVerification($email, $displayName, $verifyUrl, $lang);
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e);
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

        $accountId = (int) $account['id'];
        $this->accounts->verifyEmail($accountId);

        if (!empty($account['newsletter_optin_pending'])) {
            $this->accounts->activateNewsletterFromPending($accountId);
        }

        if (!$this->accounts->hasDeclaredMajority($accountId)) {
            $_SESSION['majority_pending'] = true;
        }

        try {
            $firstname    = $account['firstname'] ?? '';
            $isSubscribed = (int) ($account['newsletter'] ?? 0) === 1
                         || !empty($account['newsletter_optin_pending']);
            $confirmToken = '';
            if (!$isSubscribed) {
                $confirmToken = bin2hex(random_bytes(32));
                $this->accounts->storeNewsletterConfirmToken($accountId, $confirmToken);
            }
            (new MailService())->sendWelcome(
                $account['email'],
                $firstname,
                $lang,
                $isSubscribed,
                $confirmToken
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e);
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
        $emailIpKey = 'email_ip:' . $ip;
        if (!$this->rateLimiter->checkLimit($emailIpKey, self::MAX_EMAIL_IP, self::WINDOW_EMAIL_IP)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($emailIpKey) / 60);
            $this->flash('modal_error', sprintf(__('auth.too_many_reset_requests'), max(1, $wait)));
            Response::redirect("/{$lang}");
        }

        $forgotKey = 'forgot:' . $ip;
        if (!$this->rateLimiter->checkLimit($forgotKey, self::MAX_FORGOT_IP, self::WINDOW_FORGOT)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($forgotKey) / 60);
            $this->flash('modal_error', sprintf(__('auth.too_many_reset_requests'), max(1, $wait)));
            Response::redirect("/{$lang}");
        }

        $this->rateLimiter->recordAttempt($emailIpKey, self::WINDOW_EMAIL_IP);
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
                \Core\ErrorLogger::warning($e);
            }
        } else {
            // Normalisation du temps de réponse (anti timing oracle R14/R15) :
            // le fast-path (email inconnu ou non éligible) est artificiellement ralenti
            // pour correspondre au temps du chemin nominal qui exécute password_hash().
            password_hash('dummy_timing_normalization', PASSWORD_BCRYPT); // NOSONAR php:S2053 — intentionnel : normalisation timing
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
    // POST /{lang}/renvoyer-activation
    // ----------------------------------------------------------------

    /**
     * Renvoi explicite du lien d'activation pour un compte non vérifié.
     *
     * Anti-énumération : la réponse est identique quel que soit l'état de l'email
     * (inconnu, déjà vérifié, ou compte valide en attente) — empêche de déterminer
     * si une adresse est enregistrée ou active sur la plateforme.
     *
     * Rate limiting : max 3 envois par IP par 15 min + max 3 par userId par 15 min.
     *
     * @param array<string, string> $params Paramètres de route (contient 'lang')
     * @return void
     */
    public function resendActivation(array $params): void
    {
        $lang = $params['lang'];
        $back = "/{$lang}";

        if (!$this->verifyCsrf()) {
            $this->flash('resend_activation_success', '1');
            Response::redirect($back);
        }

        $email      = trim($this->request->post('email', ''));
        $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $emailIpKey = 'email_ip:' . $ip;

        // Budget email combiné par IP (forgot + resend) — 3 mails / 15 min
        if (!$this->rateLimiter->checkLimit($emailIpKey, self::MAX_EMAIL_IP, self::WINDOW_EMAIL_IP)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($emailIpKey) / 60);
            $this->flash('modal_error', sprintf(__('auth.too_many_activation_requests'), max(1, $wait)));
            Response::redirect($back);
        }

        // Rate limiting par IP spécifique resend (15 min)
        $ipKey = 'resend_activation_ip:' . $ip;
        if (!$this->rateLimiter->checkLimit($ipKey, self::MAX_RESEND_VERIF, self::WINDOW_LOGIN)) {
            $wait = (int) ceil($this->rateLimiter->getRetryAfter($ipKey) / 60);
            $this->flash('modal_error', sprintf(__('auth.too_many_activation_requests'), max(1, $wait)));
            Response::redirect($back);
        }

        // Validation basique du format email
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->rateLimiter->recordAttempt($emailIpKey, self::WINDOW_EMAIL_IP);
            $this->rateLimiter->recordAttempt($ipKey, self::WINDOW_LOGIN);
            $this->flash('resend_activation_success', '1');
            Response::redirect($back);
        }

        $account = $this->accounts->findByEmail($email);

        // Anti-énumération : retour silencieux si email inconnu ou déjà vérifié
        if (!$account || $account['email_verified_at']) {
            $this->rateLimiter->recordAttempt($emailIpKey, self::WINDOW_EMAIL_IP);
            $this->rateLimiter->recordAttempt($ipKey, self::WINDOW_LOGIN);
            $this->flash('resend_activation_success', '1');
            Response::redirect($back);
        }

        // Rate limiting par userId (15 min)
        $userKey = 'resend_activation:' . (int) $account['id'];
        if ($this->rateLimiter->checkLimit($userKey, self::MAX_RESEND_VERIF, self::WINDOW_LOGIN)) {
            $newToken    = bin2hex(random_bytes(32));
            $this->accounts->refreshVerificationToken((int) $account['id'], $newToken);
            $verifyUrl   = APP_URL . "/{$lang}/verification/{$newToken}";
            $displayName = $account['account_type'] === 'company'
                ? ($account['company_name'] ?? '')
                : trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));
            try {
                (new MailService())->sendEmailVerification($account['email'], $displayName, $verifyUrl, $lang);
            } catch (\Throwable $e) {
                \Core\ErrorLogger::warning($e);
            }
            $this->rateLimiter->recordAttempt($userKey, self::WINDOW_LOGIN);
        }

        $this->rateLimiter->recordAttempt($emailIpKey, self::WINDOW_EMAIL_IP);
        $this->rateLimiter->recordAttempt($ipKey, self::WINDOW_LOGIN);
        $this->flash('resend_activation_success', '1');
        Response::redirect($back);
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
    private function validateRegister( // NOSONAR php:S107 — 8 paramètres de validation de formulaire, regroupement en tableau alourdirait les appels
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
     * Redirige avec un message d'erreur de compte verrouillé.
     *
     * @param int    $wait     Minutes de lockout restantes
     * @param string $safeBack URL de redirection
     * @return never
     */
    private function handleLockedResult(int $wait, string $safeBack): never
    {
        $this->flash('modal_error', sprintf(__('auth.account_locked'), max(1, $wait)));
        Response::redirect($safeBack);
    }

    /**
     * Redirige avec le message de suppression programmée.
     *
     * @param string $safeBack URL de redirection
     * @return never
     */
    private function handleDeletedResult(string $safeBack): never
    {
        $this->flash('modal_error', __('auth.account_pending_deletion'));
        Response::redirect($safeBack);
    }

    /**
     * Redirige avec un message d'identifiants invalides, en gérant le lockout BT2 si déclenché.
     *
     * @param array{result: string, account: array<string, mixed>|null, wait: int} $attempt Résultat de LoginService::attempt()
     * @param string                                                                $safeBack URL de redirection
     * @return never
     */
    private function handleBadCredentialsResult(array $attempt, string $safeBack): never
    {
        // Si le compte vient d'être verrouillé (BT2), le service a déjà envoyé l'email
        // On vérifie si le compte est maintenant locké pour afficher le bon message
        if ($attempt['account'] !== null) {
            $accountKey = 'account_lockout:' . (int) $attempt['account']['id'];
            $remaining  = $this->rateLimiter->getRetryAfter($accountKey);
            if ($remaining > 0) {
                $wait = (int) ceil($remaining / 60);
                $this->flash('modal_error', sprintf(__('auth.account_locked'), max(1, $wait)));
                Response::redirect($safeBack);
            }
        }
        $this->flash('modal_error', __('auth.invalid_credentials'));
        Response::redirect($safeBack);
    }

    /**
     * Redirige avec un message d'identifiants invalides (email non vérifié — anti-énumération).
     *
     * @param string $safeBack URL de redirection
     * @return never
     */
    private function handleUnverifiedResult(string $safeBack): never
    {
        $this->flash('modal_error', __('auth.invalid_credentials'));
        Response::redirect($safeBack);
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
                (string) ($account['lang'] ?? $lang),
                $confirmToken
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e);
        }

        $_SESSION['pending_device'] = [
            'device_name' => $deviceName,
            'mfa_token'   => $confirmToken,
        ];
        Response::redirect("/{$lang}/mon-compte/nouvel-appareil");
    }
}
