<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\FeatureFlags;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Couvre la branche MFA_EMAIL_ENABLED=false dans AuthController::login().
 *
 * Quand l'env var est false, un appareil non de confiance est auto-approuvé
 * (trustedDevices->trust() appelé) et la connexion s'effectue sans challenge email.
 */
class AuthControllerMfaDisabledTest extends TestCase
{
    private \ReflectionProperty $instanceProp;
    private string $hash;

    protected function setUp(): void
    {
        $this->hash = password_hash('TestPass123!', PASSWORD_BCRYPT, ['cost' => 4]);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_SERVER['REQUEST_URI']     = '/fr/connexion';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 PHPUnit';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';

        FeatureFlags::setCache(['customer_mfa_enabled' => ['key' => 'customer_mfa_enabled', 'value' => 'false', 'type' => 'bool']]);
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
        unset($_SERVER['REMOTE_ADDR']);
        FeatureFlags::flush();
    }

    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return $stub;
    }

    // ================================================================
    // MFA_EMAIL_ENABLED=false + appareil non de confiance → login réussi (302)
    // sans redirection vers /nouvel-appareil
    // ================================================================

    public function testLoginSucceedsWithMfaDisabledForUntrustedDevice(): void
    {
        $_POST = [
            'email'      => 'jean@example.com',
            'password'   => 'TestPass123!',
            'csrf_token' => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            // 1. AccountModel::findByEmail
            [
                'id'                => 1,
                'email'             => 'jean@example.com',
                'password'          => $this->hash,
                'role'              => 'customer',
                'account_type'      => 'individual',
                'has_connected'     => '1',
                'email_verified_at' => '2025-01-01 00:00:00',
                'lang'              => 'fr',
                'firstname'         => 'Jean',
                'lastname'          => 'Dupont',
                'company_name'      => null,
            ],
            // 2. TrustedDeviceModel::isTrusted → non de confiance
            false,
            // 3. CartModel::findByUserId (badge)
            false,
            // 4. AccountModel::hasDeclaredMajority
            ['majority_declared_at' => '2025-01-01 00:00:00']
        );

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        // Auto-trust → connexion réussie → 302
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // Pas de challenge MFA en session
        $this->assertArrayNotHasKey('pending_device', $_SESSION);
    }

    // ================================================================
    // MFA_EMAIL_ENABLED=true (défaut) → comportement inchangé → MFA
    // ================================================================

    public function testLoginRedirectsToMfaWhenMfaEnabledAndUntrustedDevice(): void
    {
        FeatureFlags::setCache(['customer_mfa_enabled' => ['key' => 'customer_mfa_enabled', 'value' => 'true', 'type' => 'bool']]);

        $_POST = [
            'email'      => 'jean@example.com',
            'password'   => 'TestPass123!',
            'csrf_token' => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            [
                'id'                => 1,
                'email'             => 'jean@example.com',
                'password'          => $this->hash,
                'role'              => 'customer',
                'account_type'      => 'individual',
                'has_connected'     => '1',
                'email_verified_at' => '2025-01-01 00:00:00',
                'lang'              => 'fr',
                'firstname'         => 'Jean',
                'lastname'          => 'Dupont',
                'company_name'      => null,
            ],
            false // isTrusted → non de confiance
        );

        $caught = null;
        try {
            (new AuthController(new Request()))->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('pending_device', $_SESSION);
    }
}
