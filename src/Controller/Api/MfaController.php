<?php

declare(strict_types=1);

namespace Controller\Api;

use Core\Controller;
use Core\CookieHelper;
use Core\Jwt;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\TrustedDeviceModel;
use Model\DeviceConfirmTokenModel;

class MfaController extends Controller
{
    private AccountModel $accounts;
    private ConnectionModel $connections;
    private TrustedDeviceModel $trustedDevices;
    private DeviceConfirmTokenModel $deviceConfirmTokens;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts            = new AccountModel();
        $this->connections         = new ConnectionModel();
        $this->trustedDevices      = new TrustedDeviceModel();
        $this->deviceConfirmTokens = new DeviceConfirmTokenModel();
    }

    /**
     * GET /api/mfa/poll?token={mfa_token}
     *
     * Appelé en polling par la page interstitiel (toutes les 3s).
     * Retourne :
     *   {"status":"pending"}   — token valide, pas encore confirmé
     *   {"status":"expired"}   — token expiré ou inexistant
     *   {"status":"ok","redirect":"..."} — confirmé : JWT émis, connexion créée
     */
    public function poll(array $params): void // NOSONAR — $params requis par le router
    {
        $token  = $_GET['token'] ?? '';

        if ($token === '') {
            $this->json(['status' => 'expired']);
        }

        // Vérifie si le token existe et est encore valide (même non confirmé)
        $record = $this->deviceConfirmTokens->findByToken($token);
        if (!$record) {
            $this->json(['status' => 'expired']);
        }

        // Pas encore confirmé
        if ($record['confirmed_at'] === null) {
            $this->json(['status' => 'pending']);
        }

        // Confirmé — émettre le JWT et finaliser la connexion
        $userId      = (int) $record['user_id'];
        $deviceToken = (string) $record['device_token'];
        $deviceName  = (string) ($record['device_name'] ?? '');
        $redirectUrl = (string) ($record['redirect_url'] ?: '/' . ($record['lang'] ?? 'fr'));
        $recordLang  = (string) ($record['lang'] ?? 'fr');

        $account = $this->accounts->findById($userId);
        if (!$account || !$account['email_verified_at']) {
            $this->deviceConfirmTokens->deleteByToken($token);
            $this->json(['status' => 'expired']);
        }

        $expiry = (int) ($_ENV['JWT_EXPIRY'] ?? 3600);
        $jwt    = Jwt::generate($userId, $account['role']);

        CookieHelper::set($jwt, $expiry);

        setcookie('device_token', $deviceToken, [
            'expires'  => time() + (90 * 24 * 3600),
            'path'     => '/',
            'secure'   => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $this->connections->create(
            $userId,
            $jwt,
            $deviceToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $ua,
            $deviceName,
            'password',
            $expiry
        );

        $this->trustedDevices->trust($userId, $deviceToken, $deviceName);

        if (!(bool) ($account['has_connected'] ?? false)) {
            $this->accounts->markAsConnected($userId);
        }

        if ($account['lang'] !== $recordLang) {
            $this->accounts->updateLang($userId, $recordLang);
        }

        $this->deviceConfirmTokens->deleteByToken($token);
        unset($_SESSION['pending_device']);

        $this->json(['status' => 'ok', 'redirect' => $redirectUrl]);
    }
}
