<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests the register happy path and forgotForm redirect in AuthController.
 *
 * Lignes couvertes :
 *   ~355-383 : register() — création de compte + envoi email + redirect
 *   ~441-442 : forgotForm() → redirect immédiat
 */
class AuthControllerRegisterHappyPathTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
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
        $_SERVER['REQUEST_URI']    = '/fr/inscription';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
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
    // register() — happy path : pas de doublon → création + redirect
    // ================================================================

    public function testRegisterCreatesAccountAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Dupont',
            'firstname'        => 'Jean',
            'email'            => 'nouveau@example.com',
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
            'company_name'     => '',
            'newsletter'       => '0',
            'csrf_token'       => 'valid-csrf',
        ];

        $stub = $this->setDbMock();
        // findByEmail → pas de doublon
        $stub->method('fetchOne')->willReturn(false);

        $caught = null;
        try {
            (new AuthController(new Request()))->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'register() doit rediriger via HttpException');
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('register_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // forgotForm() → redirect immédiat vers /fr (sans authentification)
    // ================================================================

    public function testForgotFormRedirectsToHomepage(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mot-de-passe-oublie';
        $_COOKIE = []; // pas connecté → GuestMiddleware passe

        $caught = null;
        try {
            (new AuthController(new Request()))->forgotForm(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'forgotForm() doit rediriger via HttpException');
        $this->assertSame(302, $caught->status);
    }
}
