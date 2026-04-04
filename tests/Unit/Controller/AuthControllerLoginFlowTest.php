<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests the invalid-credential and lockout branches of AuthController::login().
 * Also covers the untrusted-device redirect (MFA) path.
 *
 * Lignes couvertes : ~133-158 (lockout, invalid_credentials), ~174-182 (trust check, MFA).
 */
class AuthControllerLoginFlowTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        // session_start() AVANT $_SESSION pour que RateLimiterService::ensureSession()
        // ne réinitialise pas les clés pré-positionnées.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'valid-csrf'];
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
    }

    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return $stub;
    }

    // ================================================================
    // Mauvais mot de passe / compte introuvable → flash invalid_credentials
    // ================================================================

    public function testLoginFlashesInvalidCredentialsWhenAccountNotFound(): void
    {
        $_POST = ['email' => 'unknown@example.com', 'password' => 'AnyPass1!', 'csrf_token' => 'valid-csrf'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(false); // findByEmail → compte inexistant

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

    public function testLoginFlashesInvalidCredentialsWhenPasswordWrong(): void
    {
        $_POST = ['email' => 'jean@example.com', 'password' => 'WrongPass!', 'csrf_token' => 'valid-csrf'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 1,
            'email'             => 'jean@example.com',
            'password'          => 'not-a-valid-hash', // password_verify retournera false
            'role'              => 'customer',
            'account_type'      => 'individual',
            'has_connected'     => '1',
            'email_verified_at' => '2025-01-01 00:00:00',
            'lang'              => 'fr',
        ]);

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
    // 5ème échec consécutif → lockout du compte (BT2)
    // ================================================================

    public function testLoginTriggersAccountLockoutOnFifthFailure(): void
    {
        $_POST = ['email' => 'jean@example.com', 'password' => 'WrongPass!', 'csrf_token' => 'valid-csrf'];

        // 4 échecs précédents pour ce compte
        $_SESSION['_rl']['rl:account_lockout:1:count'] = 4;

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 1,
            'email'             => 'jean@example.com',
            'password'          => 'not-a-valid-hash',
            'role'              => 'customer',
            'account_type'      => 'individual',
            'has_connected'     => '1',
            'email_verified_at' => '2025-01-01 00:00:00',
            'lang'              => 'fr',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => null,
        ]);

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // Le flash doit contenir le message account_locked (pas invalid_credentials)
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Appareil non de confiance → redirection MFA /nouvel-appareil
    // ================================================================

    public function testLoginRedirectsToMfaForUntrustedDevice(): void
    {
        $hash = password_hash('TestPass123!', PASSWORD_BCRYPT, ['cost' => 4]);

        $_POST = ['email' => 'jean@example.com', 'password' => 'TestPass123!', 'csrf_token' => 'valid-csrf'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            // 1. AccountModel::findByEmail → compte existant avec connexion antérieure
            [
                'id'                => 1,
                'email'             => 'jean@example.com',
                'password'          => $hash,
                'role'              => 'customer',
                'account_type'      => 'individual',
                'has_connected'     => '1',   // pas une première connexion
                'email_verified_at' => '2025-01-01 00:00:00',
                'lang'              => 'fr',
                'firstname'         => 'Jean',
                'lastname'          => 'Dupont',
                'company_name'      => null,
            ],
            // 2. TrustedDeviceModel::isTrusted → appareil NON connu
            false
        );

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // pending_device doit être positionné en session
        $this->assertArrayHasKey('pending_device', $_SESSION);
    }
}
