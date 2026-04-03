<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\CookieHelper;
use Core\Jwt;
use Core\Response;
use Model\AccountModel;
use Model\ConnectionModel;
use Service\GoogleOAuthService;

/**
 * Gère le flux OAuth2 Google : redirection vers Google et traitement du callback.
 *
 * Flux :
 *   GET /{lang}/auth/google          → authorize()  — génère le state, redirige vers Google
 *   GET /{lang}/auth/google/callback → callback()   — traite le retour, pose le JWT
 */
class GoogleOAuthController extends Controller
{
    /** Durée de vie du JWT Google OAuth (24h). */
    private const JWT_EXPIRY = 86400;

    private AccountModel $accounts;
    private ConnectionModel $connections;
    private GoogleOAuthService $oauth;

    /**
     * Initialise les dépendances du contrôleur.
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts    = new AccountModel();
        $this->connections = new ConnectionModel();
        $this->oauth       = new GoogleOAuthService(
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

        $authUrl = $this->oauth->buildAuthUrl($redirectUri, $state);

        Response::redirect($authUrl);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/auth/google/callback
    // ----------------------------------------------------------------

    /**
     * Traite le callback Google OAuth2.
     *
     * Cas 1 — google_id connu          : connexion directe
     * Cas 2 — email connu, pas google_id : rattachement automatique + connexion
     * Cas 3 — email inconnu             : création de compte + connexion
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function callback(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang     = $this->resolveLang($params);
        $loginUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . "/{$lang}?login=1";

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
        } catch (\RuntimeException) {
            $this->flash('modal_error', __('auth.google_error'));
            Response::redirect($loginUrl);
        }

        $googleId = $userInfo['sub'];
        $email    = strtolower(trim($userInfo['email']));

        // Cas 1 — google_id déjà lié à un compte
        $account = $this->accounts->findByGoogleId($googleId);

        // Cas 2 — email connu, pas encore de google_id → confirmation de rattachement
        if (!$account) {
            $existing = $this->accounts->findByEmail($email);
            if ($existing) {
                $_SESSION['pending_google_link'] = [
                    'google_id'  => $googleId,
                    'account_id' => (int) $existing['id'],
                    'email'      => $email,
                    'firstname'  => $userInfo['given_name'] ?? '',
                ];
                Response::redirect(
                    rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . "/{$lang}/auth/google/link"
                );
            }
        }

        // Cas 3 — email inconnu → création de compte
        if (!$account) {
            $accountId = $this->accounts->createFromGoogle(
                $email,
                $googleId,
                $lang,
                $userInfo['given_name'] ?? '',
                $userInfo['family_name'] ?? ''
            );
            $account = $this->accounts->findById($accountId);
        }

        if (!$account) {
            $this->flash('modal_error', __('auth.google_error'));
            Response::redirect($loginUrl);
        }

        $this->issueSession($account, $lang);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/auth/google/link
    // ----------------------------------------------------------------

    /**
     * Affiche la page de confirmation de rattachement de compte.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function linkConfirm(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang    = $this->resolveLang($params);
        $pending = $_SESSION['pending_google_link'] ?? null;

        if (!$pending) {
            Response::redirect(
                rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . "/{$lang}?login=1"
            );
        }

        $this->view('auth/google-link-confirm', [
            'lang'      => $lang,
            'email'     => $pending['email'],
            'firstname' => $pending['firstname'] ?? '',
            'csrf'      => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/auth/google/link
    // ----------------------------------------------------------------

    /**
     * Traite la confirmation (ou l'annulation) du rattachement de compte Google.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     */
    public function linkConfirmPost(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $lang     = $this->resolveLang($params);
        $loginUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . "/{$lang}?login=1";
        $pending  = $_SESSION['pending_google_link'] ?? null;

        if (!$pending) {
            $this->flash('modal_error', __('auth.google_error'));
            Response::redirect($loginUrl);
        }

        unset($_SESSION['pending_google_link']);

        // Annulation → retour au modal de connexion
        if (($_POST['action'] ?? '') === 'cancel') {
            Response::redirect($loginUrl);
        }

        // Confirmation → rattacher + connecter
        $this->accounts->linkGoogleId((int) $pending['account_id'], $pending['google_id']);
        $account = $this->accounts->findById((int) $pending['account_id']);

        if (!$account) {
            $this->flash('modal_error', __('auth.google_error'));
            Response::redirect($loginUrl);
        }

        $this->issueSession($account, $lang);
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Émet le JWT, enregistre la connexion et redirige vers l'espace client.
     *
     * @param array<string, mixed> $account Données du compte (id, role, lang…)
     * @param string               $lang    Code langue courant
     */
    private function issueSession(array $account, string $lang): never
    {
        $token       = Jwt::generate((int) $account['id'], $account['role'], self::JWT_EXPIRY);
        $deviceToken = $this->resolveDeviceToken();
        $ua          = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        CookieHelper::set($token, self::JWT_EXPIRY);

        if (($account['lang'] ?? $lang) !== $lang) {
            $this->accounts->updateLang((int) $account['id'], $lang);
        }

        $this->connections->create(
            (int) $account['id'],
            $token,
            $deviceToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $ua,
            $this->deriveDeviceName($ua),
            'google',
            self::JWT_EXPIRY
        );

        $this->accounts->markAsConnected((int) $account['id']);

        Response::redirect(
            rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . "/{$lang}/mon-compte"
        );
    }

    /**
     * Construit l'URI de callback OAuth pour la langue donnée.
     *
     * Priorité :
     *   1. GOOGLE_FR_FALBACK / GOOGLE_EN_FALBACK  — valeur explicite .env (localhost en dev)
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
        return rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/') . "/{$lang}/auth/google/callback";
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

    /**
     * Retourne le device token depuis le cookie, ou en génère un nouveau.
     *
     * @return string Token hexadécimal de 64 caractères
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
            'secure'   => (defined('APP_ENV') ? APP_ENV : ($_ENV['APP_ENV'] ?? 'production')) === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return $token;
    }

    /**
     * Dérive un nom lisible d'appareil depuis le User-Agent.
     *
     * @param string $ua User-Agent HTTP
     * @return string Nom au format "Browser · OS"
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
}
