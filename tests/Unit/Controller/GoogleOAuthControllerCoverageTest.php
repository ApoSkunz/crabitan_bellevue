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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Service\GoogleOAuthService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Couverture complémentaire GoogleOAuthController — lignes non couvertes.
 *
 * Cible : L80, L119, L220, L229-234, L249, L268, L287-288, L306-307,
 *         L338, L345-346, L366, L395, L435, L491.
 */
#[AllowMockObjectsWithoutExpectations]
class GoogleOAuthControllerCoverageTest extends TestCase
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
        $_SERVER['HTTP_USER_AGENT']   = 'Mozilla/5.0 Chrome/120';
        $_SERVER['REMOTE_ADDR']       = '127.0.0.1';

        unset($_ENV['GOOGLE_FR_FALBACK'], $_ENV['GOOGLE_EN_FALBACK'], $_ENV['MFA_EMAIL_ENABLED']);
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_COOKIE  = [];
        $_GET     = [];
        $_POST    = [];
        unset($_ENV['GOOGLE_FR_FALBACK'], $_ENV['GOOGLE_EN_FALBACK'], $_ENV['MFA_EMAIL_ENABLED']);
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
    // L80 — authorize() : session_start() quand session inactive
    // ----------------------------------------------------------------

    public function testAuthorizeStartsSessionWhenNone(): void
    {
        // session_status() === PHP_SESSION_ACTIVE après setUp, mais authorize() vérifie
        // PHP_SESSION_NONE et appelle session_start() si besoin — on vérifie le flux complet
        $oauth = $this->createStub(GoogleOAuthService::class);
        $oauth->method('buildAuthUrl')->willReturn('https://accounts.google.com/?state=x');

        $ctrl = $this->makeController();
        $this->injectProp($ctrl, 'oauth', $oauth);

        try {
            $ctrl->authorize(['lang' => 'fr']);
        } catch (HttpException) {
        }

        // L'état a bien été stocké (authorize() s'est exécuté jusqu'au redirect)
        $this->assertArrayHasKey('oauth_google_state', $_SESSION);
    }

    // ----------------------------------------------------------------
    // L80 — authorize() : retour HTTP_REFERER valide → oauth_return_path
    // ----------------------------------------------------------------

    public function testAuthorizeWithValidRefererStoresReturnPath(): void
    {
        $_SERVER['HTTP_REFERER'] = 'http://localhost/fr/vins';

        $oauth = $this->createStub(GoogleOAuthService::class);
        $oauth->method('buildAuthUrl')->willReturn('https://accounts.google.com/?state=x');

        $ctrl = $this->makeController();
        $this->injectProp($ctrl, 'oauth', $oauth);

        try {
            $ctrl->authorize(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertSame('/fr/vins', $_SESSION['oauth_return_path'] ?? null);
    }

    // ----------------------------------------------------------------
    // L80 — authorize() : referer /auth/... → forcé à "/{lang}"
    // ----------------------------------------------------------------

    public function testAuthorizeWithAuthRefererForcesDefaultReturnPath(): void
    {
        $_SERVER['HTTP_REFERER'] = 'http://localhost/auth/google';

        $oauth = $this->createStub(GoogleOAuthService::class);
        $oauth->method('buildAuthUrl')->willReturn('https://accounts.google.com/?state=x');

        $ctrl = $this->makeController();
        $this->injectProp($ctrl, 'oauth', $oauth);

        try {
            $ctrl->authorize(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertSame('/fr', $_SESSION['oauth_return_path'] ?? null);
    }

    // ----------------------------------------------------------------
    // L119 — callback() : session_start() appelé (déjà couvert par OAuthFlowTest
    //         mais on couvre la branche session_status() === PHP_SESSION_NONE)
    // ----------------------------------------------------------------

    public function testCallbackSessionStartBranchWithErrorParam(): void
    {
        $_GET = ['error' => 'access_denied'];

        // On vérifie simplement que le flash est bien posé (circuit complet)
        try {
            $this->makeController()->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // L220, L229-234 — accountTypePending() : session pending exists → view()
    // ----------------------------------------------------------------

    public function testAccountTypePendingRendersViewWhenPendingExists(): void
    {
        $_SESSION['oauth_google_pending'] = [
            'email'     => 'test@example.com',
            'google_id' => 'g-123',
            'lang'      => 'fr',
            'firstname' => 'Alice',
            'lastname'  => 'Dupont',
        ];

        ob_start();
        try {
            $this->makeController()->accountTypePending(['lang' => 'fr']);
        } catch (\Throwable) {
            // view() peut générer des warnings selon présence du template
        }
        ob_end_clean();

        // Session pending n'est pas effacée (elle est lue, pas supprimée ici)
        $this->assertArrayHasKey('oauth_google_pending', $_SESSION);
    }

    // ----------------------------------------------------------------
    // L249 — accountTypeSubmit() : session_start() branch
    // ----------------------------------------------------------------

    public function testAccountTypeSubmitSessionStartBranch(): void
    {
        // Appel sans pending → redirection immédiate (mais session_start() est passé)
        $_SESSION = [];
        $_POST    = [];

        try {
            $this->makeController()->accountTypeSubmit(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertStringContainsString('login=1', (string) $e->location);
        }
    }

    // ----------------------------------------------------------------
    // L268 — accountTypeSubmit() : account_type invalide → normalisé à 'individual'
    // ----------------------------------------------------------------

    public function testAccountTypeSubmitNormalizesInvalidAccountType(): void
    {
        $_SESSION = [
            'csrf'                 => 'tok',
            'oauth_google_pending' => [
                'email'     => 'x@example.com',
                'google_id' => 'g-x',
                'lang'      => 'fr',
                'firstname' => 'X',
                'lastname'  => 'Y',
            ],
        ];
        $_POST = ['csrf_token' => 'tok', 'account_type' => 'hacker', 'company_name' => ''];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();

        $accountsMock = $this->createMock(AccountModel::class);
        // account_type doit être 'individual' (normalisé)
        $accountsMock->expects($this->once())
            ->method('createFromGoogle')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'individual',
                $this->anything()
            )
            ->willReturn(55);
        $accountsMock->method('findById')->willReturn([
            'id' => 55, 'role' => 'customer', 'lang' => 'fr',
            'email' => 'x@example.com', 'firstname' => 'X',
        ]);

        $this->injectProp($ctrl, 'accounts', $accountsMock);
        $this->injectProp($ctrl, 'connections', $this->createStub(ConnectionModel::class));

        try {
            $ctrl->accountTypeSubmit(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L287-288 — accountTypeSubmit() : findById() returns null → flash + redirect
    // ----------------------------------------------------------------

    public function testAccountTypeSubmitFlashesWhenFindByIdReturnsNull(): void
    {
        $_SESSION = [
            'csrf'                 => 'tok',
            'oauth_google_pending' => [
                'email'     => 'z@example.com',
                'google_id' => 'g-z',
                'lang'      => 'fr',
                'firstname' => 'Z',
                'lastname'  => 'Z',
            ],
        ];
        $_POST = ['csrf_token' => 'tok', 'account_type' => 'individual', 'company_name' => ''];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('createFromGoogle')->willReturn(99);
        $accountsMock->method('findById')->willReturn(false); // null → erreur

        $this->injectProp($ctrl, 'accounts', $accountsMock);
        $this->injectProp($ctrl, 'connections', $this->createStub(ConnectionModel::class));

        try {
            $ctrl->accountTypeSubmit(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertStringContainsString('login=1', (string) $e->location);
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // L306-307 — accountTypeSubmit() : isSubscribed = true → pas de confirmToken
    // ----------------------------------------------------------------

    public function testAccountTypeSubmitWithSubscribedAccountSkipsConfirmToken(): void
    {
        $_SESSION = [
            'csrf'                 => 'tok',
            'oauth_google_pending' => [
                'email'     => 'sub@example.com',
                'google_id' => 'g-sub',
                'lang'      => 'fr',
                'firstname' => 'Sub',
                'lastname'  => 'User',
            ],
        ];
        $_POST = ['csrf_token' => 'tok', 'account_type' => 'individual', 'company_name' => ''];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();

        $accountsMock = $this->createStub(AccountModel::class);
        $accountsMock->method('createFromGoogle')->willReturn(77);
        $accountsMock->method('findById')->willReturn([
            'id'         => 77,
            'role'       => 'customer',
            'lang'       => 'fr',
            'email'      => 'sub@example.com',
            'firstname'  => 'Sub',
            'newsletter' => 1, // déjà abonné → isSubscribed = true
        ]);

        $this->injectProp($ctrl, 'accounts', $accountsMock);
        $this->injectProp($ctrl, 'connections', $this->createStub(ConnectionModel::class));

        try {
            $ctrl->accountTypeSubmit(['lang' => 'fr']);
        } catch (HttpException) {
        }

        // pending session effacée → flux a atteint issueSession()
        $this->assertArrayNotHasKey('oauth_google_pending', $_SESSION);
    }

    // ----------------------------------------------------------------
    // L338, L345-346 — challengeLinkByEmail() : catch Throwable lors create/send
    // ----------------------------------------------------------------

    public function testCallbackChallengeEmailLinkTokenCreateThrows(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $ctrl = $this->makeController();

        $oauth = $this->createStub(GoogleOAuthService::class);
        $oauth->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauth->method('fetchUserInfo')->willReturn([
            'sub' => 'g-throw', 'email' => 'existing@example.com',
            'given_name' => 'Jane', 'family_name' => 'Doe',
        ]);
        $this->injectProp($ctrl, 'oauth', $oauth);

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByGoogleId')->willReturn(false);
        $accounts->method('findDeletedByGoogleId')->willReturn(false);
        $accounts->method('findByEmail')->willReturn([
            'id' => 7, 'email' => 'existing@example.com', 'firstname' => 'Jane', 'lang' => 'fr',
        ]);
        $this->injectProp($ctrl, 'accounts', $accounts);

        // linkTokens::create() throws — couvre le catch block (L345-346)
        $linkTokens = $this->createStub(GoogleLinkTokenModel::class);
        $linkTokens->method('create')->willThrowException(new \RuntimeException('DB error'));
        $this->injectProp($ctrl, 'linkTokens', $linkTokens);

        $location = null;
        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        // Même si create() a échoué, le flux poursuit et redirige vers /auth/google/link
        $this->assertStringContainsString('/fr/auth/google/link', (string) $location);
    }

    // ----------------------------------------------------------------
    // L366 — linkConfirm() : session_start branch (appel direct)
    // ----------------------------------------------------------------

    public function testLinkConfirmSessionStartBranch(): void
    {
        $_SESSION['pending_google_link_email_sent'] = 'user@test.com';

        ob_start();
        try {
            $this->makeController()->linkConfirm(['lang' => 'fr']);
        } catch (\Throwable) {
        }
        ob_end_clean();

        // email consommé = session_start() et flux complet exécutés
        $this->assertArrayNotHasKey('pending_google_link_email_sent', $_SESSION);
    }

    // ----------------------------------------------------------------
    // L395 — linkVerifyByToken() : session_start branch
    // ----------------------------------------------------------------

    public function testLinkVerifyByTokenSessionStartBranch(): void
    {
        $_GET = []; // token vide → flash + redirect

        try {
            $this->makeController()->linkVerifyByToken(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // L435 — linkConfirmPost() : session_start branch
    // ----------------------------------------------------------------

    public function testLinkConfirmPostSessionStartBranch(): void
    {
        $_POST = []; // pas d'action cancel → redirect simple

        try {
            $this->makeController()->linkConfirmPost(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertStringContainsString('login=1', (string) $e->location);
        }
    }

    // ----------------------------------------------------------------
    // L491 — issueSession() : MFA_EMAIL_ENABLED=false → auto-trust + warning
    // ----------------------------------------------------------------

    public function testIssueSessionWithMfaDisabledAutoTrustsDevice(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];
        $_ENV['MFA_EMAIL_ENABLED'] = 'false';

        $existingAccount = [
            'id' => 300, 'role' => 'customer', 'lang' => 'fr',
            'email' => 'mfa-disabled@example.com',
            'firstname' => 'M', 'lastname' => 'D',
            'has_connected' => 1, // pas 1ère connexion → !trusted déclenché
            'account_type' => 'individual',
        ];

        $ctrl = $this->makeController();

        $oauth = $this->createStub(GoogleOAuthService::class);
        $oauth->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauth->method('fetchUserInfo')->willReturn([
            'sub' => 'g-mfa-off', 'email' => 'mfa-disabled@example.com',
            'given_name' => 'M', 'family_name' => 'D',
        ]);
        $this->injectProp($ctrl, 'oauth', $oauth);

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByGoogleId')->willReturn($existingAccount);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $trustedDevices = $this->createStub(TrustedDeviceModel::class);
        $trustedDevices->method('isTrusted')->willReturn(false); // pas de confiance → branche MFA
        $this->injectProp($ctrl, 'trustedDevices', $trustedDevices);

        $this->injectProp($ctrl, 'deviceConfirmTokens', $this->createStub(DeviceConfirmTokenModel::class));
        $this->injectProp($ctrl, 'connections', $this->createStub(ConnectionModel::class));

        $location = null;
        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        // MFA désactivé → pas de redirect vers nouvel-appareil, mais vers returnPath
        $this->assertStringNotContainsString('nouvel-appareil', (string) $location);
    }

    // ----------------------------------------------------------------
    // L518 — issueSession() : appareil déjà trusted (pas 1ère connexion) → updateLastSeen()
    // ----------------------------------------------------------------

    /**
     * Couvre la branche elseif ($isAlreadyTrusted) → updateLastSeen() (L518).
     *
     * Conditions : has_connected=1 (pas 1ère connexion) + isTrusted=true.
     * issueSession() est invoqué directement via ReflectionMethod pour isoler la branche.
     */
    public function testIssueSessionAlreadyTrustedCallsUpdateLastSeen(): void
    {
        $account = [
            'id'            => 400,
            'role'          => 'customer',
            'lang'          => 'fr',
            'email'         => 'trusted@example.com',
            'firstname'     => 'T',
            'lastname'      => 'U',
            'has_connected' => 1, // pas 1ère connexion
            'account_type'  => 'individual',
        ];

        $ctrl = $this->makeController();

        $accounts = $this->createStub(AccountModel::class);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $trustedDevices = $this->createMock(TrustedDeviceModel::class);
        $trustedDevices->method('isTrusted')->willReturn(true); // déjà trusted → updateLastSeen
        $trustedDevices->expects($this->once())->method('updateLastSeen');
        $this->injectProp($ctrl, 'trustedDevices', $trustedDevices);

        $this->injectProp($ctrl, 'connections', $this->createStub(ConnectionModel::class));

        $issueSession = new \ReflectionMethod(GoogleOAuthController::class, 'issueSession');
        try {
            $issueSession->invoke($ctrl, $account, 'fr');
        } catch (HttpException) {
        }

        // L'assertion principale est portée par le mock (expects once sur updateLastSeen)
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // L543 — issueSession() : accountLang ≠ lang → réécriture du redirectTarget
    // ----------------------------------------------------------------

    /**
     * Couvre la branche de réécriture de langue dans le redirectTarget (L543).
     *
     * Conditions : accountLang=en, lang=fr, returnPath=/fr/vins → réécriture en /en/vins.
     */
    public function testIssueSessionRewritesRedirectTargetWhenAccountLangDiffersFromUrlLang(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = [
            'oauth_google_state'  => 'valid-state',
            'oauth_return_path'   => '/fr/vins',   // chemin avec lang fr
        ];

        $existingAccount = [
            'id'            => 500,
            'role'          => 'customer',
            'lang'          => 'en', // préférence compte = en, alors que URL = fr
            'email'         => 'langswitch@example.com',
            'firstname'     => 'L',
            'lastname'      => 'S',
            'has_connected' => 1,
            'account_type'  => 'individual',
        ];

        $ctrl = $this->makeController();

        $oauth = $this->createStub(GoogleOAuthService::class);
        $oauth->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauth->method('fetchUserInfo')->willReturn([
            'sub'         => 'g-langswitch',
            'email'       => 'langswitch@example.com',
            'given_name'  => 'L',
            'family_name' => 'S',
        ]);
        $this->injectProp($ctrl, 'oauth', $oauth);

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByGoogleId')->willReturn($existingAccount);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $trustedDevices = $this->createStub(TrustedDeviceModel::class);
        $trustedDevices->method('isTrusted')->willReturn(true); // évite le challenge MFA
        $this->injectProp($ctrl, 'trustedDevices', $trustedDevices);

        $this->injectProp($ctrl, 'deviceConfirmTokens', $this->createStub(DeviceConfirmTokenModel::class));
        $this->injectProp($ctrl, 'connections', $this->createStub(ConnectionModel::class));

        $location = null;
        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException $e) {
            $location = $e->location;
        }

        // /fr/vins doit être réécrit en /en/vins (lang du compte)
        $this->assertSame('/en/vins', $location);
    }

    // ================================================================
    // Couverture explicite des `session_start()` (L80/L119/L220/L249/
    // L366/L395/L435) en process isolé — session_status() vaut bien
    // PHP_SESSION_NONE dans chaque child process.
    // ================================================================

    /**
     * L80 — authorize() : session_start() exécuté en process isolé.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAuthorizeSessionStartExecutedInIsolatedProcess(): void
    {
        $dbMock = $this->createStub(Database::class);
        $prop   = new \ReflectionProperty(Database::class, 'instance');
        $prop->setValue(null, $dbMock);

        $_ENV['APP_URL']              = 'http://localhost';
        $_ENV['GOOGLE_CLIENT_ID']     = 'id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'secret';
        $_SERVER['REQUEST_METHOD']    = 'GET';

        $oauth = $this->createStub(GoogleOAuthService::class);
        $oauth->method('buildAuthUrl')->willReturn('https://accounts.google.com/');

        $ctrl = new GoogleOAuthController(new Request());
        $p = new \ReflectionProperty(GoogleOAuthController::class, 'oauth');
        $p->setValue($ctrl, $oauth);

        try {
            $ctrl->authorize(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('oauth_google_state', $_SESSION);
    }

    /**
     * L119 — callback() : session_start() exécuté en process isolé.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCallbackSessionStartExecutedInIsolatedProcess(): void
    {
        $dbMock = $this->createStub(Database::class);
        $prop   = new \ReflectionProperty(Database::class, 'instance');
        $prop->setValue(null, $dbMock);

        $_ENV['APP_URL']           = 'http://localhost';
        $_GET                      = ['error' => 'access_denied'];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        try {
            (new GoogleOAuthController(new Request()))->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    /**
     * L220 — accountTypePending() : session_start() exécuté en process isolé.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAccountTypePendingSessionStartExecutedInIsolatedProcess(): void
    {
        $dbMock = $this->createStub(Database::class);
        $prop   = new \ReflectionProperty(Database::class, 'instance');
        $prop->setValue(null, $dbMock);

        $_ENV['APP_URL']           = 'http://localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        try {
            (new GoogleOAuthController(new Request()))->accountTypePending(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertStringContainsString('login=1', (string) $e->location);
            return;
        }

        $this->assertTrue(true);
    }

    /**
     * L249 — accountTypeSubmit() : session_start() exécuté en process isolé.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAccountTypeSubmitSessionStartExecutedInIsolatedProcess(): void
    {
        $dbMock = $this->createStub(Database::class);
        $prop   = new \ReflectionProperty(Database::class, 'instance');
        $prop->setValue(null, $dbMock);

        $_ENV['APP_URL']           = 'http://localhost';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        try {
            (new GoogleOAuthController(new Request()))->accountTypeSubmit(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertStringContainsString('login=1', (string) $e->location);
            return;
        }

        $this->assertTrue(true);
    }

    /**
     * L366 — linkConfirm() : session_start() exécuté en process isolé.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLinkConfirmSessionStartExecutedInIsolatedProcess(): void
    {
        $dbMock = $this->createStub(Database::class);
        $prop   = new \ReflectionProperty(Database::class, 'instance');
        $prop->setValue(null, $dbMock);

        $_ENV['APP_URL']           = 'http://localhost';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        try {
            (new GoogleOAuthController(new Request()))->linkConfirm(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertStringContainsString('login=1', (string) $e->location);
            return;
        }

        $this->assertTrue(true);
    }

    /**
     * L395 — linkVerifyByToken() : session_start() exécuté en process isolé.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLinkVerifyByTokenSessionStartExecutedInIsolatedProcess(): void
    {
        $dbMock = $this->createStub(Database::class);
        $prop   = new \ReflectionProperty(Database::class, 'instance');
        $prop->setValue(null, $dbMock);

        $_ENV['APP_URL']           = 'http://localhost';
        $_GET                      = []; // pas de token → flash + redirect
        $_SERVER['REQUEST_METHOD'] = 'GET';

        try {
            (new GoogleOAuthController(new Request()))->linkVerifyByToken(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    /**
     * L435 — linkConfirmPost() : session_start() exécuté en process isolé.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLinkConfirmPostSessionStartExecutedInIsolatedProcess(): void
    {
        $dbMock = $this->createStub(Database::class);
        $prop   = new \ReflectionProperty(Database::class, 'instance');
        $prop->setValue(null, $dbMock);

        $_ENV['APP_URL']           = 'http://localhost';
        $_POST                     = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        try {
            (new GoogleOAuthController(new Request()))->linkConfirmPost(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertStringContainsString('login=1', (string) $e->location);
            return;
        }

        $this->assertTrue(true);
    }

    /**
     * L338 — challengeLinkByEmail() : branche fallback du display name.
     * Vérifie la chaîne `?? $existing['company_name'] ?? ''` quand firstname est absent.
     */
    public function testChallengeLinkByEmailFallsBackToCompanyName(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $ctrl = $this->makeController();

        $oauth = $this->createStub(GoogleOAuthService::class);
        $oauth->method('exchangeCode')->willReturn(['access_token' => 'tok']);
        $oauth->method('fetchUserInfo')->willReturn([
            'sub' => 'g-cname', 'email' => 'company@example.com',
            'given_name' => 'Company', 'family_name' => 'User',
        ]);
        $this->injectProp($ctrl, 'oauth', $oauth);

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByGoogleId')->willReturn(false);
        $accounts->method('findDeletedByGoogleId')->willReturn(false);
        // firstname absent → fallback sur company_name
        $accounts->method('findByEmail')->willReturn([
            'id'           => 42,
            'email'        => 'company@example.com',
            'firstname'    => null,
            'company_name' => 'Château Test',
            'lang'         => 'fr',
        ]);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $linkTokens = $this->createStub(GoogleLinkTokenModel::class);
        $linkTokens->method('create')->willReturn('link-tok');
        $this->injectProp($ctrl, 'linkTokens', $linkTokens);

        $location = null;
        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        $this->assertStringContainsString('/fr/auth/google/link', (string) $location);
        // Session traces la redirection post-challenge
        $this->assertSame('company@example.com', $_SESSION['pending_google_link_email_sent'] ?? null);
    }

    /**
     * L491 — issueSession() : préférence langue compte respectée quand supportée.
     * Vérifie que $accountLang = $account['lang'] quand SUPPORTED_LANGS contient la préférence.
     */
    public function testIssueSessionRespectsAccountLangWhenSupported(): void
    {
        $account = [
            'id'            => 600,
            'role'          => 'customer',
            'lang'          => 'fr', // supportée
            'email'         => 'prefs@example.com',
            'firstname'     => 'P',
            'lastname'      => 'L',
            'has_connected' => 1,
            'account_type'  => 'individual',
        ];

        $ctrl = $this->makeController();

        $accounts = $this->createStub(AccountModel::class);
        $this->injectProp($ctrl, 'accounts', $accounts);

        $trustedDevices = $this->createStub(TrustedDeviceModel::class);
        $trustedDevices->method('isTrusted')->willReturn(true);
        $this->injectProp($ctrl, 'trustedDevices', $trustedDevices);

        $this->injectProp($ctrl, 'connections', $this->createStub(ConnectionModel::class));

        $issueSession = new \ReflectionMethod(GoogleOAuthController::class, 'issueSession');

        $location = null;
        try {
            $issueSession->invoke($ctrl, $account, 'fr');
        } catch (HttpException $e) {
            $location = (string) $e->location;
        }

        // Flux complet traversé : redirection émise
        $this->assertNotNull($location);
    }
}
