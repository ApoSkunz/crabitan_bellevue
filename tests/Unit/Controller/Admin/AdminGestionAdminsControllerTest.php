<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\AdminGestionAdminsController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour AdminGestionAdminsController.
 *
 * Vérifie les guards (rôle super_admin requis, CSRF, self-action),
 * sans requête BDD réelle (stub Database).
 */
class AdminGestionAdminsControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('fetchAll')->willReturn([]);

        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_SESSION = ['csrf' => 'valid-token'];
        $_POST    = [];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD']  = 'GET';
        $_SERVER['REQUEST_URI']     = '/admin/admins';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
    }

    private function withActiveSession(int $userId, string $role = 'admin'): void
    {
        $_ENV['JWT_SECRET'] ??= 'test-secret-key-for-unit-tests';
        $token = Jwt::generate($userId, $role);
        $_COOKIE['auth_token'] = $token;

        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnCallback(
            static function (string $sql) use ($userId, $role) {
                if (str_contains($sql, 'connections')) {
                    return ['id' => 1];
                }
                if (str_contains($sql, 'is_blocked')) {
                    return ['is_blocked' => 0];
                }
                return [
                    'id'        => $userId,
                    'email'     => 'admin@test.local',
                    'role'      => $role,
                    'is_blocked' => 0,
                    'firstname' => 'Test',
                    'lastname'  => 'Admin',
                ];
            }
        );
        $dbMock->method('fetchAll')->willReturn([]);
        $dbMock->method('execute')->willReturn(0);
        $this->instanceProp->setValue(null, $dbMock);
    }

    public function testIndexAborts404ForNonAdmin(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        (new AdminGestionAdminsController(new Request()))->index([]);
    }

    public function testIndexAborts403ForSimpleAdmin(): void
    {
        $this->withActiveSession(1, 'admin');
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        (new AdminGestionAdminsController(new Request()))->index([]);
    }

    public function testBlockRedirectsOnInvalidCsrf(): void
    {
        $this->withActiveSession(10, 'super_admin');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = 'wrong-token';
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        (new AdminGestionAdminsController(new Request()))->block(['id' => '2']);
    }

    public function testBlockAborts403WhenTargetIsSelf(): void
    {
        $selfId = 10;
        $this->withActiveSession($selfId, 'super_admin');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = 'valid-token';
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        (new AdminGestionAdminsController(new Request()))->block(['id' => (string) $selfId]);
    }

    public function testUnblockRedirectsOnInvalidCsrf(): void
    {
        $this->withActiveSession(10, 'super_admin');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = 'wrong-token';
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        (new AdminGestionAdminsController(new Request()))->unblock(['id' => '2']);
    }

    public function testUnblockAborts403WhenTargetIsSelf(): void
    {
        $selfId = 10;
        $this->withActiveSession($selfId, 'super_admin');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = 'valid-token';
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        (new AdminGestionAdminsController(new Request()))->unblock(['id' => (string) $selfId]);
    }

    public function testBlockRedirectsOnInvalidId(): void
    {
        $this->withActiveSession(10, 'super_admin');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = 'valid-token';
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        (new AdminGestionAdminsController(new Request()))->block(['id' => '0']);
    }

    public function testRequireSuperAdminAborts403ForAdmin(): void
    {
        $this->withActiveSession(1, 'admin');
        $ref  = new \ReflectionMethod(\Controller\Admin\AdminController::class, 'requireSuperAdmin');
        $ctrl = new AdminGestionAdminsController(new Request());
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);
        $ref->invoke($ctrl);
    }
}
