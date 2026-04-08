<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\AdminGestionAdminsController;
use Core\Exception\HttpException;
use Core\Jwt;

/**
 * Tests d'intégration pour AdminGestionAdminsController.
 *
 * Couvre : index, block, unblock.
 * Toutes les actions exigent le rôle super_admin.
 */
class AdminGestionAdminsControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET', string $uri = '/admin/admins'): AdminGestionAdminsController
    {
        return new AdminGestionAdminsController($this->makeRequest($method, $uri));
    }

    private function makeSuperAdmin(): int
    {
        $id    = $this->insertAdminAccount('super_admin', 'gestion-super@test.local');
        $token = Jwt::generate($id, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($id, $token);
        return $id;
    }

    private function insertTargetAdmin(string $email = 'target-admin@test.local'): int
    {
        return $this->insertAdminAccount('admin', $email);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    /**
     * GET /admin/admins sans auth → HttpException.
     */
    public function testIndexRequiresAuth(): void
    {
        $_COOKIE = [];
        $this->expectException(HttpException::class);

        ob_start();
        try {
            $this->makeController()->index([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * GET /admin/admins avec rôle admin (pas super_admin) → 404.
     */
    public function testIndexRequiresSuperAdmin(): void
    {
        // adminId de setUp est role 'admin' — pas super_admin
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->index([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * GET /admin/admins avec super_admin → rendu de la liste.
     */
    public function testIndexRendersList(): void
    {
        $this->makeSuperAdmin();

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin', strtolower($output));
    }

    // ----------------------------------------------------------------
    // block
    // ----------------------------------------------------------------

    /**
     * POST block sans CSRF valide → redirect (302).
     */
    public function testBlockRejectsInvalidCsrf(): void
    {
        $this->makeSuperAdmin();
        $targetId = $this->insertTargetAdmin('block-csrf@test.local');
        $_POST['csrf_token'] = 'wrong-token';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->block(['id' => (string) $targetId]);
    }

    /**
     * POST block avec ID = 0 → redirect (302).
     */
    public function testBlockRejectsInvalidId(): void
    {
        $this->makeSuperAdmin();
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->block(['id' => '0']);
    }

    /**
     * POST block sur son propre compte → 403.
     */
    public function testBlockForbidsSelfBlock(): void
    {
        $superAdminId = $this->makeSuperAdmin();
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $this->makeController('POST')->block(['id' => (string) $superAdminId]);
    }

    /**
     * POST block avec ID inexistant → redirect (302).
     */
    public function testBlockRejectsUnknownId(): void
    {
        $this->makeSuperAdmin();
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->block(['id' => '999999']);
    }

    /**
     * POST block valide → compte bloqué + redirect (302).
     */
    public function testBlockSucceedsAndUpdatesDatabase(): void
    {
        $this->makeSuperAdmin();
        $targetId = $this->insertTargetAdmin('block-target@test.local');
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->block(['id' => (string) $targetId]);
        } catch (HttpException $e) {
            $row = self::$db->fetchOne(
                "SELECT is_blocked FROM accounts WHERE id = ?",
                [$targetId]
            );
            $this->assertNotFalse($row);
            $this->assertSame(1, (int) $row['is_blocked']);
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // unblock
    // ----------------------------------------------------------------

    /**
     * POST unblock sans CSRF valide → redirect (302).
     */
    public function testUnblockRejectsInvalidCsrf(): void
    {
        $this->makeSuperAdmin();
        $targetId = $this->insertTargetAdmin('unblock-csrf@test.local');
        $_POST['csrf_token'] = 'wrong-token';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->unblock(['id' => (string) $targetId]);
    }

    /**
     * POST unblock avec ID = 0 → redirect (302).
     */
    public function testUnblockRejectsInvalidId(): void
    {
        $this->makeSuperAdmin();
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->unblock(['id' => '0']);
    }

    /**
     * POST unblock sur son propre compte → 403.
     */
    public function testUnblockForbidsSelfUnblock(): void
    {
        $superAdminId = $this->makeSuperAdmin();
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $this->makeController('POST')->unblock(['id' => (string) $superAdminId]);
    }

    /**
     * POST unblock avec ID inexistant → redirect (302).
     */
    public function testUnblockRejectsUnknownId(): void
    {
        $this->makeSuperAdmin();
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->unblock(['id' => '999999']);
    }

    /**
     * POST unblock valide → compte débloqué (active) + redirect (302).
     */
    public function testUnblockSucceedsAndUpdatesDatabase(): void
    {
        $this->makeSuperAdmin();
        $targetId = $this->insertTargetAdmin('unblock-target@test.local');

        // Bloquer d'abord
        self::$db->execute(
            "UPDATE accounts SET is_blocked = 1 WHERE id = ?",
            [$targetId]
        );

        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->unblock(['id' => (string) $targetId]);
        } catch (HttpException $e) {
            $row = self::$db->fetchOne(
                "SELECT is_blocked FROM accounts WHERE id = ?",
                [$targetId]
            );
            $this->assertNotFalse($row);
            $this->assertSame(0, (int) $row['is_blocked']);
            throw $e;
        }
    }
}
