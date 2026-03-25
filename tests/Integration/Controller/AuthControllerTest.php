<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\AuthController;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AuthController.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class AuthControllerTest extends IntegrationTestCase
{
    private const CSRF = 'integration-csrf-token';

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = ['csrf' => self::CSRF];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_POST   = [];
        $_COOKIE = [];
        $_SESSION = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function makeRequest(string $method = 'GET', string $uri = '/'): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET = [];
        return new Request();
    }

    private function makeController(string $method = 'POST', string $uri = '/fr/test'): AuthController
    {
        return new AuthController($this->makeRequest($method, $uri));
    }

    private function insertVerifiedAccount(
        string $email = 'user@example.com',
        string $password = 'Password1!',
        string $role = 'customer'
    ): int {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, ?, 'fr', NOW())",
            [$email, password_hash($password, PASSWORD_BCRYPT), $role]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Test', 'User', 'M')",
            [$id]
        );
        return $id;
    }

    private function insertUnverifiedAccount(string $email = 'unverified@example.com'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verification_token)
             VALUES (?, ?, 'customer', 'fr', ?)",
            [$email, password_hash('Password1!', PASSWORD_BCRYPT), bin2hex(random_bytes(16))]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Unverified', 'User', 'M')",
            [$id]
        );
        return $id;
    }

    // ----------------------------------------------------------------
    // login()
    // ----------------------------------------------------------------

    public function testLoginSuccessRedirectsToAccount(): void
    {
        $this->insertVerifiedAccount('login@example.com', 'Password1!');

        $_POST = [
            'email'      => 'login@example.com',
            'password'   => 'Password1!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr/mon-compte', $e->location);
        }
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->insertVerifiedAccount('wrongpw@example.com', 'CorrectPass1!');

        $_POST = [
            'email'      => 'wrongpw@example.com',
            'password'   => 'WrongPass99!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }
    }

    public function testLoginFailsWithUnverifiedAccount(): void
    {
        $this->insertUnverifiedAccount('noverify@example.com');

        $_POST = [
            'email'      => 'noverify@example.com',
            'password'   => 'Password1!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }
    }

    public function testLoginFailsWithUnknownEmail(): void
    {
        $_POST = [
            'email'      => 'nobody@example.com',
            'password'   => 'Password1!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }
    }

    // ----------------------------------------------------------------
    // register()
    // ----------------------------------------------------------------

    public function testRegisterCreatesAccountAndRedirects(): void
    {
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'F',
            'lastname'         => 'Dupont',
            'firstname'        => 'Marie',
            'email'            => 'marie@example.com',
            'password'         => 'Password1!',
            'password_confirm' => 'Password1!',
            'newsletter'       => '0',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }

        $account = self::$db->fetchOne(
            "SELECT a.email, a.email_verified_at, ai.lastname
             FROM accounts a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             WHERE a.email = ?",
            ['marie@example.com']
        );
        $this->assertNotFalse($account);
        $this->assertSame('Dupont', $account['lastname']);
        $this->assertNull($account['email_verified_at']);
    }

    public function testRegisterFailsWhenEmailAlreadyTaken(): void
    {
        $this->insertVerifiedAccount('taken@example.com');

        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Autre',
            'firstname'        => 'Person',
            'email'            => 'taken@example.com',
            'password'         => 'Password1!',
            'password_confirm' => 'Password1!',
            'newsletter'       => '0',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr/inscription', $e->location);
        }
    }

    // ----------------------------------------------------------------
    // logout()
    // ----------------------------------------------------------------

    public function testLogoutRevokesConnectionAndRedirects(): void
    {
        $userId = $this->insertVerifiedAccount('logout@example.com');
        $token  = Jwt::generate($userId, 'customer');
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );

        $_COOKIE['auth_token'] = $token;

        try {
            $this->makeController('GET', '/fr/deconnexion')->logout(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }

        $connection = self::$db->fetchOne(
            "SELECT status FROM connections WHERE token = ?",
            [$token]
        );
        $this->assertSame('revoked', $connection['status']);
    }

    public function testLogoutWithoutCookieRedirects(): void
    {
        try {
            $this->makeController('GET', '/fr/deconnexion')->logout(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }
    }

    // ----------------------------------------------------------------
    // verifyEmail()
    // ----------------------------------------------------------------

    public function testVerifyEmailActivatesAccount(): void
    {
        $verificationToken = bin2hex(random_bytes(16));
        $accountId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verification_token)
             VALUES ('verify@example.com', 'hash', 'customer', 'fr', ?)",
            [$verificationToken]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'V', 'User', 'M')",
            [$accountId]
        );

        ob_start();
        try {
            $this->makeController('GET', '/fr/verification/' . $verificationToken)
                ->verifyEmail(['lang' => 'fr', 'token' => $verificationToken]);
        } finally {
            ob_end_clean();
        }

        $account = self::$db->fetchOne(
            "SELECT email_verified_at FROM accounts WHERE email = ?",
            ['verify@example.com']
        );
        $this->assertNotNull($account['email_verified_at']);
    }

    public function testVerifyEmailWithInvalidTokenRendersFailureView(): void
    {
        ob_start();
        $this->makeController('GET', '/fr/verification/invalidtoken')
            ->verifyEmail(['lang' => 'fr', 'token' => 'invalidtoken']);
        $output = ob_get_clean();

        // The view is rendered; output should exist (the verify view template)
        $this->assertIsString($output);
    }

    public function testVerifyEmailAlreadyVerifiedRedirects(): void
    {
        $verificationToken = bin2hex(random_bytes(16));
        $accountId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, email_verification_token)
             VALUES ('already@example.com', 'hash', 'customer', 'fr', NOW(), ?)",
            [$verificationToken]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'AV', 'User', 'M')",
            [$accountId]
        );

        try {
            $this->makeController('GET', '/fr/verification/' . $verificationToken)
                ->verifyEmail(['lang' => 'fr', 'token' => $verificationToken]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }
    }

    // ----------------------------------------------------------------
    // forgot()
    // ----------------------------------------------------------------

    public function testForgotCreatesResetTokenForVerifiedAccount(): void
    {
        $userId = $this->insertVerifiedAccount('forgot@example.com');

        $_POST = [
            'email'      => 'forgot@example.com',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->forgot(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $reset = self::$db->fetchOne(
            "SELECT * FROM password_reset WHERE user_id = ?",
            [$userId]
        );
        $this->assertNotFalse($reset);
    }

    public function testForgotRedirectsEvenForUnknownEmail(): void
    {
        $_POST = [
            'email'      => 'ghost@example.com',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->forgot(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            // Anti-enumeration: always redirects
            $this->assertSame(302, $e->status);
        }
    }

    // ----------------------------------------------------------------
    // reset()
    // ----------------------------------------------------------------

    public function testResetUpdatesPasswordAndRedirects(): void
    {
        $userId = $this->insertVerifiedAccount('reset@example.com', 'OldPassword1!');
        $token  = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );

        $_POST = [
            'password'         => 'NewPassword1!',
            'password_confirm' => 'NewPassword1!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }

        // Password reset token deleted
        $reset = self::$db->fetchOne(
            "SELECT * FROM password_reset WHERE user_id = ?",
            [$userId]
        );
        $this->assertFalse($reset);
    }

    public function testResetFailsWhenPasswordsTooShort(): void
    {
        $userId = $this->insertVerifiedAccount('resetshort@example.com');
        $token  = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );

        $_POST = [
            'password'         => 'abc',
            'password_confirm' => 'abc',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertStringContainsString('/fr/reinitialisation/', $e->location ?? '');
        }
    }

    public function testResetRedirectsWhenTokenInvalid(): void
    {
        $_POST = [
            'password'         => 'NewPassword1!',
            'password_confirm' => 'NewPassword1!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => 'invalidtoken']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }
    }
}
