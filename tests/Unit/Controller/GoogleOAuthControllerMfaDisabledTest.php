<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\GoogleOAuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Couvre la branche MFA_EMAIL_ENABLED=false dans GoogleOAuthController::issueSession().
 *
 * Quand l'env var est false, un appareil non de confiance est auto-approuvé
 * (trustedDevices->trust() appelé) et la session OAuth est émise sans challenge email.
 */
class GoogleOAuthControllerMfaDisabledTest extends TestCase
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
        $_ENV['MFA_EMAIL_ENABLED']    = 'false';
        $_SERVER['REQUEST_METHOD']    = 'GET';
        $_SERVER['REQUEST_URI']       = '/fr/auth/google/callback';
        $_SERVER['HTTP_USER_AGENT']   = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120';
        $_SERVER['REMOTE_ADDR']       = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_COOKIE  = [];
        $_GET     = [];
        unset($_ENV['MFA_EMAIL_ENABLED'], $_SERVER['REMOTE_ADDR']);
    }

    private function makeController(): GoogleOAuthController
    {
        return new GoogleOAuthController(new Request());
    }

    private function callIssueSession(GoogleOAuthController $ctrl, array $account, string $lang): void
    {
        $m = new \ReflectionMethod(GoogleOAuthController::class, 'issueSession');
        $m->invoke($ctrl, $account, $lang);
    }

    // ================================================================
    // MFA_EMAIL_ENABLED=false + appareil non de confiance → session émise
    // sans redirection vers /nouvel-appareil
    // ================================================================

    public function testIssueSessionAutoTrustsDeviceWhenMfaDisabled(): void
    {
        $stub = $this->createStub(Database::class);
        $this->dbInstanceProp->setValue(null, $stub);

        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            // 1. TrustedDeviceModel::isTrusted → non de confiance
            false,
            // 2. AccountModel::hasDeclaredMajority
            ['majority_declared_at' => '2025-01-01 00:00:00']
        );

        $account = [
            'id'           => 42,
            'role'         => 'customer',
            'lang'         => 'fr',
            'has_connected' => true,
            'account_type' => 'individual',
            'firstname'    => 'Jean',
            'lastname'     => 'Dupont',
            'company_name' => null,
        ];

        $caught = null;
        try {
            $this->callIssueSession($this->makeController(), $account, 'fr');
        } catch (HttpException $e) {
            $caught = $e;
        }

        // Auto-trust → session émise → redirect 302
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        // Pas de challenge MFA en session
        $this->assertArrayNotHasKey('pending_device', $_SESSION);
    }

    // ================================================================
    // MFA_EMAIL_ENABLED=true → handleUntrustedDevice → /nouvel-appareil
    // ================================================================

    public function testIssueSessionChallengesMfaWhenEnabled(): void
    {
        $_ENV['MFA_EMAIL_ENABLED'] = 'true';

        $stub = $this->createStub(Database::class);
        $this->dbInstanceProp->setValue(null, $stub);

        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            false // isTrusted → non de confiance
        );

        $account = [
            'id'            => 99,
            'role'          => 'customer',
            'lang'          => 'fr',
            'has_connected' => true,
            'account_type'  => 'individual',
            'firstname'     => 'Marie',
            'lastname'      => 'Dupont',
            'company_name'  => null,
            'email'         => 'marie@example.com',
        ];

        $caught = null;
        try {
            $this->callIssueSession($this->makeController(), $account, 'fr');
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('pending_device', $_SESSION);
    }
}
