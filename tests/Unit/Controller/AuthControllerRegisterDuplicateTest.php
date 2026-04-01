<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie le comportement anti-énumération lors d'une inscription
 * avec un email déjà enregistré.
 *
 * Le message affiché doit être identique à celui d'une inscription réussie
 * (R5 anti-énumération) et aucun message d'erreur révélant l'existence
 * du compte ne doit être présent en session.
 */
class AuthControllerRegisterDuplicateTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createMock(Database::class);
        // fetchOne retourne un compte existant pour findByEmail
        $dbMock->method('fetchOne')->willReturn([
            'id'           => 1,
            'email'        => 'existing@example.com',
            'account_type' => 'individual',
            'firstname'    => 'Alice',
            'lastname'     => 'Martin',
            'company_name' => null,
        ]);

        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'test-csrf-token'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
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

    public function testDuplicateEmailShowsSameMessageAsSuccess(): void
    {
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Dupont',
            'firstname'        => 'Jean',
            'email'            => 'existing@example.com',
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
            'company_name'     => '',
            'newsletter'        => '0',
            'majority_confirmed' => '1',
            'csrf_token'        => 'test-csrf-token',
        ];

        $controller = new AuthController(new Request());
        $caught     = null;
        try {
            $controller->register(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught, 'register() doit rediriger via HttpException');
        $this->assertSame(302, $caught->status);

        // Anti-énumération R5 : flag de succès identique à une inscription réussie
        $this->assertArrayHasKey('register_success', $_SESSION['flash']);
        $this->assertNotEmpty($_SESSION['flash']['register_success']);

        // Aucune clé d'erreur révélant l'existence du compte
        $this->assertArrayNotHasKey('register_errors', $_SESSION['flash']);
    }

    public function testDuplicateEmailDoesNotExposeAccountExistence(): void
    {
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'F',
            'lastname'         => 'Durand',
            'firstname'        => 'Marie',
            'email'            => 'existing@example.com',
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
            'company_name'     => '',
            'newsletter'         => '0',
            'majority_confirmed' => '1',
            'csrf_token'         => 'test-csrf-token',
        ];

        $controller = new AuthController(new Request());
        try {
            $controller->register(['lang' => 'fr']);
        } catch (HttpException) {
        }

        // Aucune clé d'erreur révélant l'existence du compte dans les autres clés flash
        $this->assertArrayNotHasKey('info', $_SESSION['flash']);
        $this->assertArrayNotHasKey('modal_error', $_SESSION['flash']);
    }
}
