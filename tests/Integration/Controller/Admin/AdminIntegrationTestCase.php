<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Base class pour les tests d'intégration des controllers admin.
 * Crée un compte admin, positionne le cookie JWT et le token CSRF avant chaque test.
 */
abstract class AdminIntegrationTestCase extends IntegrationTestCase
{
    protected const CSRF_TOKEN = 'test-csrf-admin-token';

    protected int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = ['csrf' => self::CSRF_TOKEN];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';

        $this->adminId = $this->insertAdminAccount();
        $_COOKIE['auth_token'] = Jwt::generate($this->adminId, 'admin');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
    }

    protected function insertAdminAccount(string $role = 'admin', string $email = 'admin@test.local'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, ?, 'fr', NOW())",
            [$email, password_hash('Admin123!', PASSWORD_BCRYPT), $role]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Admin', 'Test', 'M')",
            [$id]
        );
        return $id;
    }

    protected function makeRequest(string $method = 'GET', string $uri = '/admin'): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        return new Request();
    }
}
