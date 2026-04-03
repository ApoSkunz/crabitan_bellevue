<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Api;

use Controller\Api\CartApiController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CartApiController.
 * La base de données est mockée — aucune connexion réelle requise.
 */
class CartApiControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'test-csrf-token'];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD']  = 'POST';
        $_SERVER['REQUEST_URI']     = '/api/cart/add';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_ENV['JWT_SECRET']         = 'test-secret-key-minimum-32-chars!!';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    private function makeController(): CartApiController
    {
        return new CartApiController(new Request());
    }

    // ----------------------------------------------------------------
    // add — non authentifié → 401
    // ----------------------------------------------------------------

    public function testAddReturns401WhenNotAuthenticated(): void
    {
        $_COOKIE = [];
        $_POST   = ['wine_id' => 1, 'quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // add — CSRF invalide → 403
    // ----------------------------------------------------------------

    public function testAddReturns403WhenCsrfInvalid(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 2, 'csrf_token' => 'INVALID-TOKEN'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // add — wine_id manquant → 422
    // ----------------------------------------------------------------

    public function testAddReturns422WhenWineIdMissing(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['quantity' => 2, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // update — non authentifié → 401
    // ----------------------------------------------------------------

    public function testUpdateReturns401WhenNotAuthenticated(): void
    {
        $_COOKIE = [];
        $_POST   = ['wine_id' => 1, 'quantity' => 1, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ob_start();
        try {
            $this->makeController()->update([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // update — CSRF invalide → 403
    // ----------------------------------------------------------------

    public function testUpdateReturns403WhenCsrfInvalid(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'quantity' => 1, 'csrf_token' => 'BAD'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->update([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // remove — non authentifié → 401
    // ----------------------------------------------------------------

    public function testRemoveReturns401WhenNotAuthenticated(): void
    {
        $_COOKIE = [];
        $_POST   = ['wine_id' => 1, 'csrf_token' => 'test-csrf-token'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ob_start();
        try {
            $this->makeController()->remove([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // remove — CSRF invalide → 403
    // ----------------------------------------------------------------

    public function testRemoveReturns403WhenCsrfInvalid(): void
    {
        $_COOKIE = ['auth_token' => $this->makeValidJwt(1, 'customer')];
        $_POST   = ['wine_id' => 1, 'csrf_token' => 'WRONG'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController()->remove([]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // Helper : génère un JWT valide pour les tests
    // ----------------------------------------------------------------

    private function makeValidJwt(int $userId, string $role): string
    {
        return \Core\Jwt::generate($userId, $role, 3600);
    }
}
