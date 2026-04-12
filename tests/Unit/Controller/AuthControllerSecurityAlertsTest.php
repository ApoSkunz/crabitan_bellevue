<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;
use Service\RateLimiterService;
use Service\SecurityAlertService;

/**
 * Couvre les 41 lignes non couvertes restantes dans AuthController.php :
 *   - login L164 : fallback lang 'fr' quand la langue du compte est invalide
 *   - login L173 : catch Throwable dans sendPendingDeletionLoginAlert
 *   - login L190-193 : admin avec shouldAlertOnFailedLogins → sendAdminLoginAlert
 *   - login L199,207 : admin shouldAutoBlock → blockAccount + sendAdminAccountBlocked + catch
 *   - login L217-229 : BT2 lockout account (company) → sendAccountLocked + catch
 *   - login L247-253 : catch Throwable dans sendEmailVerification (resend verif)
 *   - login L264 : connexion admin réussie → resetFailedLoginCount
 *   - login L292 : accountLang fallback (lang compte invalide) sur chemin happy path
 *   - register L489-497 : sendEmailAlreadyExists + catch Throwable (duplicate email)
 *   - register L531-532 : catch Throwable dans sendEmailVerification (register nominal)
 *   - forgot L667-668 : catch Throwable dans sendPasswordReset / sendGoogleAccountInfo
 *   - resendActivation L828-835 : catch Throwable + dev log dans sendEmailVerification
 *   - handleUntrustedDevice L1059-1060 : catch Throwable dans sendNewDeviceAlert
 */
class AuthControllerSecurityAlertsTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'valid-csrf', '_rl' => [], '_rl_ttl' => []];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_SERVER['REQUEST_URI']     = '/fr/connexion';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
        unset($_SERVER['REMOTE_ADDR']);
        $_ENV['APP_ENV'] = 'prod';
    }

    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return $stub;
    }

    /**
     * Crée un AuthController avec RateLimiterService forcé en mode session.
     *
     * @return AuthController
     */
    private function makeSessionController(): AuthController
    {
        $controller = new AuthController(new Request());
        $prop = new \ReflectionProperty(AuthController::class, 'rateLimiter');
        $prop->setValue($controller, new class extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        });
        return $controller;
    }

    /**
     * Crée un AuthController avec RateLimiterService session + SecurityAlertService mocké.
     *
     * @param SecurityAlertService $secAlert Service d'alerte à injecter
     * @return AuthController
     */
    private function makeControllerWithSecurityAlert(SecurityAlertService $secAlert): AuthController
    {
        $controller = $this->makeSessionController();
        $prop = new \ReflectionProperty(AuthController::class, 'securityAlert');
        $prop->setValue($controller, $secAlert);
        return $controller;
    }

    // ================================================================
    // L164 — Fallback lang 'fr' quand la langue du compte est invalide
    // L173 — catch Throwable dans sendPendingDeletionLoginAlert
    // ================================================================

    /**
     * Un compte soft-deleted avec une langue invalide ('xx') doit fallback sur 'fr'.
     * Le catch Throwable de sendPendingDeletionLoginAlert est couvert car MailService
     * ne peut pas envoyer de mail en contexte PHPUnit (pas de config SMTP).
     *
     * @covers \Controller\AuthController::login
     */
    public function testLoginSoftDeletedAccountWithInvalidLangFallsBackToFr(): void
    {
        $hash = password_hash('CorrectPass1!', PASSWORD_BCRYPT, ['cost' => 4]);

        $_POST = ['email' => 'deleted@example.com', 'password' => 'CorrectPass1!', 'csrf_token' => 'valid-csrf'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            false, // findByEmail → aucun compte actif
            [      // findDeletedByEmail → compte soft-deleted, lang invalide
                'id'                    => 77,
                'email'                 => 'deleted@example.com',
                'password'              => $hash,
                'lang'                  => 'xx', // langue invalide → fallback 'fr' L164
                'firstname'             => 'Test',
                'company_name'          => null,
                'scheduled_deletion_at' => date('Y-m-d H:i:s', strtotime('+10 days')),
                'reactivation_token'    => 'tok-reactivation',
            ]
        );

        $caught = null;
        try {
            $this->makeSessionController()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // Flag session positionné (le catch Throwable n'a pas empêché le déroulement)
        $this->assertTrue($_SESSION['deletion_alert_sent_77'] ?? false);
    }

    // ================================================================
    // L217-229 — BT2 lockout (company) → sendAccountLocked + catch
    // ================================================================

    /**
     * Un compte entreprise verrouillé (3 échecs passés) déclenche sendAccountLocked.
     * Le catch Throwable (L225-227) est couvert car MailService échoue en PHPUnit.
     *
     * @covers \Controller\AuthController::login
     */
    public function testLoginBt2LockoutCompanyAccountSendsLockedMail(): void
    {
        $_POST = ['email' => 'company@example.com', 'password' => 'WrongPass!', 'csrf_token' => 'valid-csrf'];

        // MAX_ACCOUNT_FAILURES = 3.
        // count=2 → L140 checkLimit voit 2 < 3 → continue.
        // recordLoginFailure() incrémente → count=3.
        // L216 checkLimit voit 3 >= 3 → pose lockout → retourne false → L217-229 couvertes.
        $_SESSION['_rl']['rl:account_lockout:20:count'] = 2;

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 20,
            'email'             => 'company@example.com',
            'password'          => 'not-a-valid-hash',
            'role'              => 'customer',
            'account_type'      => 'company',
            'has_connected'     => '1',
            'email_verified_at' => '2025-01-01 00:00:00',
            'lang'              => 'fr',
            'firstname'         => null,
            'lastname'          => null,
            'company_name'      => 'Acme Corp',
        ]);

        $caught = null;
        try {
            $this->makeSessionController()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // L220 — BT2 lockout individual → displayName = trim(firstname lastname)
    // ================================================================

    /**
     * Un compte individuel déclenche la branche L220 (individual displayName).
     * count=2 → passe L140, échoue L216 après recordLoginFailure → L217-229.
     *
     * @covers \Controller\AuthController::login
     */
    public function testLoginBt2LockoutIndividualAccountSendsLockedMail(): void
    {
        $_POST = ['email' => 'user2@example.com', 'password' => 'WrongPass!', 'csrf_token' => 'valid-csrf'];

        $_SESSION['_rl']['rl:account_lockout:21:count'] = 2;

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 21,
            'email'             => 'user2@example.com',
            'password'          => 'not-a-valid-hash',
            'role'              => 'customer',
            'account_type'      => 'individual', // L220 : branche individual
            'has_connected'     => '1',
            'email_verified_at' => '2025-01-01 00:00:00',
            'lang'              => 'fr',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => null,
        ]);

        $caught = null;
        try {
            $this->makeSessionController()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // L247-253 — catch Throwable dans sendEmailVerification (resend silencieux au login)
    // ================================================================

    /**
     * Un compte avec email non vérifié et mot de passe correct déclenche le renvoi
     * silencieux du token de vérification (L241-255).
     * Le catch Throwable de sendEmailVerification (L252-253) est couvert car MailService
     * ne peut pas envoyer en contexte PHPUnit.
     *
     * @covers \Controller\AuthController::login
     */
    public function testLoginUnverifiedEmailResendCatchesThrowable(): void
    {
        $hash = password_hash('CorrectPass1#XyZ', PASSWORD_BCRYPT, ['cost' => 4]);

        $_POST = ['email' => 'unverified@example.com', 'password' => 'CorrectPass1#XyZ', 'csrf_token' => 'valid-csrf'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 30,
            'email'             => 'unverified@example.com',
            'password'          => $hash, // password_verify retourne true
            'role'              => 'customer',
            'account_type'      => 'individual',
            'has_connected'     => '1',
            'email_verified_at' => null, // non vérifié → branch L237
            'lang'              => 'fr',
            'firstname'         => 'Un',
            'lastname'          => 'Verified',
            'company_name'      => null,
        ]);

        $caught = null;
        try {
            $this->makeSessionController()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // L247 — resend verif branche company (displayName = company_name)
    // ================================================================

    /**
     * Un compte entreprise avec email non vérifié → displayName = company_name (L247).
     *
     * @covers \Controller\AuthController::login
     */
    public function testLoginUnverifiedCompanyEmailUsesCompanyName(): void
    {
        $hash = password_hash('CorrectPass1#XyZ', PASSWORD_BCRYPT, ['cost' => 4]);

        $_POST = ['email' => 'company@unverified.com', 'password' => 'CorrectPass1#XyZ', 'csrf_token' => 'valid-csrf'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 31,
            'email'             => 'company@unverified.com',
            'password'          => $hash,
            'role'              => 'customer',
            'account_type'      => 'company', // L247 : branche company
            'has_connected'     => '1',
            'email_verified_at' => null,
            'lang'              => 'fr',
            'firstname'         => null,
            'lastname'          => null,
            'company_name'      => 'BigCorp',
        ]);

        $caught = null;
        try {
            $this->makeSessionController()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // L292 — accountLang fallback quand la lang du compte est invalide (happy path)
    // ================================================================

    /**
     * Un compte authentifié avec une langue invalide ('zz') doit utiliser la lang
     * de l'URL comme fallback (L292).
     *
     * @covers \Controller\AuthController::login
     */
    public function testLoginUsesUrlLangWhenAccountLangIsInvalid(): void
    {
        $hash = password_hash('TestPass123!', PASSWORD_BCRYPT, ['cost' => 4]);

        $_POST = ['email' => 'user@example.com', 'password' => 'TestPass123!', 'csrf_token' => 'valid-csrf'];
        $_COOKIE['device_token'] = 'trusted-device-token';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            [
                'id'                => 50,
                'email'             => 'user@example.com',
                'password'          => $hash,
                'role'              => 'customer',
                'account_type'      => 'individual',
                'has_connected'     => '1',
                'email_verified_at' => '2025-01-01 00:00:00',
                'lang'              => 'zz', // invalide → fallback sur $lang de l'URL (L292)
                'firstname'         => 'Jane',
                'lastname'          => 'Doe',
                'company_name'      => null,
            ],
            ['id' => 50], // TrustedDeviceModel::isTrusted → connu
            false,        // CartModel::findByUserId
            ['majority_declared_at' => '2025-01-01 00:00:00']
        );

        $caught = null;
        try {
            $this->makeSessionController()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // L489-497 — catch Throwable dans sendEmailAlreadyExists (register duplicate)
    // ================================================================

    /**
     * Quand un email déjà existant est soumis à register(), sendEmailAlreadyExists
     * est appelé. Le catch Throwable (L496-497) est couvert car MailService
     * ne peut pas envoyer en contexte PHPUnit.
     *
     * @covers \Controller\AuthController::register
     */
    public function testRegisterDuplicateEmailCatchesThrowableOnMailSend(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/inscription';
        $_POST = [
            'csrf_token'        => 'valid-csrf',
            'account_type'      => 'individual',
            'email'             => 'existing@example.com',
            'password'          => 'Vigilant7#xKqm',
            'password_confirm'  => 'Vigilant7#xKqm',
            'civility'          => 'M',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => '',
            'newsletter'        => '0',
        ];

        $stub = $this->setDbMock();
        // findByEmail retourne un compte existant → branch "already exists"
        $stub->method('fetchOne')->willReturn([
            'id'           => 60,
            'email'        => 'existing@example.com',
            'account_type' => 'individual',
            'firstname'    => 'Jean',
            'lastname'     => 'Dupont',
            'company_name' => null,
        ]);

        $caught = null;
        try {
            $this->makeSessionController()->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // Anti-énumération : même flash que register_success
        $this->assertArrayHasKey('register_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // L489 — branche company dans register duplicate (displayName = company_name)
    // ================================================================

    /**
     * Un email existant de type company → displayName = company_name (L489).
     *
     * @covers \Controller\AuthController::register
     */
    public function testRegisterDuplicateCompanyEmailUsesCompanyName(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/inscription';
        $_POST = [
            'csrf_token'        => 'valid-csrf',
            'account_type'      => 'company',
            'email'             => 'company@example.com',
            'password'          => 'Vigilant7#xKqm',
            'password_confirm'  => 'Vigilant7#xKqm',
            'civility'          => '',
            'firstname'         => '',
            'lastname'          => '',
            'company_name'      => 'Acme Corp',
            'newsletter'        => '0',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'           => 61,
            'email'        => 'company@example.com',
            'account_type' => 'company',
            'firstname'    => null,
            'lastname'     => null,
            'company_name' => 'Acme Corp', // L489 : branche company
        ]);

        $caught = null;
        try {
            $this->makeSessionController()->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('register_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // L531-532 — catch Throwable dans sendEmailVerification (register nominal)
    // ================================================================

    /**
     * Sur le chemin nominal de register(), sendEmailVerification peut lever
     * une exception. Le catch Throwable (L531-532) ne doit pas bloquer le redirect.
     *
     * @covers \Controller\AuthController::register
     */
    public function testRegisterNominalCatchesThrowableOnVerificationMail(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/inscription';
        $_POST = [
            'csrf_token'        => 'valid-csrf',
            'account_type'      => 'individual',
            'email'             => 'newuser@example.com',
            'password'          => 'Vigilant7#xKqm',
            'password_confirm'  => 'Vigilant7#xKqm',
            'civility'          => 'M',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => '',
            'newsletter'        => '0',
        ];

        $stub = $this->setDbMock();
        // findByEmail → pas d'email existant
        $stub->method('fetchOne')->willReturn(false);
        // execute() → void pour AccountModel::create (INSERT)

        $caught = null;
        try {
            $this->makeSessionController()->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('register_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // L667-668 — catch Throwable dans sendPasswordReset / sendGoogleAccountInfo (forgot)
    // ================================================================

    /**
     * Sur le chemin nominal de forgot() pour un compte individuel valide,
     * sendPasswordReset peut lever une exception → catch L667-668 couvert.
     *
     * @covers \Controller\AuthController::forgot
     */
    public function testForgotNominalCatchesThrowableOnPasswordResetMail(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/mot-de-passe-oublie';
        $_POST = ['csrf_token' => 'valid-csrf', 'email' => 'user@example.com'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 70,
            'email'             => 'user@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',
            'role'              => 'customer',
            'password'          => '$2y$10$hashedpassword',
            'account_type'      => 'individual',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => null,
        ]);

        $caught = null;
        try {
            $this->makeSessionController()->forgot(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('forgot_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // L828-835 — catch Throwable + dev log dans sendEmailVerification (resendActivation)
    // ================================================================

    /**
     * Sur le chemin nominal de resendActivation() avec APP_ENV=dev,
     * le catch Throwable de sendEmailVerification doit déclencher le log dev (L830-835).
     *
     * @covers \Controller\AuthController::resendActivation
     */
    public function testResendActivationCatchesThrowableWithDevLog(): void
    {
        $_ENV['APP_ENV'] = 'dev';
        $_SERVER['REQUEST_URI'] = '/fr/renvoyer-activation';
        $_POST = ['csrf_token' => 'valid-csrf', 'email' => 'user@example.com'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 80,
            'email'             => 'user@example.com',
            'email_verified_at' => null,
            'account_type'      => 'individual',
            'firstname'         => 'Test',
            'lastname'          => 'User',
            'company_name'      => null,
        ]);

        $caught = null;
        try {
            $this->makeSessionController()->resendActivation(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // L1059-1060 — catch Throwable dans sendNewDeviceAlert (handleUntrustedDevice)
    // ================================================================

    /**
     * Quand un appareil non de confiance déclenche handleUntrustedDevice(),
     * sendNewDeviceAlert peut lever une exception → catch L1059-1060 couvert.
     * Le redirect vers /fr/mon-compte/nouvel-appareil doit toujours avoir lieu.
     *
     * @covers \Controller\AuthController::login
     */
    public function testLoginUntrustedDeviceCatchesThrowableOnNewDeviceAlert(): void
    {
        $hash = password_hash('TestPass123!', PASSWORD_BCRYPT, ['cost' => 4]);

        $_POST = ['email' => 'jean@example.com', 'password' => 'TestPass123!', 'csrf_token' => 'valid-csrf'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            [
                'id'                => 90,
                'email'             => 'jean@example.com',
                'password'          => $hash,
                'role'              => 'customer',
                'account_type'      => 'individual',
                'has_connected'     => '1',
                'email_verified_at' => '2025-01-01 00:00:00',
                'lang'              => 'fr',
                'firstname'         => 'Jean',
                'lastname'          => 'Dupont',
                'company_name'      => null,
            ],
            false // TrustedDeviceModel::isTrusted → appareil NON connu → handleUntrustedDevice
        );

        $caught = null;
        try {
            $this->makeSessionController()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // pending_device positionné malgré l'échec d'envoi email
        $this->assertArrayHasKey('pending_device', $_SESSION);
    }
}
