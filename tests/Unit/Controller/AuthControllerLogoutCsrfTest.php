<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie que la déconnexion est protégée contre les attaques CSRF.
 *
 * Critères testés :
 *   - GET /{lang}/deconnexion → 405 Method Not Allowed (ne déconnecte pas)
 *   - POST /{lang}/deconnexion sans CSRF → redirige sans déconnecter
 *   - POST /{lang}/deconnexion avec CSRF valide → déconnecte et redirige
 */
class AuthControllerLogoutCsrfTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'valid-csrf-token'];
        $_POST    = [];
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
    }

    // ----------------------------------------------------------------
    // GET /deconnexion → doit répondre 405, pas de déconnexion
    // ----------------------------------------------------------------

    /**
     * Un GET sur /deconnexion doit retourner 405 Method Not Allowed
     * et ne doit PAS invalider la session ni supprimer le cookie.
     */
    public function testGetLogoutReturns405(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/deconnexion';
        $_COOKIE['auth_token']     = 'fake-jwt-token';

        $controller = new AuthController(new Request());

        $exception = null;
        try {
            $controller->logout(['lang' => 'fr']);
        } catch (HttpException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception, 'logout() doit lever une HttpException sur GET');
        $this->assertSame(405, $exception->status, 'Le statut doit être 405 Method Not Allowed');

        // Le cookie auth_token ne doit pas avoir été supprimé
        $this->assertArrayHasKey('auth_token', $_COOKIE, 'Le cookie ne doit pas être supprimé sur GET');
    }

    // ----------------------------------------------------------------
    // POST /deconnexion sans CSRF → redirige sans déconnecter
    // ----------------------------------------------------------------

    /**
     * Un POST sur /deconnexion avec un CSRF token invalide doit
     * rediriger sans invalider la session ni supprimer le cookie.
     */
    public function testPostLogoutWithInvalidCsrfDoesNotLogout(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/deconnexion';
        $_COOKIE['auth_token']     = 'fake-jwt-token';
        $_POST['csrf_token']       = 'wrong-token';

        $controller = new AuthController(new Request());

        $exception = null;
        try {
            $controller->logout(['lang' => 'fr']);
        } catch (HttpException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception, 'logout() doit lever une HttpException (redirect)');
        $this->assertSame(302, $exception->status, 'Doit rediriger en 302');

        // Le cookie ne doit pas avoir été supprimé
        $this->assertArrayHasKey('auth_token', $_COOKIE, 'Le cookie ne doit pas être supprimé sans CSRF valide');
    }

    // ----------------------------------------------------------------
    // POST /deconnexion avec CSRF valide → déconnecte
    // ----------------------------------------------------------------

    /**
     * Un POST sur /deconnexion avec un CSRF token valide doit
     * déclencher la déconnexion et rediriger vers l'accueil.
     */
    public function testPostLogoutWithValidCsrfLogsOut(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/deconnexion';
        $_COOKIE['auth_token']     = 'fake-jwt-token';
        $_POST['csrf_token']       = 'valid-csrf-token';

        $controller = new AuthController(new Request());

        $exception = null;
        try {
            $controller->logout(['lang' => 'fr']);
        } catch (HttpException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception, 'logout() doit lever une HttpException (redirect)');
        $this->assertSame(302, $exception->status, 'Doit rediriger en 302 après logout');
    }
}
