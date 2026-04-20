<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\CookieHelper;
use Core\DeviceHelper;
use Core\Exception\GoogleOAuthException;
use Core\Jwt;
use Core\Response;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\DeviceConfirmTokenModel;
use Model\GoogleLinkTokenModel;
use Model\TrustedDeviceModel;
use Service\GoogleOAuthService;
use Service\MailService;

/**
 * Gère le flux OAuth2 Google : redirection vers Google et traitement du callback.
 *
 * Flux :
 *   GET /{lang}/auth/google          → authorize()  — génère le state, redirige vers Google
 *   GET /{lang}/auth/google/callback → callback()   — traite le retour, pose le JWT
 *
 * MFA (Option A, 2026-04-14) : identique au login classique — si le device n'est
 * pas de confiance et que ce n'est pas la première connexion du compte, on envoie
 * un email de confirmation new-device (DeviceConfirmToken).
 *
 * Rattachement Cas 2 : flow email dédié (GoogleLinkTokenModel) — un email de
 * confirmation est envoyé au compte existant, l'utilisateur clique le lien, le
 * google_id est rattaché puis la session est émise (device auto-trusté car email
 * déjà validé).
 */
class GoogleOAuthController extends Controller
{
    use DeviceHelper;

    /** Durée de vie du JWT Google OAuth (24h). */
    private const JWT_EXPIRY = 86400;

    private AccountModel $accounts;
    private ConnectionModel $connections;
    private TrustedDeviceModel $trustedDevices;
    private DeviceConfirmTokenModel $deviceConfirmTokens;
    private GoogleLinkTokenModel $linkTokens;
    private GoogleOAuthService $oauth;

    /**
     * Initialise les dépendances du contrôleur.
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts            = new AccountModel();
        $this->connections         = new ConnectionModel();
        $this->trustedDevices      = new TrustedDeviceModel();
        $this->deviceConfirmTokens = new DeviceConfirmTokenModel();
        $this->linkTokens          = new GoogleLinkTokenModel();
        $this->oauth               = new GoogleOAuthService(
            $_ENV['GOOGLE_CLIENT_ID']     ?? '',
            $_ENV['GOOGLE_CLIENT_SECRET'] ?? ''
        );
    }

    // ----------------------------------------------------------------
    // GET /{lang}/auth/google
    // ----------------------------------------------------------------

    /**
     * Génère un nonce state, le stocke en session et redirige vers Google OAuth2.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function authorize(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang        = $this->resolveLang($params);
        $state       = bin2hex(random_bytes(16));
        $redirectUri = $this->buildRedirectUri($lang);

        $_SESSION['oauth_google_state'] = $state;

        // Sauvegarder la page d'origine pour y revenir après connexion
        $referer    = $_SERVER['HTTP_REFERER'] ?? '';
        $returnPath = parse_url($referer, PHP_URL_PATH) ?? '';
        $validReturn = $returnPath !== ''
            && preg_match('#^/[^/]#', $returnPath)
            && !str_contains($returnPath, '://')
            && !str_starts_with($returnPath, '/auth/');
        $_SESSION['oauth_return_path'] = $validReturn ? $returnPath : "/{$lang}";

        $authUrl = $this->oauth->buildAuthUrl($redirectUri, $state);

        Response::redirect($authUrl);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/auth/google/callback
    // ----------------------------------------------------------------

    /**
     * Traite le callback Google OAuth2.
     *
     * Cas 1 — google_id connu            : connexion (MFA si device non trusted)
     * Cas 2 — email connu, pas google_id : envoi email de confirmation de rattachement
     * Cas 3 — email inconnu              : création de compte + connexion (bypass MFA, 1ère fois)
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function callback(array $params): void // NOSONAR php:S3776 — complexité OAuth : état CSRF, échange de code, 3 cas d'association + MFA
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang     = $this->resolveLang($params);
        $loginUrl = "/{$lang}?login=1";

        // Erreur explicite retournée par Google (ex. accès refusé)
        if (!empty($_GET['error'])) {
            $this->flash('modal_error', __('auth.google_error'));
            Response::redirect($loginUrl);
        }

        // Vérification du state anti-CSRF
        $stateSession = $_SESSION['oauth_google_state'] ?? null;
        $stateGet     = $_GET['state'] ?? null;

        if (!$stateSession || !$stateGet || !hash_equals($stateSession, $stateGet)) {
            unset($_SESSION['oauth_google_state']);
            $this->flash('modal_error', __('auth.google_state_invalid'));
            Response::redirect($loginUrl);
        }

        unset($_SESSION['oauth_google_state']);

        $code = $_GET['code'] ?? null;
        if (!$code) {
            $this->flash('modal_error', __('auth.google_error'));
            Response::redirect($loginUrl);
        }

        // Échange du code et récupération des infos utilisateur
        try {
            $redirectUri = $this->buildRedirectUri($lang);
            $tokenData   = $this->oauth->exchangeCode((string) $code, $redirectUri);
            $userInfo    = $this->oauth->fetchUserInfo($tokenData['access_token']);
        } catch (GoogleOAuthException) {
            $this->flash('modal_error', __('auth.google_error'));
            Response::redirect($loginUrl);
        }

        $googleId = $userInfo['sub'];
        $email    = strtolower(trim($userInfo['email']));

        // Cas 1 — google_id déjà lié à un compte actif
        $account = $this->accounts->findByGoogleId($googleId);

        // Cas 1b — compte en cours de suppression retrouvé via google_id → réactivation automatique
        if (!$account) {
            $deleted = $this->accounts->findDeletedByGoogleId($googleId);
            if ($deleted) {
                $this->accounts->reactivate((int) $deleted['id']);
                $account = $this->accounts->findById((int) $deleted['id']);
            }
        }

        // Cas 2 — email connu (actif), pas encore de google_id → double confirmation via email
        if (!$account) {
            $existing = $this->accounts->findByEmail($email);
            if ($existing) {
                $this->challengeLinkByEmail($existing, $googleId, $lang);
                // challengeLinkByEmail redirige → jamais atteint
            }
        }

        // Cas 2b — compte en cours de suppression retrouvé via email → réactivation + rattachement direct
        if (!$account) {
            $deleted = $this->accounts->findDeletedByEmail($email);
            if ($deleted) {
                $this->accounts->reactivate((int) $deleted['id']);
                $this->accounts->linkGoogleId((int) $deleted['id'], $googleId);
                $account = $this->accounts->findById((int) $deleted['id']);
            }
        }

        // Cas 3 — email inconnu → sélection du type de compte avant création
        if (!$account) {
            $_SESSION['oauth_google_pending'] = [
                'email'     => $email,
                'google_id' => $googleId,
                'lang'      => $lang,
                'firstname' => $userInfo['given_name'] ?? '',
                'lastname'  => $userInfo['family_name'] ?? '',
            ];
            Response::redirect("/{$lang}/auth/google/type-compte");
        }

        $this->issueSession($account, $lang);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/auth/google/type-compte
    // ----------------------------------------------------------------

    /**
     * Affiche le formulaire de sélection du type de compte (Cas 3 — nouvel utilisateur Google).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function accountTypePending(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang = $this->resolveLang($params);

        if (empty($_SESSION['oauth_google_pending'])) {
            Response::redirect("/{$lang}?login=1");
        }

        $pending = $_SESSION['oauth_google_pending'];
        $this->view('auth/google-account-type', [
            'lang'            => $lang,
            'googleFirstname' => $pending['firstname'],
            'googleLastname'  => $pending['lastname'],
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/auth/google/type-compte
    // ----------------------------------------------------------------

    /**
     * Crée le compte Google avec le type choisi par l'utilisateur, puis émet la session.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function accountTypeSubmit(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang    = $this->resolveLang($params);
        $pending = $_SESSION['oauth_google_pending'] ?? null;

        if (!$pending) {
            $this->flash('modal_error', __('auth.google_type_session_expired'));
            Response::redirect("/{$lang}?login=1");
        }

        $csrf = $this->request->post('csrf_token', '');
        if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
            $this->flash('modal_error', __('auth.google_error'));
            Response::redirect("/{$lang}/auth/google/type-compte");
        }

        $accountType = $this->request->post('account_type', 'individual');
        if (!in_array($accountType, ['individual', 'company'], true)) {
            $accountType = 'individual';
        }
        $companyName = trim($this->request->post('company_name', ''));

        unset($_SESSION['oauth_google_pending']);

        $accountId = $this->accounts->createFromGoogle(
            $pending['email'],
            $pending['google_id'],
            $pending['lang'],
            $pending['firstname'],
            $pending['lastname'],
            $accountType,
            $companyName
        );

        $account = $this->accounts->findById($accountId);

        if (!$account) {
            $this->flash('modal_error', __('auth.google_error'));
            Response::redirect("/{$lang}?login=1");
        }

        try {
            $firstname    = $account['firstname'] ?? $account['company_name'] ?? $pending['firstname'];
            $isSubscribed = (int) ($account['newsletter'] ?? 0) === 1;
            $confirmToken = '';
            if (!$isSubscribed) {
                $confirmToken = bin2hex(random_bytes(32));
                $this->accounts->storeNewsletterConfirmToken((int) $account['id'], $confirmToken);
            }
            (new \Service\MailService())->sendWelcome(
                $account['email'],
                (string) $firstname,
                $pending['lang'],
                $isSubscribed,
                $confirmToken
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[GoogleOAuth/accountTypeSubmit]');
        }

        $this->issueSession($account, $lang);
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Cas 2 — déclenche le flux de double confirmation par email pour rattacher un
     * google_id à un compte classique existant. Crée un token, envoie l'email puis
     * redirige vers la page "email envoyé".
     *
     * @param array<string, mixed> $existing Compte classique trouvé par email
     * @param string               $googleId google_id à rattacher
     * @param string               $lang     Langue courante
     */
    private function challengeLinkByEmail(array $existing, string $googleId, string $lang): never
    {
        $accountId   = (int) $existing['id'];
        $accountLang = (string) ($existing['lang'] ?? $lang);
        $token       = '';

        try {
            $token       = $this->linkTokens->create($accountId, $googleId, $accountLang);
            $base        = rtrim($_ENV['APP_URL'] ?? '', '/');
            $confirmUrl  = $base . "/{$accountLang}/auth/google/link/verify?token=" . urlencode($token);
            $displayName = $existing['firstname']
                ?? $existing['company_name']
                ?? '';
            (new MailService())->sendGoogleLinkConfirmation(
                $existing['email'],
                (string) $displayName,
                $confirmUrl,
                $accountLang
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[GoogleOAuth/linkToken]');
        }

        $_SESSION['pending_google_link_email_sent'] = $existing['email'];
        $_SESSION['pending_google_link_token']      = $token;
        Response::redirect("/{$lang}/auth/google/link");
    }

    // ----------------------------------------------------------------
    // GET /{lang}/auth/google/link
    // ----------------------------------------------------------------

    /**
     * Affiche la page de confirmation "email envoyé" après Cas 2.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function linkConfirm(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang        = $this->resolveLang($params);
        $emailSentTo = $_SESSION['pending_google_link_email_sent'] ?? null;
        unset($_SESSION['pending_google_link_email_sent']);

        if (!$emailSentTo) {
            Response::redirect("/{$lang}?login=1");
        }

        $this->view('auth/google-link-confirm', [
            'lang'  => $lang,
            'email' => $emailSentTo,
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/auth/google/link/verify?token=...
    // ----------------------------------------------------------------

    /**
     * Consomme le token envoyé par email et rattache le compte Google au compte classique.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function linkVerifyByToken(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang     = $this->resolveLang($params);
        $loginUrl = "/{$lang}?login=1";
        $token    = (string) ($_GET['token'] ?? '');

        if ($token === '') {
            $this->flash('modal_error', __('auth.google_link_token_invalid'));
            Response::redirect($loginUrl);
        }

        $record = $this->linkTokens->findActive($token);
        if (!$record) {
            $this->flash('modal_error', __('auth.google_link_token_invalid'));
            Response::redirect($loginUrl);
        }

        $this->linkTokens->consume((int) $record['id']);

        $accountId = (int) $record['account_id'];
        $this->accounts->linkGoogleId($accountId, (string) $record['google_id']);

        // La session est émise par le polling côté onglet initiateur (Tab A).
        // Cet onglet (Tab B — lien email) se contente d'afficher la confirmation.
        $this->view('auth/google-link-verify-done', ['lang' => $lang]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/auth/google/link
    // ----------------------------------------------------------------

    /**
     * Traite l'annulation du rattachement depuis la page "email envoyé".
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function linkConfirmPost(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang = $this->resolveLang($params);
        unset($_SESSION['pending_google_link_email_sent'], $_SESSION['pending_google_link_token']);

        if (($_POST['action'] ?? '') === 'cancel') {
            $_SESSION['flash']['modal_error'] = __('auth.google_link_cancel_info');
        }

        Response::redirect("/{$lang}?login=1");
    }

    /**
     * Émet le JWT (ou déclenche le challenge MFA), enregistre la connexion et redirige.
     *
     * MFA Option A : si l'appareil n'est pas de confiance et que ce n'est pas la
     * première connexion, on envoie un email new-device et on bloque l'émission du
     * JWT jusqu'à confirmation.
     *
     * @param array<string, mixed> $account Données du compte (id, role, lang…)
     * @param string               $lang    Code langue courant
     */
    private function issueSession(array $account, string $lang): never
    {
        $deviceToken      = $this->resolveDeviceToken();
        $ua               = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $deviceName       = $this->deriveDeviceName($ua);
        $accountId        = (int) $account['id'];
        $isFirstEverLogin = !(bool) ($account['has_connected'] ?? false);
        $isAlreadyTrusted = $this->trustedDevices->isTrusted($accountId, $deviceToken);
        $trusted          = $isFirstEverLogin || $isAlreadyTrusted;

        // MFA requis : appareil non de confiance et pas la 1ʳᵉ connexion → challenge email
        // MFA_EMAIL_ENABLED=false : auto-trust temporaire (limite SMTP IONOS < 30 jours).
        if (!$trusted) {
            $mfaEnabled = filter_var($_ENV['MFA_EMAIL_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
            if ($mfaEnabled) {
                $this->handleUntrustedDevice($account, $deviceToken, $deviceName, $lang);
            } else {
                \Core\ErrorLogger::warning(
                    new \RuntimeException('[MFA_DISABLED] Auto-trust device — MFA_EMAIL_ENABLED=false'),
                    '[MFA_DISABLED]'
                );
                $this->trustedDevices->trust($accountId, $deviceToken, $deviceName);
            }
        }

        $token       = Jwt::generate($accountId, $account['role'], self::JWT_EXPIRY);

        session_regenerate_id(true); // VULN-005 : protection session fixation après OAuth Google
        CookieHelper::set($token, self::JWT_EXPIRY);

        // Respecter la préférence de langue enregistrée — ne pas écraser avec la langue de l'URL
        $accountLang = in_array((string) ($account['lang'] ?? ''), SUPPORTED_LANGS, true)
            ? (string) $account['lang']
            : $lang;

        // Cookie cb-lang : rend la préférence disponible dans config.php (DEFAULT_LANG)
        setcookie('cb-lang', $accountLang, [
            'expires'  => time() + self::JWT_EXPIRY,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $this->connections->create(
            $accountId,
            $token,
            $deviceToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $ua,
            $deviceName,
            'google',
            self::JWT_EXPIRY
        );

        // 1ʳᵉ connexion : enroll trusted device (bypass MFA initial). Sinon, mise à jour last_seen.
        if ($isFirstEverLogin) {
            $this->accounts->markAsConnected($accountId);
            $this->trustedDevices->trust($accountId, $deviceToken, $deviceName);
        } elseif ($isAlreadyTrusted) {
            $this->trustedDevices->updateLastSeen($accountId, $deviceToken);
        }

        $this->accounts->resetInactivityWarning($accountId);

        if (!$this->accounts->hasDeclaredMajority($accountId)) {
            $_SESSION['majority_pending'] = true;
        } else {
            unset($_SESSION['majority_pending']);
        }

        // _intended prioritaire sur oauth_return_path (navigation directe vers page protégée)
        $intended    = $_SESSION['_intended'] ?? null;
        $returnPath  = $_SESSION['oauth_return_path'] ?? "/{$accountLang}";
        unset($_SESSION['_intended'], $_SESSION['oauth_return_path']);

        $validIntended = $intended !== null
            && is_string($intended)
            && preg_match('#^/[^/]#', $intended)
            && !str_contains($intended, '://');

        // À la connexion : rediriger vers la langue préférée du compte (une seule fois)
        $redirectTarget = $validIntended ? $intended : $returnPath;
        if ($accountLang !== $lang && preg_match('#^/(fr|en)(/.+)?$#', $redirectTarget, $m)) {
            $redirectTarget = '/' . $accountLang . ($m[2] ?? '');
        }
        Response::redirect($redirectTarget);
    }

    /**
     * Crée un DeviceConfirmToken, envoie l'email new-device et redirige vers la
     * page interstitielle de polling. Le JWT sera émis par MfaController::poll
     * après confirmation.
     *
     * @param array<string, mixed> $account     Compte concerné
     * @param string               $deviceToken Token device courant
     * @param string               $deviceName  Nom lisible de l'appareil
     * @param string               $lang        Langue courante
     */
    private function handleUntrustedDevice(
        array $account,
        string $deviceToken,
        string $deviceName,
        string $lang
    ): never {
        $confirmToken = bin2hex(random_bytes(32));
        $accountLang  = (string) ($account['lang'] ?? $lang);
        $returnPath   = $_SESSION['oauth_return_path'] ?? "/{$accountLang}";

        $this->deviceConfirmTokens->create(
            (int) $account['id'],
            $deviceToken,
            $deviceName,
            $confirmToken,
            $returnPath,
            $accountLang
        );

        try {
            $displayName = ($account['account_type'] ?? '') === 'company'
                ? (string) ($account['company_name'] ?? 'Client')
                : trim(((string) ($account['firstname'] ?? '')) . ' ' . ((string) ($account['lastname'] ?? '')));
            (new MailService())->sendNewDeviceAlert(
                $account['email'],
                $displayName,
                $deviceName,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $accountLang,
                $confirmToken
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[GoogleOAuth/newDevice]');
        }

        $_SESSION['pending_device'] = [
            'device_name' => $deviceName,
            'mfa_token'   => $confirmToken,
        ];
        Response::redirect("/{$lang}/mon-compte/nouvel-appareil");
    }

    /**
     * Construit l'URI de callback OAuth pour la langue donnée.
     *
     * Priorité :
     *   1. GOOGLE_FR_FALBACK / GOOGLE_EN_FALBACK  — valeur explicite .env
     *   2. APP_URL + /{lang}/auth/google/callback  — fallback générique
     *
     * @param string $lang Code langue (fr|en)
     * @return string URI complète enregistrée dans la Google Cloud Console
     */
    private function buildRedirectUri(string $lang): string
    {
        $envKey = 'GOOGLE_' . strtoupper($lang) . '_FALBACK';
        if (!empty($_ENV[$envKey])) {
            return $_ENV[$envKey];
        }
        return rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/') . "/{$lang}/auth/google/callback";
    }

    /**
     * Ajoute un message flash en session.
     *
     * @param string $key     Clé du message flash
     * @param string $message Contenu du message
     */
    private function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }
}
