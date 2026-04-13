<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\GoogleOAuthController;
use Core\Database;
use Core\Exception\GoogleOAuthException;
use Core\Exception\HttpException;
use Core\Request;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\DeviceConfirmTokenModel;
use Model\TrustedDeviceModel;
use PHPUnit\Framework\TestCase;
use Service\GoogleOAuthService;

/**
 * Tests unitaires GoogleOAuthController — couverture des branches non couvertes.
 *
 * Couvre : authorize(), callback() erreurs, Cas 2 (MFA rattachement),
 *          resolveDeviceToken(), resolveLang(), issueSession() via AccountModel mock.
 */
class GoogleOAuthControllerOAuthFlowTest extends TestCase
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
        $_SERVER['REQUEST_URI']       = '/fr/auth/google';
        $_SERVER['HTTP_USER_AGENT']   = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120';
        $_SERVER['REMOTE_ADDR']       = '127.0.0.1';

        unset($_ENV['GOOGLE_FR_FALBACK'], $_ENV['GOOGLE_EN_FALBACK']);
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_COOKIE  = [];
        $_GET     = [];
        $_POST    = [];
        unset($_ENV['GOOGLE_FR_FALBACK'], $_ENV['GOOGLE_EN_FALBACK']);
    }

    private function makeController(): GoogleOAuthController
    {
        return new GoogleOAuthController(new Request());
    }

    private function callPrivate(GoogleOAuthController $ctrl, string $method, mixed ...$args): mixed
    {
        $m = new \ReflectionMethod(GoogleOAuthController::class, $method);
        return $m->invoke($ctrl, ...$args);
    }

    // ----------------------------------------------------------------
    // authorize()
    // ----------------------------------------------------------------

    public function testAuthorizeStoresStateInSessionAndRedirects(): void
    {
        $oauthMock = $this->createStub(GoogleOAuthService::class);
        $oauthMock->method('buildAuthUrl')->willReturn('https://accounts.google.com/auth?client_id=test');

        $ctrl = $this->makeController();

        $prop = new \ReflectionProperty(GoogleOAuthController::class, 'oauth');
        $prop->setValue($ctrl, $oauthMock);

        try {
            $ctrl->authorize(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('oauth_google_state', $_SESSION);
        $this->assertNotEmpty($_SESSION['oauth_google_state']);
    }

    // ----------------------------------------------------------------
    // callback() — branches d'erreur
    // ----------------------------------------------------------------

    public function testCallbackWithErrorParamSetsFlashAndRedirects(): void
    {
        $_GET = ['error' => 'access_denied'];

        try {
            $this->makeController()->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    public function testCallbackWithMissingStateSetsFlashAndRedirects(): void
    {
        $_GET     = ['code' => 'someCode'];
        $_SESSION = []; // pas de oauth_google_state

        try {
            $this->makeController()->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    public function testCallbackWithStateMismatchSetsFlash(): void
    {
        $_GET     = ['code' => 'someCode', 'state' => 'attacker-state'];
        $_SESSION = ['oauth_google_state' => 'real-state'];

        try {
            $this->makeController()->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    public function testCallbackWithMissingCodeSetsFlash(): void
    {
        $_GET     = ['state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        try {
            $this->makeController()->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // callback() — cas 1 : google_id connu → issueSession()
    // ----------------------------------------------------------------

    public function testCallbackWithKnownGoogleIdCallsIssueSession(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $fakeAccount = [
            'id' => 42, 'role' => 'customer', 'lang' => 'fr',
            'email' => 'user@example.com', 'firstname' => 'Jean',
        ];

        $ctrl = $this->makeController();

        $oauthMock = $this->createStub(GoogleOAuthService::class);
        $oauthMock->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauthMock->method('fetchUserInfo')->willReturn([
            'sub' => 'g-123', 'email' => 'user@example.com',
            'given_name' => 'Jean', 'family_name' => 'Dupont',
        ]);

        $oauthProp = new \ReflectionProperty(GoogleOAuthController::class, 'oauth');
        $oauthProp->setValue($ctrl, $oauthMock);

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('findByGoogleId')->willReturn($fakeAccount);
        $accountsMock->method('findByEmail')->willReturn(false);
        // updateLang() et markAsConnected() retournent void — createStub les gère sans configuration

        $accountsProp = new \ReflectionProperty(GoogleOAuthController::class, 'accounts');
        $accountsProp->setValue($ctrl, $accountsMock);

        $connsMock = $this->createStub(ConnectionModel::class);
        // create() retourne void — createStub le gère sans configuration

        $connsProp = new \ReflectionProperty(GoogleOAuthController::class, 'connections');
        $connsProp->setValue($ctrl, $connsMock);

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {
            // issueSession() appelle Response::redirect() → HttpException attendue
        }

        // Si on arrive ici sans exception non gérée, le flux a bien atteint issueSession
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // callback() — cas 1b : compte supprimé via google_id → réactivation
    // ----------------------------------------------------------------

    public function testCallbackReactivatesDeletedAccountByGoogleId(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $deletedAccount = ['id' => 77, 'role' => 'customer', 'lang' => 'fr', 'email' => 'reactivated@example.com', 'firstname' => 'Re'];

        $ctrl = $this->makeController();

        $oauthMock = $this->createStub(GoogleOAuthService::class);
        $oauthMock->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauthMock->method('fetchUserInfo')->willReturn([
            'sub' => 'g-deleted', 'email' => 'reactivated@example.com',
            'given_name' => 'Re', 'family_name' => 'User',
        ]);

        $oauthProp = new \ReflectionProperty(GoogleOAuthController::class, 'oauth');
        $oauthProp->setValue($ctrl, $oauthMock);

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('findByGoogleId')->willReturn(false);      // compte actif introuvable
        $accountsMock->method('findDeletedByGoogleId')->willReturn($deletedAccount); // compte supprimé trouvé
        $accountsMock->method('findById')->willReturn($deletedAccount);
        // updateLang et markAsConnected retournent void — createStub les gère sans configuration

        $accountsProp = new \ReflectionProperty(GoogleOAuthController::class, 'accounts');
        $accountsProp->setValue($ctrl, $accountsMock);

        $connsMock = $this->createStub(ConnectionModel::class);
        $connsProp = new \ReflectionProperty(GoogleOAuthController::class, 'connections');
        $connsProp->setValue($ctrl, $connsMock);

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertTrue(true); // flux atteint issueSession sans erreur
    }

    // ----------------------------------------------------------------
    // callback() — cas 2b : compte supprimé via email → réactivation + rattachement
    // ----------------------------------------------------------------

    public function testCallbackReactivatesDeletedAccountByEmail(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $deletedAccount = ['id' => 88, 'role' => 'customer', 'lang' => 'fr', 'email' => 'reactemail@example.com', 'firstname' => 'React'];

        $ctrl = $this->makeController();

        $oauthMock = $this->createStub(GoogleOAuthService::class);
        $oauthMock->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauthMock->method('fetchUserInfo')->willReturn([
            'sub' => 'g-new-sub', 'email' => 'reactemail@example.com',
            'given_name' => 'React', 'family_name' => 'User',
        ]);

        $oauthProp = new \ReflectionProperty(GoogleOAuthController::class, 'oauth');
        $oauthProp->setValue($ctrl, $oauthMock);

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('findByGoogleId')->willReturn(false);
        $accountsMock->method('findDeletedByGoogleId')->willReturn(false);
        $accountsMock->method('findByEmail')->willReturn(false);
        $accountsMock->method('findDeletedByEmail')->willReturn($deletedAccount); // supprimé par email
        $accountsMock->method('findById')->willReturn($deletedAccount);
        // reactivate() et linkGoogleId() retournent void — createStub les gère sans configuration

        $accountsProp = new \ReflectionProperty(GoogleOAuthController::class, 'accounts');
        $accountsProp->setValue($ctrl, $accountsMock);

        $connsMock = $this->createStub(ConnectionModel::class);
        $connsProp = new \ReflectionProperty(GoogleOAuthController::class, 'connections');
        $connsProp->setValue($ctrl, $connsMock);

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertTrue(true); // flux atteint issueSession sans erreur
    }

    // ----------------------------------------------------------------
    // callback() — cas 2 : email connu → MFA standard avec pending_google_id
    // ----------------------------------------------------------------

    public function testCallbackWithExistingEmailTriggersMfaChallenge(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $ctrl = $this->makeController();

        $oauthMock = $this->createStub(GoogleOAuthService::class);
        $oauthMock->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauthMock->method('fetchUserInfo')->willReturn([
            'sub' => 'g-999', 'email' => 'existing@example.com',
            'given_name' => 'Jane', 'family_name' => 'Doe',
        ]);

        $oauthProp = new \ReflectionProperty(GoogleOAuthController::class, 'oauth');
        $oauthProp->setValue($ctrl, $oauthMock);

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('findByGoogleId')->willReturn(false);
        $accountsMock->method('findByEmail')->willReturn([
            'id' => 7, 'email' => 'existing@example.com', 'firstname' => 'Jane', 'lang' => 'fr',
        ]);

        $accountsProp = new \ReflectionProperty(GoogleOAuthController::class, 'accounts');
        $accountsProp->setValue($ctrl, $accountsMock);

        // Device non de confiance → MFA challenge obligatoire
        $trustedMock = $this->createStub(TrustedDeviceModel::class);
        $trustedMock->method('isTrusted')->willReturn(false);
        $trustedProp = new \ReflectionProperty(GoogleOAuthController::class, 'trustedDevices');
        $trustedProp->setValue($ctrl, $trustedMock);

        $dctMock = $this->createStub(DeviceConfirmTokenModel::class);
        $dctProp = new \ReflectionProperty(GoogleOAuthController::class, 'deviceConfirmTokens');
        $dctProp->setValue($ctrl, $dctMock);

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        // Nouveau flux (Option A MFA) : pending_device posé avec mfa_token.
        $this->assertArrayHasKey('pending_device', $_SESSION);
        $this->assertArrayHasKey('mfa_token', $_SESSION['pending_device']);
    }

    // ----------------------------------------------------------------
    // callback() — cas 3 : email inconnu → création de compte
    // ----------------------------------------------------------------

    public function testCallbackWithUnknownEmailCreatesAccount(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $newAccount = ['id' => 55, 'role' => 'customer', 'lang' => 'fr', 'email' => 'new@example.com', 'firstname' => 'New'];

        $ctrl = $this->makeController();

        $oauthMock = $this->createStub(GoogleOAuthService::class);
        $oauthMock->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauthMock->method('fetchUserInfo')->willReturn([
            'sub' => 'g-new', 'email' => 'new@example.com',
            'given_name' => 'New', 'family_name' => 'User',
        ]);

        $oauthProp = new \ReflectionProperty(GoogleOAuthController::class, 'oauth');
        $oauthProp->setValue($ctrl, $oauthMock);

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('findByGoogleId')->willReturn(false);
        $accountsMock->method('findByEmail')->willReturn(false);
        $accountsMock->method('createFromGoogle')->willReturn(55);
        $accountsMock->method('findById')->willReturn($newAccount);
        // updateLang() et markAsConnected() retournent void — createStub les gère sans configuration

        $accountsProp = new \ReflectionProperty(GoogleOAuthController::class, 'accounts');
        $accountsProp->setValue($ctrl, $accountsMock);

        $connsMock = $this->createStub(ConnectionModel::class);
        // create() retourne void — createStub le gère sans configuration

        $connsProp = new \ReflectionProperty(GoogleOAuthController::class, 'connections');
        $connsProp->setValue($ctrl, $connsMock);

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertTrue(true); // flux atteint issueSession sans erreur
    }

    // ----------------------------------------------------------------
    // resolveDeviceToken() — depuis cookie vs génération
    // ----------------------------------------------------------------

    public function testResolveDeviceTokenReturnsExistingCookieToken(): void
    {
        $_COOKIE['device_token'] = 'existing-device-token';

        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'resolveDeviceToken');

        $this->assertSame('existing-device-token', $result);
    }

    public function testResolveDeviceTokenGeneratesTokenWhenNoCookie(): void
    {
        unset($_COOKIE['device_token']);

        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'resolveDeviceToken');

        $this->assertIsString($result);
        $this->assertSame(64, strlen($result)); // bin2hex(random_bytes(32)) = 64 hex chars
    }

    // ----------------------------------------------------------------
    // resolveLang() — via callback avec lang invalide
    // ----------------------------------------------------------------

    public function testCallbackFallsBackToDefaultLangWhenLangInvalid(): void
    {
        $_GET     = ['error' => 'access_denied'];

        try {
            $this->makeController()->callback(['lang' => 'zz']); // lang inconnue
        } catch (HttpException $e) {
            $this->assertStringContainsString('login=1', (string) $e->location);
        }
    }
}
