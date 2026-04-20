<?php

declare(strict_types=1);

namespace Controller\Api;

use Core\Controller;
use Core\CookieHelper;
use Core\DeviceHelper;
use Core\Jwt;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\GoogleLinkTokenModel;
use Model\TrustedDeviceModel;

/**
 * Endpoint de polling pour le rattachement compte Google (Cas 2).
 *
 * Lorsque le token est consommé (l'utilisateur a cliqué le lien dans l'email),
 * c'est cet endpoint — appelé depuis l'onglet initiateur — qui émet la session
 * (JWT + cookie). L'onglet email (Tab B) se contente d'afficher une confirmation.
 */
class GoogleLinkStatusController extends Controller
{
    use DeviceHelper;

    private GoogleLinkTokenModel $linkTokens;
    private AccountModel $accounts;
    private ConnectionModel $connections;
    private TrustedDeviceModel $trustedDevices;

    /** Durée de vie du JWT (identique à GoogleOAuthController). */
    private const JWT_EXPIRY = 86400;

    /**
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->linkTokens     = new GoogleLinkTokenModel();
        $this->accounts       = new AccountModel();
        $this->connections    = new ConnectionModel();
        $this->trustedDevices = new TrustedDeviceModel();
    }

    /**
     * GET /api/google-link-status
     *
     * Retourne :
     *   {"status":"pending"}              — token actif, rattachement pas encore effectué
     *   {"status":"linked","redirect":"…"} — token consommé → session émise, redirect fourni
     *   {"status":"expired"}              — token introuvable/expiré ou session absente
     *
     * Rate limit : 1 requête/s par session.
     *
     * @param array<string, mixed> $params Paramètres de route (non utilisés)
     * @return void
     */
    public function status(array $params): void // NOSONAR — $params requis par le router
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $now      = time();
        $lastCall = (int) ($_SESSION['google_link_status_last_call'] ?? 0);
        if ($now - $lastCall < 1) {
            $this->json(['status' => 'pending'], 429);
        }
        $_SESSION['google_link_status_last_call'] = $now;

        $plainToken = (string) ($_SESSION['pending_google_link_token'] ?? '');
        if ($plainToken === '') {
            $this->json(['status' => 'expired']);
        }

        $consumed = $this->linkTokens->isConsumed($plainToken);

        if ($consumed === null) {
            $this->json(['status' => 'expired']);
        }

        if (!$consumed) {
            $this->json(['status' => 'pending']);
        }

        // Token consommé — émettre la session dans cet onglet (Tab A)
        $record = $this->linkTokens->findByHash($plainToken);
        if ($record === false) {
            $this->json(['status' => 'expired']);
        }

        $accountId = (int) $record['account_id'];
        $account = $this->accounts->findById($accountId);
        if ($account === false) {
            $this->json(['status' => 'expired']);
        }

        $accountLang = in_array((string) ($account['lang'] ?? ''), SUPPORTED_LANGS, true)
            ? (string) $account['lang']
            : (string) ($record['lang'] ?? 'fr');

        $deviceToken = $this->resolveDeviceToken();
        $deviceName  = $this->deriveDeviceName($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

        // Email validé → device de confiance d'emblée (même logique que linkVerifyByToken)
        if (!$this->trustedDevices->isTrusted($accountId, $deviceToken)) {
            $this->trustedDevices->trust($accountId, $deviceToken, $deviceName);
        }

        $jwt = Jwt::generate($accountId, (string) ($account['role'] ?? 'user'), self::JWT_EXPIRY);

        session_regenerate_id(true);
        CookieHelper::set($jwt, self::JWT_EXPIRY);

        setcookie('cb-lang', $accountLang, [
            'expires'  => time() + self::JWT_EXPIRY,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $this->connections->create(
            $accountId,
            $jwt,
            $deviceToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $deviceName,
            'google',
            self::JWT_EXPIRY
        );

        if (!(bool) ($account['has_connected'] ?? false)) {
            $this->accounts->markAsConnected($accountId);
            $this->trustedDevices->trust($accountId, $deviceToken, $deviceName);
        } else {
            $this->trustedDevices->updateLastSeen($accountId, $deviceToken);
        }

        $this->accounts->resetInactivityWarning($accountId);
        $this->accounts->updateLastLoginAt($accountId);

        if (!$this->accounts->hasDeclaredMajority($accountId)) {
            $_SESSION['majority_pending'] = true;
        } else {
            unset($_SESSION['majority_pending']);
        }

        $intended   = $_SESSION['_intended'] ?? null;
        $returnPath = $_SESSION['oauth_return_path'] ?? "/{$accountLang}";
        unset(
            $_SESSION['pending_google_link_token'],
            $_SESSION['google_link_status_last_call'],
            $_SESSION['_intended'],
            $_SESSION['oauth_return_path']
        );

        $validIntended = $intended !== null
            && is_string($intended)
            && preg_match('#^/[^/]#', $intended)
            && !str_contains($intended, '://');

        $redirect = $validIntended ? $intended : $returnPath;

        // Langue du compte différente de la langue de l'URL de retour → adapter
        if (preg_match('#^/(fr|en)(/.+)?$#', $redirect, $m) && $m[1] !== $accountLang) {
            $redirect = '/' . $accountLang . ($m[2] ?? '');
        }

        $this->json(['status' => 'linked', 'redirect' => $redirect]);
    }
}
