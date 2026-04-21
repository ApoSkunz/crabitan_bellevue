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

    public function testIndexWithValidPerPageRendersView(): void
    {
        $_GET['per_page'] = '25';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

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

    public function testVerifyAborts404ForNonSuperAdmin(): void
    {
        // admin (non super_admin) → requireSuperAdmin retourne 404 (pas de révélation de route)
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

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
        $superAdminId          = $this->insertAdminAccount('super_admin', 'superadmin@test.local');
        $token                 = Jwt::generate($superAdminId, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($superAdminId, $token);
        $_POST['csrf_token'] = 'wrong';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->verify(['id' => '999']);
    }

    // ----------------------------------------------------------------
    // verify — super_admin, CSRF valide → redirect 302
    // ----------------------------------------------------------------

    public function testVerifySuccessAsSuperAdmin(): void
    {
        $superAdminId          = $this->insertAdminAccount('super_admin', 'superadmin@test.local');
        $token                 = Jwt::generate($superAdminId, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($superAdminId, $token);
        $_POST['csrf_token']   = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->verify(['id' => (string) $this->adminId]);
    }

    // ----------------------------------------------------------------
    // resetPassword — rôle insuffisant
    // ----------------------------------------------------------------

    public function testResetPasswordAborts404ForNonSuperAdmin(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController('POST')->resetPassword(['id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // resetPassword — CSRF invalide
    // ----------------------------------------------------------------

    public function testResetPasswordRedirectsOnInvalidCsrf(): void
    {
        $superAdminId          = $this->insertAdminAccount('super_admin', 'superadmin2@test.local');
        $token                 = Jwt::generate($superAdminId, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($superAdminId, $token);
        $_POST['csrf_token']   = 'invalid';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->resetPassword(['id' => '999']);
    }

    // ----------------------------------------------------------------
    // resetPassword — compte introuvable
    // ----------------------------------------------------------------

    public function testResetPasswordRedirectsOnUnknownAccount(): void
    {
        $superAdminId          = $this->insertAdminAccount('super_admin', 'superadmin3@test.local');
        $token                 = Jwt::generate($superAdminId, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($superAdminId, $token);
        $_POST['csrf_token']   = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->resetPassword(['id' => '999999']);
    }

    // ----------------------------------------------------------------
    // resetPassword — compte Google (pas de mot de passe)
    // ----------------------------------------------------------------

    public function testResetPasswordRedirectsForGoogleOnlyAccount(): void
    {
        $superAdminId          = $this->insertAdminAccount('super_admin', 'superadmin4@test.local');
        $token                 = Jwt::generate($superAdminId, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($superAdminId, $token);
        $_POST['csrf_token']   = self::CSRF_TOKEN;

        $googleId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES ('google.only@test.local', NULL, 'individual', 'customer', 'fr', NOW())",
            []
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Google', 'User', 'M')",
            [$googleId]
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->resetPassword(['id' => (string) $googleId]);
    }

    // ----------------------------------------------------------------
    // resetPassword — succès (mail mocké)
    // ----------------------------------------------------------------

    public function testResetPasswordSendsLinkAndRedirects(): void
    {
        $superAdminId          = $this->insertAdminAccount('super_admin', 'superadmin5@test.local');
        $token                 = Jwt::generate($superAdminId, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($superAdminId, $token);
        $_POST['csrf_token']   = self::CSRF_TOKEN;
        $_ENV['APP_URL']       = 'https://crabitan.test';

        $customerId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES ('customer.reset@test.local', ?, 'individual', 'customer', 'fr', NOW())",
            [password_hash('Test123!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Dupont', 'Jean', 'M')",
            [$customerId]
        );

        $uri        = '/admin/comptes/' . $customerId . '/reset-password';
        $controller = new class ($this->makeRequest('POST', $uri)) extends \Controller\Admin\AccountAdminController {
            protected function newMailService(): \Service\MailService
            {
                return new class extends \Service\MailService {
                    public function sendAdminPasswordReset(string $to, string $name, string $resetUrl, string $lang): void
                    {
                        // mail stubbed — pas d'envoi SMTP réel
                    }
                };
            }
        };

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $controller->resetPassword(['id' => (string) $customerId]);
    }
}
