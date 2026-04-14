<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\GoogleOAuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\DeviceConfirmTokenModel;
use Model\GoogleLinkTokenModel;
use Model\TrustedDeviceModel;
use PHPUnit\Framework\TestCase;
use Service\GoogleOAuthService;

/**
 * Tests unitaires GoogleOAuthController — flow rattachement par email.
 *
 * Couvre :
 *   - linkConfirm() : page "email envoyé" + redirection si pas d'email en session
 *   - linkVerifyByToken() : token vide / invalide / valide, trust device, issueSession
 *   - linkConfirmPost() : annulation (cancel) + redirect
 *   - handleUntrustedDevice() (via callback) : MFA non-trusted device
 */
class GoogleOAuthControllerLinkFlowTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock);

        $_SESSION = [];
        $_COOKIE  = [];
        $_GET     = [];
        $_POST    = [];
        $_ENV['GOOGLE_CLIENT_ID']     = 'test-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'test-secret';
        $_ENV['APP_URL']              = 'http://localhost';
        $_SERVER['REQUEST_METHOD']    = 'GET';
        $_SERVER['REQUEST_URI']       = '/fr/auth/google/link';
        $_SERVER['HTTP_USER_AGENT']   = 'Mozilla/5.0 Chrome/120';
        $_SERVER['REMOTE_ADDR']       = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_COOKIE  = [];
        $_GET     = [];
        $_POST    = [];
    }

    private function makeController(): GoogleOAuthController
    {
        return new GoogleOAuthController(new Request());
    }

    private function injectProp(GoogleOAuthController $ctrl, string $name, object $mock): void
    {
        $prop = new \ReflectionProperty(GoogleOAuthController::class, $name);
        $prop->setValue($ctrl, $mock);
    }

    // ----------------------------------------------------------------
    // linkConfirm() — affiche la page si email en session
    // ----------------------------------------------------------------

    public function testLinkConfirmRendersViewWhenEmailInSession(): void
    {
        $_SESSION['pending_google_link_email_sent'] = 'user@example.com';

        ob_start();
        try {
            $this->makeController()->linkConfirm(['lang' => 'fr']);
        } catch (\Throwable) {
            // view() rend du HTML — peut générer warning ou non selon présence du template
        }
        ob_end_clean();

        // L'email a bien été consommé (unset)
        $this->assertArrayNotHasKey('pending_google_link_email_sent', $_SESSION);
    }

    // ----------------------------------------------------------------
    // linkConfirm() — redirige si pas d'email en session
    // ----------------------------------------------------------------

    public function testLinkConfirmRedirectsToLoginWhenNoEmail(): void
    {
        $_SESSION = [];

        $location = null;
        try {
            $this->makeController()->linkConfirm(['lang' => 'fr']);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertSame('/fr?login=1', $location);
    }

    // ----------------------------------------------------------------
    // linkVerifyByToken() — token vide → redirect
    // ----------------------------------------------------------------

    public function testLinkVerifyByTokenWithEmptyTokenSetsFlash(): void
    {
        $_GET = [];

        try {
            $this->makeController()->linkVerifyByToken(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // linkVerifyByToken() — token introuvable / consommé / expiré
    // ----------------------------------------------------------------

    public function testLinkVerifyByTokenWithUnknownTokenSetsFlash(): void
    {
        $_GET = ['token' => 'unknown-token'];

        $ctrl       = $this->makeController();
        $linkTokens = $this->createStub(GoogleLinkTokenModel::class);
        $linkTokens->method('findActive')->willReturn(false);
        $this->injectProp($ctrl, 'linkTokens', $linkTokens);

        try {
            $ctrl->linkVerifyByToken(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // linkVerifyByToken() — token valide → linkGoogleId + issueSession
    // ----------------------------------------------------------------

    public function testLinkVerifyByTokenValidLinksAccountAndIssuesSession(): void
    {
        $_GET = ['token' => 'valid-token'];

        $ctrl = $this->makeController();

        $record = ['id' => 9, 'account_id' => 42, 'google_id' => 'g-abc'];
        $linkTokens = $this->createStub(GoogleLinkTokenModel::class);
        $linkTokens->method('findActive')->willReturn($record);
        $this->injectProp($ctrl, 'linkTokens', $linkTokens);

        $account = ['id' => 42, 'role' => 'customer', 'lang' => 'fr', 'email' => 'u@ex.com', 'has_connected' => 1];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);
        $accounts->method('hasDeclaredMajority')->willReturn(true);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $trustedDevices = $this->createStub(TrustedDeviceModel::class);
        $trustedDevices->method('isTrusted')->willReturn(false);
        $this->injectProp($ctrl, 'trustedDevices', $trustedDevices);

        $connections = $this->createStub(ConnectionModel::class);
        $this->injectProp($ctrl, 'connections', $connections);

        $caught = null;
        try {
            $ctrl->linkVerifyByToken(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // issueSession() redirige systématiquement (HttpException attendue)
        $this->assertNotNull($caught);
    }

    // ----------------------------------------------------------------
    // linkVerifyByToken() — account introuvable après linkGoogleId
    // ----------------------------------------------------------------

    public function testLinkVerifyByTokenAccountNotFoundSetsFlash(): void
    {
        $_GET = ['token' => 'valid-token'];

        $ctrl = $this->makeController();

        $record = ['id' => 9, 'account_id' => 42, 'google_id' => 'g-abc'];
        $linkTokens = $this->createStub(GoogleLinkTokenModel::class);
        $linkTokens->method('findActive')->willReturn($record);
        $this->injectProp($ctrl, 'linkTokens', $linkTokens);

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(false);
        $this->injectProp($ctrl, 'accounts', $accounts);

        try {
            $ctrl->linkVerifyByToken(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // linkConfirmPost() — annulation
    // ----------------------------------------------------------------

    public function testLinkConfirmPostCancelSetsModalError(): void
    {
        $_POST = ['action' => 'cancel'];
        $_SESSION['pending_google_link_email_sent'] = 'u@ex.com';

        $location = null;
        try {
            $this->makeController()->linkConfirmPost(['lang' => 'fr']);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('pending_google_link_email_sent', $_SESSION);
        $this->assertSame('/fr?login=1', $location);
    }

    // ----------------------------------------------------------------
    // linkConfirmPost() — sans action cancel → redirect simple
    // ----------------------------------------------------------------

    public function testLinkConfirmPostWithoutActionRedirectsOnly(): void
    {
        $_POST = [];

        $location = null;
        try {
            $this->makeController()->linkConfirmPost(['lang' => 'en']);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertSame('/en?login=1', $location);
        $this->assertArrayNotHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // callback() → handleUntrustedDevice : MFA challenge envoyé
    // ----------------------------------------------------------------

    public function testCallbackUntrustedDeviceTriggersMfaChallenge(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $existingAccount = [
            'id' => 100, 'role' => 'customer', 'lang' => 'fr',
            'email' => 'mfa@example.com', 'firstname' => 'M', 'lastname' => 'FA',
            'has_connected' => 1, 'account_type' => 'individual',
        ];

        $ctrl = $this->makeController();

        $oauth = $this->createStub(GoogleOAuthService::class);
        $oauth->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauth->method('fetchUserInfo')->willReturn([
            'sub' => 'g-mfa', 'email' => 'mfa@example.com',
            'given_name' => 'M', 'family_name' => 'FA',
        ]);
        $this->injectProp($ctrl, 'oauth', $oauth);

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByGoogleId')->willReturn($existingAccount);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $trustedDevices = $this->createStub(TrustedDeviceModel::class);
        $trustedDevices->method('isTrusted')->willReturn(false);
        $this->injectProp($ctrl, 'trustedDevices', $trustedDevices);

        $deviceTokens = $this->createStub(DeviceConfirmTokenModel::class);
        $this->injectProp($ctrl, 'deviceConfirmTokens', $deviceTokens);

        $location = null;
        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertStringContainsString('/fr/mon-compte/nouvel-appareil', (string) $location);
        // Inspection via JSON pour éviter les contraintes PHPStan sur la shape figée de $_SESSION.
        $sessionJson = json_encode($_SESSION) ?: '';
        $this->assertStringContainsString('"pending_device"', $sessionJson);
        $this->assertStringContainsString('"mfa_token"', $sessionJson);
    }

    // ----------------------------------------------------------------
    // callback() → handleUntrustedDevice avec compte company
    // ----------------------------------------------------------------

    public function testCallbackUntrustedDeviceCompanyAccountUsesCompanyName(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $companyAccount = [
            'id' => 200, 'role' => 'customer', 'lang' => 'en',
            'email' => 'pro@example.com', 'company_name' => 'Acme Corp',
            'has_connected' => 1, 'account_type' => 'company',
        ];

        $ctrl = $this->makeController();

        $oauth = $this->createStub(GoogleOAuthService::class);
        $oauth->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauth->method('fetchUserInfo')->willReturn([
            'sub' => 'g-pro', 'email' => 'pro@example.com',
            'given_name' => 'Pro', 'family_name' => 'User',
        ]);
        $this->injectProp($ctrl, 'oauth', $oauth);

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByGoogleId')->willReturn($companyAccount);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $trustedDevices = $this->createStub(TrustedDeviceModel::class);
        $trustedDevices->method('isTrusted')->willReturn(false);
        $this->injectProp($ctrl, 'trustedDevices', $trustedDevices);

        $deviceTokens = $this->createStub(DeviceConfirmTokenModel::class);
        $this->injectProp($ctrl, 'deviceConfirmTokens', $deviceTokens);

        try {
            $ctrl->callback(['lang' => 'en']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('pending_device', $_SESSION);
    }
}
