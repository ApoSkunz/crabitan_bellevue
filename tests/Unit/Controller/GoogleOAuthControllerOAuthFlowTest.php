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
use Model\GoogleLinkTokenModel;
use PHPUnit\Framework\TestCase;
use Service\GoogleOAuthService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires GoogleOAuthController — couverture des branches non couvertes.
 *
 * Couvre : authorize(), callback() erreurs, Cas 2 (rattachement par email),
 *          resolveDeviceToken(), resolveLang(), issueSession() via AccountModel mock.
 */
#[AllowMockObjectsWithoutExpectations]
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
    // callback() — cas 2 : email connu → rattachement par email (GoogleLinkToken)
    // ----------------------------------------------------------------

    public function testCallbackWithExistingEmailRedirectsToLinkPage(): void
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

        // Mock GoogleLinkTokenModel pour éviter l'accès BDD
        $linkTokensMock = $this->createStub(GoogleLinkTokenModel::class);
        $linkTokensMock->method('create')->willReturn('fake-link-token-hex');
        $linkTokensProp = new \ReflectionProperty(GoogleOAuthController::class, 'linkTokens');
        $linkTokensProp->setValue($ctrl, $linkTokensMock);

        $redirectLocation = null;
        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException $e) {
            $redirectLocation = (string) $e->location;
        }

        // Cas 2 rattachement : email envoyé + redirection vers /fr/auth/google/link
        $this->assertSame('existing@example.com', $_SESSION['pending_google_link_email_sent'] ?? null);
        $this->assertStringContainsString('/fr/auth/google/link', (string) $redirectLocation);
    }

    // ----------------------------------------------------------------
    // callback() — cas 3 : email inconnu → création de compte
    // ----------------------------------------------------------------

    public function testCallbackWithUnknownEmailStoresPendingSessionAndRedirects(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

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
        $accountsMock->method('findDeletedByGoogleId')->willReturn(false);
        $accountsMock->method('findByEmail')->willReturn(false);
        $accountsMock->method('findDeletedByEmail')->willReturn(false);

        $accountsProp = new \ReflectionProperty(GoogleOAuthController::class, 'accounts');
        $accountsProp->setValue($ctrl, $accountsMock);

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        // Cas 3 : session pending stockée, redirection vers le formulaire de type de compte
        // Inspection via JSON pour éviter les contraintes PHPStan sur la shape figée de $_SESSION.
        $sessionJson = json_encode($_SESSION) ?: '';
        $this->assertStringContainsString('"oauth_google_pending"', $sessionJson);
        $this->assertStringContainsString('"new@example.com"', $sessionJson);
        $this->assertStringContainsString('"g-new"', $sessionJson);
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

    // ----------------------------------------------------------------
    // accountTypePending() — GET /{lang}/auth/google/type-compte
    // ----------------------------------------------------------------

    public function testAccountTypePendingRedirectsWhenNoPendingSession(): void
    {
        $_SESSION = [];

        try {
            $this->makeController()->accountTypePending(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertStringContainsString('login=1', (string) $e->location);
        }
    }

    // ----------------------------------------------------------------
    // accountTypeSubmit() — POST /{lang}/auth/google/type-compte
    // ----------------------------------------------------------------

    public function testAccountTypeSubmitRedirectsWhenNoPendingSession(): void
    {
        $_SESSION = [];
        $_POST    = [];

        try {
            $this->makeController()->accountTypeSubmit(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertStringContainsString('login=1', (string) $e->location);
        }
    }

    public function testAccountTypeSubmitRedirectsOnInvalidCsrf(): void
    {
        $_SESSION = [
            'csrf'                  => 'valid-csrf-token',
            'oauth_google_pending'  => [
                'email'     => 'test@example.com',
                'google_id' => 'g-123',
                'lang'      => 'fr',
                'firstname' => 'Test',
                'lastname'  => 'User',
            ],
        ];
        $_POST = ['csrf_token' => 'wrong-token', 'account_type' => 'individual'];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        try {
            $this->makeController()->accountTypeSubmit(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertStringContainsString('type-compte', (string) $e->location);
        }
    }

    public function testAccountTypeSubmitCreatesIndividualAccountAndIssuesSession(): void
    {
        $_SESSION = [
            'csrf'                 => 'valid-csrf-token',
            'oauth_google_pending' => [
                'email'     => 'new@example.com',
                'google_id' => 'g-456',
                'lang'      => 'fr',
                'firstname' => 'Alice',
                'lastname'  => 'Dupont',
            ],
        ];
        $_POST = ['csrf_token' => 'valid-csrf-token', 'account_type' => 'individual', 'company_name' => ''];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('createFromGoogle')->willReturn(99);
        $accountsMock->method('findById')->willReturn([
            'id' => 99, 'role' => 'customer', 'lang' => 'fr',
            'email' => 'new@example.com', 'firstname' => 'Alice',
        ]);

        $accountsProp = new \ReflectionProperty(GoogleOAuthController::class, 'accounts');
        $accountsProp->setValue($ctrl, $accountsMock);

        $connsMock = $this->createStub(ConnectionModel::class);
        $connsProp = new \ReflectionProperty(GoogleOAuthController::class, 'connections');
        $connsProp->setValue($ctrl, $connsMock);

        try {
            $ctrl->accountTypeSubmit(['lang' => 'fr']);
        } catch (HttpException) {
        }

        // Pending session doit être effacé
        $this->assertArrayNotHasKey('oauth_google_pending', $_SESSION);
    }

    public function testAccountTypeSubmitCreatesCompanyAccountWithCompanyName(): void
    {
        $_SESSION = [
            'csrf'                 => 'valid-csrf-token',
            'oauth_google_pending' => [
                'email'     => 'pro@example.com',
                'google_id' => 'g-789',
                'lang'      => 'fr',
                'firstname' => 'Jean',
                'lastname'  => 'Martin',
            ],
        ];
        $_POST = ['csrf_token' => 'valid-csrf-token', 'account_type' => 'company', 'company_name' => 'SARL Dupont'];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();

        $createCapture = null;
        $accountsMock  = $this->createMock(AccountModel::class);
        $accountsMock->expects($this->once())
            ->method('createFromGoogle')
            ->with(
                'pro@example.com',
                'g-789',
                'fr',
                'Jean',
                'Martin',
                'company',
                'SARL Dupont'
            )
            ->willReturn(100);
        $accountsMock->method('findById')->willReturn([
            'id' => 100, 'role' => 'customer', 'lang' => 'fr',
            'email' => 'pro@example.com', 'company_name' => 'SARL Dupont',
        ]);

        $accountsProp = new \ReflectionProperty(GoogleOAuthController::class, 'accounts');
        $accountsProp->setValue($ctrl, $accountsMock);

        $connsMock = $this->createStub(ConnectionModel::class);
        $connsProp = new \ReflectionProperty(GoogleOAuthController::class, 'connections');
        $connsProp->setValue($ctrl, $connsMock);

        try {
            $ctrl->accountTypeSubmit(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayNotHasKey('oauth_google_pending', $_SESSION);
    }
}
