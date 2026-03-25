<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\AccountAdminController;
use Core\Exception\HttpException;
use Core\Jwt;

class AccountAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): AccountAdminController
    {
        return new AccountAdminController($this->makeRequest($method, '/admin/comptes'));
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersAccountsList(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
        $this->assertStringContainsString('Comptes', $output);
    }

    public function testIndexWithRoleFilterRendersView(): void
    {
        $_GET['role'] = 'customer';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-filters', $output);
    }

    public function testIndexWithSearchRendersView(): void
    {
        $_GET['search'] = 'test@example.com';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    // ----------------------------------------------------------------
    // verify — rôle insuffisant
    // ----------------------------------------------------------------

    public function testVerifyAborts403ForNonSuperAdmin(): void
    {
        // admin (non super_admin) → doit recevoir 403
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            $this->makeController('POST')->verify(['id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // verify — super_admin, CSRF invalide
    // ----------------------------------------------------------------

    public function testVerifyRedirectsOnInvalidCsrfAsSuperAdmin(): void
    {
        // Remplacer le cookie par un super_admin
        $superAdminId = $this->insertAdminAccount('super_admin', 'superadmin@test.local');
        $_COOKIE['auth_token'] = Jwt::generate($superAdminId, 'super_admin');
        $_POST['csrf_token'] = 'wrong';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->verify(['id' => '999']);
    }
}
