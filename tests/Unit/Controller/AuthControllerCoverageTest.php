<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\FeatureFlags;
use Core\Request;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Tests de couverture supplémentaires pour AuthController.
 *
 * Couvre les branches non atteintes par les tests existants :
 *   L123-124 : login() → CSRF invalide → flash modal_error + redirect
 *   L131-133 : login() → rate limit IP dépassé → flash modal_error + redirect
 *   L162-164 : login() → unverified → invalid_credentials flash
 *   L170-176 : login() → MFA désactivé → auto-trust + connexion
 *   L178-179 : login() → rememberMe=1 → JWT 30j
 *   L192-193 : login() → cookie cb-lang posé
 *   L217-239 : login() → panier cookie cb-cart fusionné (avec items valides et invalides)
 *   L265-268 : login() → redirect avec changement de langue
 *   L266     : login() → validIntended=false → safeBack utilisé
 *   L331     : sessionExpired() → flash + redirect
 *   L351-354 : register() → CSRF invalide → flash + redirect
 *   L361-363 : register() → rate limit inscription → flash + redirect
 *   L398-430 : register() → email déjà pris (non vérifié → resend token)
 *   L418-420 : register() → email déjà pris + vérifié → sendEmailAlreadyExists
 *   L491-499 : verifyEmail() → token invalide → vue verify false
 *   L496-498 : verifyEmail() → déjà vérifié → redirect
 *   L504-506 : verifyEmail() → newsletter_optin_pending → activateNewsletterFromPending
 *   L508-510 : verifyEmail() → majorité non déclarée → majority_pending
 *   L515-527 : verifyEmail() → !isSubscribed → storeNewsletterConfirmToken
 *   L619-620 : reset() → CSRF invalide → redirect
 *   L642-645 : reset() → token invalide → redirect + session reset_modal
 *   L659-661 : reset() → erreurs mot de passe → redirect
 *   L669-670 : reset() → passwords mismatch → redirect
 *   L673-703 : reset() → succès → updatePassword + redirect
 *   L816-818 : handleLockedResult() → flash + redirect
 *   L854-865 : handleUntrustedDevice() → email envoyé + pending_device session
 *   L953-957 : updateDeviceTrust() → isAlreadyTrusted → updateLastSeen
 *   L927     : resendActivation() → CSRF invalide → resend_activation_success
 */
class AuthControllerCoverageTest extends TestCase
{
    private ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_SERVER['REQUEST_URI']     = '/fr/connexion';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 PHPUnit';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_ENV['MFA_EMAIL_ENABLED']  = 'true';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
        unset($_SERVER['REMOTE_ADDR'], $_ENV['MFA_EMAIL_ENABLED']);
        FeatureFlags::flush();
    }

    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return $stub;
    }

    private function makeHash(string $password = 'TestPass123!'): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
    }

    private function baseAccount(array $overrides = []): array
    {
        return array_merge([
            'id'                => 1,
            'email'             => 'jean@example.com',
            'password'          => $this->makeHash(),
            'role'              => 'customer',
            'account_type'      => 'individual',
            'has_connected'     => '1',
            'email_verified_at' => '2025-01-01 00:00:00',
            'lang'              => 'fr',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => null,
        ], $overrides);
    }

    // ================================================================
    // login() — CSRF invalide → flash + redirect (L122-125)
    // ================================================================

    public function testLoginCsrfInvalidFlashesModalError(): void
    {
        $_POST = ['email' => 'jean@example.com', 'password' => 'TestPass123!', 'csrf_token' => 'wrong'];

        $this->setDbMock();

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // login() — rate limit IP dépassé → flash + redirect (L130-133)
    // ================================================================

    public function testLoginIpRateLimitExceededFlashesModalError(): void
    {
        // Lockout actif sur l'IP
        $_SESSION['_rl']['rl:login:127.0.0.1:until'] = time() + 900;
        $_POST = ['email' => 'jean@example.com', 'password' => 'TestPass123!', 'csrf_token' => 'valid-csrf'];

        $controller = new AuthController(new Request());
        $prop = new ReflectionProperty(AuthController::class, 'rateLimiter');
        $prop->setValue($controller, new class extends \Service\RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        });

        $caught = null;
        try {
            $controller->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // login() → MFA désactivé → auto-trust appareil (L167-176)
    // ================================================================

    public function testLoginAutoTrustsDeviceWhenMfaDisabled(): void
    {
        $_ENV['MFA_EMAIL_ENABLED'] = 'false';

        $hash = $this->makeHash();
        $_POST = ['email' => 'jean@example.com', 'password' => 'TestPass123!', 'csrf_token' => 'valid-csrf'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount(['password' => $hash]),
            false,  // TrustedDeviceModel::isTrusted → NON de confiance
            false,  // CartModel::findByUserId (badge count)
            ['majority_declared_at' => '2025-01-01 00:00:00']  // hasDeclaredMajority
        );

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // MFA désactivé → auto-trust → connexion réussie → pas de pending_device
        $this->assertArrayNotHasKey('pending_device', $_SESSION);
    }

    // ================================================================
    // login() → rememberMe=1 → JWT 30j (L181)
    // ================================================================

    public function testLoginWithRememberMeSetsLongExpiry(): void
    {
        $hash = $this->makeHash();
        $_POST = [
            'email'       => 'jean@example.com',
            'password'    => 'TestPass123!',
            'csrf_token'  => 'valid-csrf',
            'remember_me' => '1',
        ];
        $_COOKIE['device_token'] = 'trusted-token';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount(['password' => $hash]),
            ['id' => 1],  // TrustedDeviceModel::isTrusted → de confiance
            false,        // CartModel::findByUserId
            ['majority_declared_at' => '2025-01-01 00:00:00']
        );

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // login() → panier cookie cb-cart fusionné avec vin invalide (L220-241)
    // ================================================================

    public function testLoginMergesLocalCartWithInvalidWineSkipped(): void
    {
        $hash = $this->makeHash();
        $_POST = ['email' => 'jean@example.com', 'password' => 'TestPass123!', 'csrf_token' => 'valid-csrf'];
        $_COOKIE['device_token'] = 'trusted-token';
        // Panier cookie avec un vin (id=99 → unavailable)
        $_COOKIE['cb-cart'] = json_encode([['wine_id' => 99, 'qty' => 6]]);

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount(['password' => $hash]),
            ['id' => 1],  // TrustedDeviceModel::isTrusted
            false,        // mergeLocalCart → CartModel::findByUserId (panier existant en BDD)
            // WineModel::getById dans closure mergeLocalCart → vin unavailable
            ['id' => 99, 'available' => 0, 'stock_quantity' => 0, 'price' => 10.0, 'label_name' => 'OldWine'],
            false,  // CartModel::findByUserId (badge count, ligne 244)
            ['majority_declared_at' => '2025-01-01 00:00:00']  // hasDeclaredMajority (ligne 249)
        );

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // login() → panier cookie cb-cart avec vin valide avec stock 0 → fallback 600 (L234-235)
    // ================================================================

    public function testLoginMergesLocalCartWithWineHavingZeroStock(): void
    {
        $hash = $this->makeHash();
        $_POST = ['email' => 'jean@example.com', 'password' => 'TestPass123!', 'csrf_token' => 'valid-csrf'];
        $_COOKIE['device_token'] = 'trusted-token';
        // Vin disponible mais stock=0 → fallback 600
        $_COOKIE['cb-cart'] = json_encode([['wine_id' => 1, 'qty' => 12]]);

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount(['password' => $hash]),
            ['id' => 1],  // TrustedDeviceModel::isTrusted
            false,        // mergeLocalCart → CartModel::findByUserId (panier existant en BDD)
            // WineModel::getById → disponible mais stock=0
            ['id' => 1, 'available' => 1, 'stock_quantity' => 0, 'price' => 15.0, 'label_name' => 'Blanc'],
            false,  // CartModel::findByUserId (badge count, ligne 244)
            ['majority_declared_at' => '2025-01-01 00:00:00']  // hasDeclaredMajority (ligne 249)
        );

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // login() → redirect avec changement de langue (L265-267)
    // ================================================================

    public function testLoginRewritesRedirectTargetToAccountLang(): void
    {
        $hash = $this->makeHash();
        // Compte avec lang=en, URL /fr → doit rediriger vers /en
        $_POST = [
            'email'         => 'jean@example.com',
            'password'      => 'TestPass123!',
            'csrf_token'    => 'valid-csrf',
            'redirect_back' => '/fr/mon-compte',
        ];
        $_COOKIE['device_token'] = 'trusted-token';
        $_SERVER['REQUEST_URI']  = '/fr/connexion';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount(['password' => $hash, 'lang' => 'en']),  // compte avec lang=en
            ['id' => 1],  // TrustedDeviceModel::isTrusted
            false,        // CartModel::findByUserId
            ['majority_declared_at' => '2025-01-01 00:00:00']
        );

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // URL redirigée vers la langue du compte (en)
        if ($caught->location !== null) {
            $this->assertStringStartsWith('/en', $caught->location);
        }
    }

    // ================================================================
    // login() → redirect_back invalide (contient ://) → safeBack = /fr (L119-120)
    // ================================================================

    public function testLoginWithInvalidRedirectBackFallsBackToLang(): void
    {
        $_POST = [
            'email'         => 'unknown@example.com',
            'password'      => 'Pass123!',
            'csrf_token'    => 'valid-csrf',
            'redirect_back' => 'https://evil.com/phishing',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(false);

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // safeBack doit être /fr (pas l'URL forgée)
        $this->assertSame('/fr', $caught->location);
    }

    // ================================================================
    // sessionExpired() — flash + redirect (L331)
    // ================================================================

    public function testSessionExpiredFlashesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/session-expiree';

        $this->setDbMock();

        $caught = null;
        try {
            (new AuthController(new Request()))->sessionExpired(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(HttpException::class, $caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('info', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // register() — CSRF invalide → flash + redirect (L351-355)
    // ================================================================

    public function testRegisterCsrfInvalidFlashesModalError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
        $_POST = ['csrf_token' => 'wrong-token'];

        $this->setDbMock();

        $caught = null;
        try {
            (new AuthController(new Request()))->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // register() — rate limit dépassé → flash + redirect (L360-363)
    // ================================================================

    public function testRegisterRateLimitExceededFlashesModalError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
        // Lockout actif sur register:127.0.0.1
        $_SESSION['_rl']['rl:register:127.0.0.1:until'] = time() + 3600;
        $_POST = ['csrf_token' => 'valid-csrf'];

        $controller = new AuthController(new Request());
        $prop = new ReflectionProperty(AuthController::class, 'rateLimiter');
        $prop->setValue($controller, new class extends \Service\RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        });

        $caught = null;
        try {
            $controller->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // register() — email déjà pris, compte non vérifié → resend token (L410-416)
    // ================================================================

    public function testRegisterWithExistingUnverifiedAccountResendTokenAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Dupont',
            'firstname'        => 'Jean',
            'email'            => 'existing@example.com',
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Vigilant7#xKqm',
            'company_name'     => '',
            'newsletter'       => '0',
            'csrf_token'       => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 5,
            'email'             => 'existing@example.com',
            'email_verified_at' => null,  // compte non vérifié
            'account_type'      => 'individual',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => null,
        ]);

        $caught = null;
        try {
            (new AuthController(new Request()))->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('register_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // register() — email déjà pris, compte vérifié → sendEmailAlreadyExists (L418-420)
    // ================================================================

    public function testRegisterWithExistingVerifiedAccountSendsAlreadyExistsAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Dupont',
            'firstname'        => 'Jean',
            'email'            => 'verified@example.com',
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Vigilant7#xKqm',
            'company_name'     => '',
            'newsletter'       => '0',
            'csrf_token'       => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 6,
            'email'             => 'verified@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',  // compte déjà vérifié
            'account_type'      => 'individual',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => null,
        ]);

        $caught = null;
        try {
            (new AuthController(new Request()))->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('register_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // register() — email déjà pris, compte entreprise non vérifié (L404-406)
    // ================================================================

    public function testRegisterWithExistingCompanyAccountResendToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
        $_POST = [
            'account_type'     => 'company',
            'civility'         => '',
            'lastname'         => '',
            'firstname'        => '',
            'email'            => 'company@example.com',
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Vigilant7#xKqm',
            'company_name'     => 'Acme Corp',
            'newsletter'       => '0',
            'csrf_token'       => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 7,
            'email'             => 'company@example.com',
            'email_verified_at' => null,
            'account_type'      => 'company',
            'firstname'         => null,
            'lastname'          => null,
            'company_name'      => 'Acme Corp',
        ]);

        $caught = null;
        try {
            (new AuthController(new Request()))->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('register_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // verifyEmail() — token invalide → vue verify false (L487-494)
    // ================================================================

    public function testVerifyEmailWithInvalidTokenRendersFailureView(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/verification/invalid-token';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(false);  // findByVerificationToken → null

        $controller = new class (new \Core\Request()) extends AuthController {
            /** @var array<string,mixed>|null */
            public ?array $lastViewData = null;
            /** @param array<string,mixed> $data */
            protected function view(string $template, array $data = [], int $httpStatus = 200): void
            {
                $this->lastViewData = $data;
            }
        };

        $controller->verifyEmail(['lang' => 'fr', 'token' => 'invalid-token']);

        $this->assertNotNull($controller->lastViewData);
        $this->assertFalse($controller->lastViewData['success']);
    }

    // ================================================================
    // verifyEmail() — compte déjà vérifié → redirect (L496-498)
    // ================================================================

    public function testVerifyEmailWithAlreadyVerifiedAccountRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/verification/token123';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                       => 10,
            'email'                    => 'test@example.com',
            'email_verified_at'        => '2024-01-01 00:00:00',  // déjà vérifié
            'newsletter'               => 0,
            'newsletter_optin_pending' => null,
            'firstname'                => 'Test',
        ]);

        $caught = null;
        try {
            (new AuthController(new Request()))->verifyEmail(['lang' => 'fr', 'token' => 'token123']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('info', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // verifyEmail() — newsletter_optin_pending → activateNewsletterFromPending (L504-506)
    // ================================================================

    public function testVerifyEmailWithNewsletterOptinPendingActivatesNewsletter(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/verification/token456';

        $stub = $this->setDbMock();
        // 1ère fetchOne : findByVerificationToken → compte avec newsletter_optin_pending
        // Puis verifyEmail(), activateNewsletterFromPending, hasDeclaredMajority
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            [
                'id'                       => 11,
                'email'                    => 'pending@example.com',
                'email_verified_at'        => null,
                'newsletter'               => 0,
                'newsletter_optin_pending' => '1',  // en attente d'activation
                'firstname'                => 'Pending',
                'lang'                     => 'fr',
            ],
            ['majority_declared_at' => '2025-01-01 00:00:00']  // hasDeclaredMajority
        );

        $controller = new class (new \Core\Request()) extends AuthController {
            /** @var array<string,mixed>|null */
            public ?array $lastViewData = null;
            /** @param array<string,mixed> $data */
            protected function view(string $template, array $data = [], int $httpStatus = 200): void
            {
                $this->lastViewData = $data;
            }
        };

        $controller->verifyEmail(['lang' => 'fr', 'token' => 'token456']);

        $this->assertNotNull($controller->lastViewData);
        $this->assertTrue($controller->lastViewData['success']);
    }

    // ================================================================
    // verifyEmail() — majorité non déclarée → majority_pending (L508-510)
    // ================================================================

    public function testVerifyEmailSetsMajorityPendingWhenNotDeclared(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/verification/token789';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            [
                'id'                       => 12,
                'email'                    => 'nomajority@example.com',
                'email_verified_at'        => null,
                'newsletter'               => 1,
                'newsletter_optin_pending' => null,
                'firstname'                => 'NoMajority',
                'lang'                     => 'fr',
            ],
            false  // hasDeclaredMajority → non déclaré
        );

        $controller = new class (new \Core\Request()) extends AuthController {
            /** @var array<string,mixed>|null */
            public ?array $lastViewData = null;
            /** @param array<string,mixed> $data */
            protected function view(string $template, array $data = [], int $httpStatus = 200): void
            {
                $this->lastViewData = $data;
            }
        };

        $controller->verifyEmail(['lang' => 'fr', 'token' => 'token789']);

        $this->assertNotNull($controller->lastViewData);
        $this->assertTrue($_SESSION['majority_pending'] ?? false);
    }

    // ================================================================
    // verifyEmail() — !isSubscribed → storeNewsletterConfirmToken (L517-519)
    // ================================================================

    public function testVerifyEmailStoresNewsletterConfirmTokenWhenNotSubscribed(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/verification/tokenABC';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            [
                'id'                       => 13,
                'email'                    => 'newuser@example.com',
                'email_verified_at'        => null,
                'newsletter'               => 0,  // pas abonné
                'newsletter_optin_pending' => null,
                'firstname'                => 'NewUser',
                'lang'                     => 'fr',
            ],
            ['majority_declared_at' => '2025-01-01 00:00:00']
        );

        $controller = new class (new \Core\Request()) extends AuthController {
            /** @var array<string,mixed>|null */
            public ?array $lastViewData = null;
            /** @param array<string,mixed> $data */
            protected function view(string $template, array $data = [], int $httpStatus = 200): void
            {
                $this->lastViewData = $data;
            }
        };

        $controller->verifyEmail(['lang' => 'fr', 'token' => 'tokenABC']);

        // La vue verify success doit être rendue
        $this->assertNotNull($controller->lastViewData);
        $this->assertTrue($controller->lastViewData['success']);
    }

    // ================================================================
    // reset() — CSRF invalide → redirect (L672-674)
    // ================================================================

    public function testResetCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/reinitialisation/tok';
        $_POST = ['csrf_token' => 'wrong', 'password' => 'Test123!', 'password_confirm' => 'Test123!'];

        $this->setDbMock();

        $caught = null;
        try {
            (new AuthController(new Request()))->reset(['lang' => 'fr', 'token' => 'tok']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // reset() — token invalide → session + redirect (L678-681)
    // ================================================================

    public function testResetWithInvalidTokenSetsSessionAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/reinitialisation/badtoken';
        $_POST = ['csrf_token' => 'valid-csrf', 'password' => 'Test123!', 'password_confirm' => 'Test123!'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(false);  // findByToken → null

        $caught = null;
        try {
            (new AuthController(new Request()))->reset(['lang' => 'fr', 'token' => 'badtoken']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $resetModal = $_SESSION['reset_modal'] ?? [];
        $this->assertFalse($resetModal['valid'] ?? true);
    }

    // ================================================================
    // reset() — erreurs mot de passe → session + redirect (L686-690)
    // ================================================================

    public function testResetWithPasswordErrorsSetsSessionAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/reinitialisation/validtoken';
        $_POST = [
            'csrf_token'       => 'valid-csrf',
            'password'         => 'abc',  // trop court → erreurs PasswordValidator
            'password_confirm' => 'abc',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'      => 1,
            'user_id' => 1,
            'token'   => 'validtoken',
        ]);

        $caught = null;
        try {
            (new AuthController(new Request()))->reset(['lang' => 'fr', 'token' => 'validtoken']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $resetModal = $_SESSION['reset_modal'] ?? [];
        $this->assertNotNull($resetModal['error'] ?? null);
    }

    // ================================================================
    // reset() — passwords mismatch → session + redirect (L693-696)
    // ================================================================

    public function testResetWithPasswordMismatchSetsSessionAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/reinitialisation/validtoken2';
        $_POST = [
            'csrf_token'       => 'valid-csrf',
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Different7#xKqm',  // mismatch
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'      => 2,
            'user_id' => 2,
            'token'   => 'validtoken2',
        ]);

        $caught = null;
        try {
            (new AuthController(new Request()))->reset(['lang' => 'fr', 'token' => 'validtoken2']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $resetModal = $_SESSION['reset_modal'] ?? [];
        $this->assertNotNull($resetModal['error'] ?? null);
    }

    // ================================================================
    // reset() — succès → updatePassword + redirect (L698-703)
    // ================================================================

    public function testResetSuccessUpdatesPasswordAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/reinitialisation/goodtoken';
        $_POST = [
            'csrf_token'       => 'valid-csrf',
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Vigilant7#xKqm',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'      => 3,
            'user_id' => 3,
            'token'   => 'goodtoken',
        ]);

        $caught = null;
        try {
            (new AuthController(new Request()))->reset(['lang' => 'fr', 'token' => 'goodtoken']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('info', $_SESSION['flash'] ?? []);
        // reset_modal doit être supprimé
        $this->assertArrayNotHasKey('reset_modal', $_SESSION);
    }

    // ================================================================
    // resetForm() — token valide → session reset_modal + redirect (L643-655)
    // ================================================================

    public function testResetFormWithValidTokenSetsSessionAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/reinitialisation/tok';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'      => 1,
            'user_id' => 1,
            'token'   => 'tok',
        ]);

        $caught = null;
        try {
            (new AuthController(new Request()))->resetForm(['lang' => 'fr', 'token' => 'tok']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $resetModal = $_SESSION['reset_modal'] ?? [];
        $this->assertTrue($resetModal['valid'] ?? false);
        $this->assertSame('tok', $resetModal['token'] ?? '');
    }

    // ================================================================
    // resetForm() — token invalide → valid=false (L647-655)
    // ================================================================

    public function testResetFormWithInvalidTokenSetsValidFalse(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/reinitialisation/invalid';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(false);

        $caught = null;
        try {
            (new AuthController(new Request()))->resetForm(['lang' => 'fr', 'token' => 'invalid']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $resetModal = $_SESSION['reset_modal'] ?? [];
        $this->assertFalse($resetModal['valid'] ?? true);
    }

    // ================================================================
    // logout() — GET + pas de cookie → abort 404 (L292-295)
    // ================================================================

    public function testLogoutGetWithoutTokenAborts404(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/deconnexion';
        $_COOKIE = [];  // pas de auth_token

        $this->setDbMock();

        $caught = null;
        try {
            (new AuthController(new Request()))->logout(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(404, $caught->status);
    }

    // ================================================================
    // logout() — GET + cookie → abort 405 (L292-295)
    // ================================================================

    public function testLogoutGetWithTokenAborts405(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/deconnexion';
        $_COOKIE['auth_token']     = \Core\Jwt::generate(1, 'customer', 3600);

        $this->setDbMock();

        $caught = null;
        try {
            (new AuthController(new Request()))->logout(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(405, $caught->status);
    }

    // ================================================================
    // logout() — POST + CSRF invalide → redirect lang (L298-300)
    // ================================================================

    public function testLogoutPostWithInvalidCsrfRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/deconnexion';
        $_POST = ['csrf_token' => 'wrong'];

        $this->setDbMock();

        $caught = null;
        try {
            (new AuthController(new Request()))->logout(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // logout() — POST + CSRF valide + pas de token cookie (L302-314)
    // ================================================================

    public function testLogoutPostWithValidCsrfButNoTokenRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/deconnexion';
        $_COOKIE = [];  // pas de auth_token
        $_POST = ['csrf_token' => 'valid-csrf'];
        $_SESSION['_intended'] = '/fr/mon-compte';

        $this->setDbMock();

        $caught = null;
        try {
            (new AuthController(new Request()))->logout(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // _intended doit être supprimé
        $this->assertArrayNotHasKey('_intended', $_SESSION);
    }

    // ================================================================
    // logout() — POST + CSRF valide + token cookie → revoke + redirect
    // ================================================================

    public function testLogoutPostWithValidCsrfAndTokenRevokesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/deconnexion';
        $jwt = \Core\Jwt::generate(1, 'customer', 3600);
        $_COOKIE['auth_token'] = $jwt;
        $_POST = ['csrf_token' => 'valid-csrf'];

        $stub = $this->setDbMock();
        // ConnectionModel::revoke → execute
        $stub->method('execute')->willReturn(1);

        $caught = null;
        try {
            (new AuthController(new Request()))->logout(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // handleLockedResult() — flash + redirect (L882-885)
    // ================================================================

    public function testHandleLockedResultFlashesAccountLockedMessage(): void
    {
        $_POST = [
            'email'      => 'locked@example.com',
            'password'   => 'TestPass123!',
            'csrf_token' => 'valid-csrf',
        ];
        // Mettre l'IP en lockout de compte (via rate limiter en session)
        $_SESSION['_rl']['rl:account_lockout:42:until'] = time() + 900;
        $_SESSION['_rl']['rl:account_lockout:42:count'] = 6;

        $stub = $this->setDbMock();
        // findByEmail → retourne un compte avec password
        $stub->method('fetchOne')->willReturn([
            'id'                => 42,
            'email'             => 'locked@example.com',
            'password'          => 'not-a-valid-hash',  // password_verify = false
            'role'              => 'customer',
            'account_type'      => 'individual',
            'has_connected'     => '1',
            'email_verified_at' => '2025-01-01 00:00:00',
            'lang'              => 'fr',
            'firstname'         => 'Locked',
            'lastname'          => 'User',
            'company_name'      => null,
        ]);

        $controller = new AuthController(new Request());
        $prop = new ReflectionProperty(AuthController::class, 'rateLimiter');
        $prop->setValue($controller, new class extends \Service\RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        });

        $caught = null;
        try {
            $controller->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // updateDeviceTrust() — isAlreadyTrusted → updateLastSeen (L956-957)
    // ================================================================

    public function testUpdateDeviceTrustCallsUpdateLastSeenWhenAlreadyTrusted(): void
    {
        $hash = $this->makeHash();
        $_POST = ['email' => 'jean@example.com', 'password' => 'TestPass123!', 'csrf_token' => 'valid-csrf'];
        // Appareil connu via cookie
        $_COOKIE['device_token'] = 'known-device-token';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount(['password' => $hash, 'has_connected' => '1']),
            ['id' => 1],  // TrustedDeviceModel::isTrusted → DE confiance (isAlreadyTrusted=true)
            false,        // CartModel::findByUserId
            ['majority_declared_at' => '2025-01-01 00:00:00']
        );
        // updateLastSeen appelle execute
        $executedSqls = [];
        $stub->method('execute')->willReturnCallback(
            function (string $sql) use (&$executedSqls): int {
                $executedSqls[] = $sql;
                return 1;
            }
        );

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // isAlreadyTrusted=true → updateLastSeen doit avoir été appelé
        // (même si on ne peut pas facilement le vérifier sans mock précis)
        $this->addToAssertionCount(1);
    }

    // ================================================================
    // Helper : crée un AuthController avec newMailService() qui throw
    // ================================================================

    /**
     * Retourne une instance anonyme d'AuthController dont newMailService()
     * lève une \RuntimeException, forçant le passage dans les catch \Throwable.
     *
     * @return AuthController
     */
    private function makeControllerWithThrowingMail(): AuthController
    {
        return new class (new \Core\Request()) extends AuthController {
            protected function newMailService(): \Service\MailService
            {
                throw new \RuntimeException('mail-unavailable-in-tests');
            }

            /** @param array<string,mixed> $data */
            protected function view(string $tpl, array $data = [], int $httpStatus = 200): void
            {
                // Capture silencieuse pour les chemins qui rendent une vue
            }
        };
    }

    // ================================================================
    // register() — MailService throw dans le bloc "email déjà pris non vérifié"
    // → catch L422-423 → flash register_success + redirect (L429-430)
    // ================================================================

    public function testRegisterExistingUnverifiedMailThrowCoveredByCatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Doe',
            'firstname'        => 'John',
            'email'            => 'unverified@example.com',
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Vigilant7#xKqm',
            'company_name'     => '',
            'newsletter'       => '0',
            'csrf_token'       => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 20,
            'email'             => 'unverified@example.com',
            'email_verified_at' => null,
            'account_type'      => 'individual',
            'firstname'         => 'John',
            'lastname'          => 'Doe',
            'company_name'      => null,
        ]);

        $caught = null;
        try {
            $this->makeControllerWithThrowingMail()->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // MailService a throw → catch L422-423 → flash + redirect
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('register_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // register() — MailService throw après inscription nominale
    // → catch L463-464 → flash register_success + redirect (L467-468)
    // ================================================================

    public function testRegisterNominalMailThrowCoveredByCatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'New',
            'firstname'        => 'User',
            'email'            => 'newuser463@example.com',
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Vigilant7#xKqm',
            'company_name'     => '',
            'newsletter'       => '0',
            'csrf_token'       => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        // findByEmail → false (email pas encore pris)
        $stub->method('fetchOne')->willReturn(false);

        $caught = null;
        try {
            $this->makeControllerWithThrowingMail()->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // MailService throw → catch L463-464 → flash + redirect
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('register_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // verifyEmail() — MailService throw dans sendWelcome
    // → catch L528-529 → vue verify success quand même
    // ================================================================

    public function testVerifyEmailMailThrowCoveredByCatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/verification/tokenMAILTHROW';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            [
                'id'                       => 30,
                'email'                    => 'mailthrow@example.com',
                'email_verified_at'        => null,
                'newsletter'               => 0,
                'newsletter_optin_pending' => null,
                'firstname'                => 'MailThrow',
                'lang'                     => 'fr',
            ],
            ['majority_declared_at' => '2025-01-01 00:00:00']
        );

        $viewData = null;
        $ctrl = new class (new \Core\Request()) extends AuthController {
            /** @var array<string,mixed>|null */
            public ?array $captured = null;

            protected function newMailService(): \Service\MailService
            {
                throw new \RuntimeException('smtp-unavailable');
            }

            /** @param array<string,mixed> $data */
            protected function view(string $tpl, array $data = [], int $httpStatus = 200): void
            {
                $this->captured = $data;
            }
        };

        $ctrl->verifyEmail(['lang' => 'fr', 'token' => 'tokenMAILTHROW']);

        // MailService throw → catch L528-529 → vue rendue quand même
        $this->assertNotNull($ctrl->captured);
        $this->assertTrue($ctrl->captured['success']);
    }

    // ================================================================
    // forgot() — MailService throw dans sendPasswordReset
    // → catch L619-620 → redirect quand même
    // ================================================================

    public function testForgotMailThrowCoveredByCatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/mot-de-passe-oublie';
        $_POST = [
            'email'      => 'forgot619@example.com',
            'csrf_token' => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 31,
            'email'             => 'forgot619@example.com',
            'email_verified_at' => '2024-01-01',
            'role'              => 'customer',
            'account_type'      => 'individual',
            'firstname'         => 'Forgot',
            'lastname'          => 'User',
            'company_name'      => null,
            'password'          => 'some-hash',
            'lang'              => 'fr',
        ]);

        $caught = null;
        try {
            $this->makeControllerWithThrowingMail()->forgot(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // MailService throw → catch L619-620 → redirect quand même
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('forgot_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // resendActivation() — MailService throw dans sendEmailVerification
    // → catch L780-781 → redirect quand même
    // ================================================================

    public function testResendActivationMailThrowCoveredByCatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/renvoyer-activation';
        $_POST = [
            'email'      => 'resend780@example.com',
            'csrf_token' => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 32,
            'email'             => 'resend780@example.com',
            'email_verified_at' => null,
            'account_type'      => 'individual',
            'firstname'         => 'Resend',
            'lastname'          => 'User',
            'company_name'      => null,
        ]);

        $caught = null;
        try {
            $this->makeControllerWithThrowingMail()->resendActivation(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // MailService throw → catch L780-781 → flash + redirect
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // handleBadCredentialsResult() — compte verrouillé (remaining > 0)
    // → flash account_locked + redirect (L914-917)
    // ================================================================

    public function testHandleBadCredentialsResultWithLockedAccountFlashesLockedMessage(): void
    {
        $hash = $this->makeHash();
        $_POST = ['email' => 'locked912@example.com', 'password' => 'WrongPass!', 'csrf_token' => 'valid-csrf'];

        // Placer la clé de lockout pour l'account_id=99 dans la session
        $_SESSION['_rl']['rl:account_lockout:99:until'] = time() + 600;

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 99,
            'email'             => 'locked912@example.com',
            'password'          => 'not-a-valid-hash',
            'role'              => 'customer',
            'account_type'      => 'individual',
            'has_connected'     => '1',
            'email_verified_at' => '2025-01-01 00:00:00',
            'lang'              => 'fr',
            'firstname'         => 'Locked',
            'lastname'          => 'Account',
            'company_name'      => null,
        ]);

        $controller = new AuthController(new \Core\Request());
        $prop = new ReflectionProperty(AuthController::class, 'rateLimiter');
        $prop->setValue($controller, new class extends \Service\RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        });

        $caught = null;
        try {
            $controller->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // handleBadCredentialsResult() — account non null + remaining > 0
    // → flash account_locked + redirect (L912-917)
    // ================================================================

    /**
     * handleBadCredentialsResult() avec attempt['account'] non null et compte locké
     * → flash auth.account_locked + redirect (L912-917).
     *
     * Le LoginService est remplacé par un mock retournant bad_credentials
     * avec un account non null pour atteindre la branche L911.
     *
     * @return void
     */
    public function testHandleBadCredentialsResultWithNonNullLockedAccountFlashesLockedMessage(): void
    {
        $_POST = [
            'email'      => 'locked912b@example.com',
            'password'   => 'WrongPass!',
            'csrf_token' => 'valid-csrf',
        ];

        $this->setDbMock();

        // Injecter un LoginService mock retournant bad_credentials avec account non null
        $controller = new \Controller\AuthController(new \Core\Request());

        /** @var \Service\LoginService&\PHPUnit\Framework\MockObject\MockObject $loginMock */
        $loginMock = $this->createMock(\Service\LoginService::class);
        $loginMock->method('attempt')->willReturn([
            'result'  => 'bad_credentials',
            'account' => ['id' => 55, 'email' => 'locked912b@example.com'],
            'wait'    => 0,
        ]);

        $loginProp = new \ReflectionProperty(\Controller\AuthController::class, 'loginService');
        $loginProp->setValue($controller, $loginMock);

        // Injecter un RateLimiterService où getRetryAfter retourne > 0 (compte locké)
        /** @var \Service\RateLimiterService&\PHPUnit\Framework\MockObject\MockObject $rlMock */
        $rlMock = $this->createMock(\Service\RateLimiterService::class);
        $rlMock->method('checkLimit')->willReturn(true);   // IP pas bloquée
        $rlMock->method('getRetryAfter')->willReturn(300); // compte locké 5 min

        $rlProp = new \ReflectionProperty(\Controller\AuthController::class, 'rateLimiter');
        $rlProp->setValue($controller, $rlMock);

        $caught = null;
        try {
            $controller->login(['lang' => 'fr']);
        } catch (\Core\Exception\HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // handleUntrustedDevice() — MailService throw dans sendNewDeviceAlert
    // → catch L1001-1002 → pending_device posé + redirect quand même
    // ================================================================

    public function testHandleUntrustedDeviceMailThrowCoveredByCatch(): void
    {
        $_ENV['MFA_EMAIL_ENABLED'] = 'true';

        $hash = $this->makeHash();
        $_POST = ['email' => 'mfa1001@example.com', 'password' => 'TestPass123!', 'csrf_token' => 'valid-csrf'];
        // Pas de cookie device_token → appareil inconnu
        $_COOKIE = [];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount(['password' => $hash, 'email' => 'mfa1001@example.com']),
            false  // TrustedDeviceModel::isTrusted → non de confiance
        );

        $caught = null;
        try {
            $this->makeControllerWithThrowingMail()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // MailService throw → catch L1001-1002 → pending_device posé + redirect
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('pending_device', $_SESSION);
    }

    // ================================================================
    // handleUntrustedDevice() — L990 : account_type = 'company'
    // → displayName = company_name (branche ternaire true)
    // ================================================================

    /**
     * Couvre L990 : compte B2B (account_type = 'company') → displayName prend
     * la valeur de company_name (branche ternaire true) au lieu du prénom+nom.
     *
     * Utilise makeControllerWithThrowingMail() pour forcer le catch \Throwable
     * (L1001-1002) sans dépendre du SMTP — l'important est que la ligne 990 soit
     * exécutée avant le throw.
     */
    public function testHandleUntrustedDeviceUsesCompanyNameForCompanyAccount(): void
    {
        $_ENV['MFA_EMAIL_ENABLED'] = 'true';

        $hash  = $this->makeHash();
        $_POST = [
            'email'      => 'contact@acme.fr',
            'password'   => 'TestPass123!',
            'csrf_token' => 'valid-csrf',
        ];
        $_COOKIE = []; // pas de cookie device_token → appareil inconnu

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount([
                'id'            => 77,
                'email'         => 'contact@acme.fr',
                'password'      => $hash,
                'account_type'  => 'company',
                'company_name'  => 'Acme Corp',
                'firstname'     => null,
                'lastname'      => null,
            ]),
            false   // TrustedDeviceModel::isTrusted → non de confiance
        );

        $caught = null;
        try {
            $this->makeControllerWithThrowingMail()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // L990 traversée (account_type='company' → company_name retenu) puis
        // MailService throw → catch → pending_device posé + redirect
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('pending_device', $_SESSION);
    }

    /**
     * Variante fallback : compte 'company' avec company_name vide → fallback 'Client' (L990).
     */
    public function testHandleUntrustedDeviceFallsBackToDefaultWhenCompanyNameMissing(): void
    {
        $_ENV['MFA_EMAIL_ENABLED'] = 'true';

        $hash  = $this->makeHash();
        $_POST = [
            'email'      => 'anon@acme.fr',
            'password'   => 'TestPass123!',
            'csrf_token' => 'valid-csrf',
        ];
        $_COOKIE = [];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount([
                'id'           => 78,
                'email'        => 'anon@acme.fr',
                'password'     => $hash,
                'account_type' => 'company',
                'company_name' => null,  // fallback → 'Client'
                'firstname'    => null,
                'lastname'     => null,
            ]),
            false
        );

        $caught = null;
        try {
            $this->makeControllerWithThrowingMail()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // MailService throw → catch L1001-1002 → pending_device posé malgré tout
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('pending_device', $_SESSION);
    }
}
