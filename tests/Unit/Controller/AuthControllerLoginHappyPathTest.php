<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests the successful login paths in AuthController::login().
 *
 * Lignes couvertes :
 *   ~174-176  : $isFirstEverLogin / $isAlreadyTrusted / $trusted
 *   ~185-231  : JWT emission, cookie, connections, cart merge, badge count, redirect
 */
class AuthControllerLoginHappyPathTest extends TestCase
{
    private \ReflectionProperty $instanceProp;
    private string $hash;

    protected function setUp(): void
    {
        // BCrypt cost 4 = rapide en test
        $this->hash = password_hash('TestPass123!', PASSWORD_BCRYPT, ['cost' => 4]);

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
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) PHPUnit';
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

    private function baseAccount(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }

    // ================================================================
    // Connexion réussie — appareil déjà de confiance (isAlreadyTrusted)
    // ================================================================

    public function testLoginSucceedsWithAlreadyTrustedDevice(): void
    {
        $_POST = [
            'email'        => 'jean@example.com',
            'password'     => 'TestPass123!',
            'csrf_token'   => 'valid-csrf',
        ];
        $_COOKIE['device_token'] = 'existing-device-token';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount(),           // 1. AccountModel::findByEmail
            ['id' => 1],                    // 2. TrustedDeviceModel::isTrusted → connu
            false                           // 3. CartModel::findByUserId (badge count)
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
    // Connexion réussie — première connexion du compte (isFirstEverLogin)
    // ================================================================

    public function testLoginSucceedsForFirstEverLogin(): void
    {
        $_POST = [
            'email'      => 'new@example.com',
            'password'   => 'TestPass123!',
            'csrf_token' => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            $this->baseAccount(['id' => 2, 'email' => 'new@example.com', 'has_connected' => '0']),
            // 2. TrustedDeviceModel::isTrusted — valeur ignorée (isFirstEverLogin = true → trusted de toute façon)
            false,
            // 3. CartModel::findByUserId (badge count)
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
    }
}
