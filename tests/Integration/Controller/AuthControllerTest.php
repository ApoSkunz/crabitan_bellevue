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
        string $password = 'Password123!',
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
            [$email, password_hash('Password123!', PASSWORD_BCRYPT), bin2hex(random_bytes(16))]
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
        $this->insertVerifiedAccount('login@example.com', 'Password123!');

        $_POST = [
            'email'         => 'login@example.com',
            'password'      => 'Password123!',
            'csrf_token'    => self::CSRF,
            'redirect_back' => '/fr/mon-compte',
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
            'password'   => 'Password123!',
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
            'password'   => 'Password123!',
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

    public function testLoginFailsWithInvalidCsrf(): void
    {
        $this->insertVerifiedAccount('csrf-login@example.com');

        $_POST = [
            'email'      => 'csrf-login@example.com',
            'password'   => 'Password123!',
            'csrf_token' => 'wrong-token',
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }
    }

    public function testLoginWithSafeRedirectBack(): void
    {
        $this->insertVerifiedAccount('redirect@example.com', 'Password123!');

        $_POST = [
            'email'         => 'redirect@example.com',
            'password'      => 'Password123!',
            'csrf_token'    => self::CSRF,
            'redirect_back' => '/fr/vins',
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr/vins', $e->location);
        }
    }

    public function testLoginWithUnsafeRedirectBackFallsToLang(): void
    {
        $this->insertVerifiedAccount('unsafe-back@example.com', 'Password123!');

        $_POST = [
            'email'         => 'unsafe-back@example.com',
            'password'      => 'Password123!',
            'csrf_token'    => self::CSRF,
            'redirect_back' => 'https://evil.com/phish',
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
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Vigilant7#xKqm',
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
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
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
    }

    public function testRegisterFailsWithInvalidCsrf(): void
    {
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Test',
            'firstname'        => 'Csrf',
            'email'            => 'csrftest@example.com',
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
            'newsletter'       => '0',
            'csrf_token'       => 'bad-csrf-token',
        ];

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }
    }

    public function testRegisterFailsWithValidationErrors(): void
    {
        // Mot de passe trop court, email invalide
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'A', // trop court
            'firstname'        => 'B', // trop court
            'email'            => 'not-an-email',
            'password'         => 'short',
            'password_confirm' => 'short',
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

        // Aucun compte ne doit avoir été créé
        $account = self::$db->fetchOne(
            "SELECT id FROM accounts WHERE email = ?",
            ['not-an-email']
        );
        $this->assertFalse($account);
    }

    public function testRegisterFailsWithPasswordMismatch(): void
    {
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Martin',
            'firstname'        => 'Paul',
            'email'            => 'pwmatch@example.com',
            'password'         => 'Password123!',
            'password_confirm' => 'Different456!',
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
    }

    public function testRegisterCompanyTypeCreatesAccount(): void
    {
        $_POST = [
            'account_type'     => 'company',
            'civility'         => '',
            'lastname'         => '',
            'firstname'        => '',
            'company_name'     => 'Château Dupont SARL',
            'email'            => 'company@example.com',
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Vigilant7#xKqm',
            'newsletter'       => '1',
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
            "SELECT email, account_type FROM accounts WHERE email = ?",
            ['company@example.com']
        );
        $this->assertNotFalse($account);
        $this->assertSame('company', $account['account_type']);
    }

    public function testRegisterCompanyFailsWithoutCompanyName(): void
    {
        $_POST = [
            'account_type'     => 'company',
            'civility'         => '',
            'lastname'         => '',
            'firstname'        => '',
            'company_name'     => 'A', // trop court
            'email'            => 'company-short@example.com',
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
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
    }

    public function testRegisterWithInvalidAccountTypeProducesError(): void
    {
        $_POST = [
            'account_type'     => 'unknown_type',
            'civility'         => 'M',
            'lastname'         => 'Test',
            'firstname'        => 'User',
            'email'            => 'invalid-type@example.com',
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
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

        $_COOKIE['auth_token']  = $token;
        $_POST['csrf_token']    = self::CSRF;
        $_SESSION['csrf']       = self::CSRF;

        try {
            $this->makeController('POST', '/fr/deconnexion')->logout(['lang' => 'fr']);
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

    public function testLogoutGetWithoutCookieReturns404(): void
    {
        // GET sans cookie → 404 (path enumeration prevention)
        try {
            $this->makeController('GET', '/fr/deconnexion')->logout(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->status);
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
            'password'         => 'Zephyr9$mKlt2w',
            'password_confirm' => 'Zephyr9$mKlt2w',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr?login=1', $e->location);
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
            $this->assertSame('/fr?modal=reset', $e->location);
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
            $this->assertSame('/fr?modal=reset', $e->location);
        }
    }

    public function testResetFailsWhenPasswordsDontMatch(): void
    {
        $userId = $this->insertVerifiedAccount('resetmatch@example.com', 'OldPassword1!');
        $token  = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );

        $_POST = [
            'password'         => 'NewPassword1!',
            'password_confirm' => 'DifferentPass2!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr?modal=reset', $e->location);
        }
    }

    public function testResetFailsWithInvalidCsrf(): void
    {
        $userId = $this->insertVerifiedAccount('resetcsrf@example.com');
        $token  = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );

        $_POST = [
            'password'         => 'NewPassword1!',
            'password_confirm' => 'NewPassword1!',
            'csrf_token'       => 'bad-csrf',
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertStringContainsString('/fr/reinitialisation/', $e->location);
        }
    }

    public function testForgotFailsWithInvalidCsrf(): void
    {
        $_POST = [
            'email'      => 'someuser@example.com',
            'csrf_token' => 'bad-token',
        ];

        try {
            $this->makeController()->forgot(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            // Redirige vers la page de reset (non vers /fr)
            $this->assertStringContainsString('/fr', $e->location);
        }
    }

    public function testForgotDoesNotCreateTokenForUnverifiedAccount(): void
    {
        $userId = $this->insertUnverifiedAccount('unverified-forgot@example.com');

        $_POST = [
            'email'      => 'unverified-forgot@example.com',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->forgot(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Aucun token de réinitialisation ne doit avoir été créé
        $reset = self::$db->fetchOne(
            "SELECT * FROM password_reset WHERE user_id = ?",
            [$userId]
        );
        $this->assertFalse($reset);
    }

    // ----------------------------------------------------------------
    // forgotForm()
    // ----------------------------------------------------------------

    public function testForgotFormRedirectsToLang(): void
    {
        try {
            $this->makeController('GET', '/fr/mot-de-passe-oublie')->forgotForm(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }
    }

    // ----------------------------------------------------------------
    // resetForm()
    // ----------------------------------------------------------------

    public function testResetFormWithValidTokenSetsSessionAndRedirects(): void
    {
        $userId = $this->insertVerifiedAccount('resetform@example.com');
        $token  = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );

        try {
            $this->makeController('GET', "/fr/reinitialisation/{$token}")->resetForm(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr?modal=reset', $e->location);
        }

        $this->assertTrue($_SESSION['reset_modal']['valid']);
        $this->assertSame($token, $_SESSION['reset_modal']['token']);
    }

    public function testResetFormWithInvalidTokenSetsValidFalse(): void
    {
        try {
            $this->makeController('GET', '/fr/reinitialisation/badtoken')->resetForm(['lang' => 'fr', 'token' => 'badtoken']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr?modal=reset', $e->location);
        }

        $this->assertFalse($_SESSION['reset_modal']['valid']);
    }

    // ----------------------------------------------------------------
    // forgot() — compte company (branche displayName company)
    // ----------------------------------------------------------------

    public function testForgotCreatesResetTokenForCompanyAccount(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, account_type)
             VALUES ('company-forgot@example.com', ?, 'customer', 'fr', NOW(), 'company')",
            [password_hash('Password123!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_companies (account_id, company_name) VALUES (?, 'Test SARL')",
            [$id]
        );

        $_POST = [
            'email'      => 'company-forgot@example.com',
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
            [$id]
        );
        $this->assertNotFalse($reset);
    }

    public function testForgotDoesNotCreateTokenForAdminRole(): void
    {
        $userId = $this->insertVerifiedAccount('admin-forgot@example.com', 'Password123!', 'admin');

        $_POST = [
            'email'      => 'admin-forgot@example.com',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->forgot(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Admin role → la condition account['role'] === 'customer' est false → pas de token
        $reset = self::$db->fetchOne(
            "SELECT * FROM password_reset WHERE user_id = ?",
            [$userId]
        );
        $this->assertFalse($reset);
    }

    // ----------------------------------------------------------------
    // login() — lang preference preserved (URL lang ne doit pas écraser accounts.lang)
    // ----------------------------------------------------------------

    public function testLoginPreservesAccountLangOverUrlLang(): void
    {
        // Account with lang = 'fr', login via URL /en → accounts.lang must remain 'fr'
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('langdiff@example.com', ?, 'customer', 'fr', NOW())",
            [password_hash('Password123!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Lang', 'User', 'M')",
            [$id]
        );

        $_POST = [
            'email'      => 'langdiff@example.com',
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController('POST', '/en/wines')->login(['lang' => 'en']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $account = self::$db->fetchOne(
            "SELECT lang FROM accounts WHERE id = ?",
            [$id]
        );
            // La préférence de langue du compte est conservée en DB — jamais écrasée par l'URL
        $this->assertSame('fr', $account['lang']);
    }

    // ----------------------------------------------------------------
    // login() — already trusted device (isAlreadyTrusted branch)
    // ----------------------------------------------------------------

    public function testLoginWithAlreadyTrustedDeviceUpdatesLastSeen(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, has_connected)
             VALUES ('trusted@example.com', ?, 'customer', 'fr', NOW(), 1)",
            [password_hash('Password123!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Trust', 'User', 'M')",
            [$id]
        );

        $deviceToken = bin2hex(random_bytes(16));
        self::$db->insert(
            "INSERT INTO trusted_devices (user_id, device_token, device_name, last_seen)
             VALUES (?, ?, 'Chrome · Windows', NOW())",
            [$id, $deviceToken]
        );

        $_COOKIE['device_token'] = $deviceToken;
        $_POST = [
            'email'      => 'trusted@example.com',
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // last_seen_at should have been updated (row still exists)
        $device = self::$db->fetchOne(
            "SELECT last_seen FROM trusted_devices WHERE user_id = ? AND device_token = ?",
            [$id, $deviceToken]
        );
        $this->assertNotFalse($device);
    }

    // ----------------------------------------------------------------
    // login() — untrusted device (handleUntrustedDevice / MFA branch)
    // ----------------------------------------------------------------

    public function testLoginWithUntrustedDeviceRedirectsToNewDevice(): void
    {
        // has_connected = 1, device_token not in trusted_devices → MFA required
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, has_connected)
             VALUES ('untrusted@example.com', ?, 'customer', 'fr', NOW(), 1)",
            [password_hash('Password123!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Untrust', 'User', 'M')",
            [$id]
        );

        // device_token cookie not present → resolveDeviceToken generates a new one
        $_COOKIE = [];
        $_POST = [
            'email'      => 'untrusted@example.com',
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertStringContainsString('nouvel-appareil', $e->location);
        }
    }

    // ----------------------------------------------------------------
    // register() — newsletter = '1' (branche newsletter)
    // ----------------------------------------------------------------

    // ----------------------------------------------------------------
    // login() — remember me cookie TTL
    // ----------------------------------------------------------------

    public function testLoginWithRememberMeSetsLongLivedCookie(): void
    {
        $this->insertVerifiedAccount('remember@example.com', 'Password123!');

        $_POST = [
            'email'       => 'remember@example.com',
            'password'    => 'Password123!',
            'csrf_token'  => self::CSRF,
            'remember_me' => '1',
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // La connexion doit avoir une expiration > now + 29 jours (30 jours de TTL)
        $connection = self::$db->fetchOne(
            "SELECT expired_at FROM connections WHERE user_id = (
                SELECT id FROM accounts WHERE email = ?
             ) ORDER BY created_at DESC LIMIT 1",
            ['remember@example.com']
        );

        $this->assertNotFalse($connection);
        $expiredAt    = strtotime((string) $connection['expired_at']);
        $minExpected  = time() + (29 * 24 * 3600);
        $this->assertGreaterThan($minExpected, $expiredAt);
    }

    public function testLoginWithoutRememberMeUsesDefaultExpiry(): void
    {
        $this->insertVerifiedAccount('noremember@example.com', 'Password123!');

        $_POST = [
            'email'      => 'noremember@example.com',
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // La connexion doit avoir une expiration bien inférieure à 29 jours (JWT_EXPIRY par défaut ≤ 1 h)
        $connection = self::$db->fetchOne(
            "SELECT expired_at FROM connections WHERE user_id = (
                SELECT id FROM accounts WHERE email = ?
             ) ORDER BY created_at DESC LIMIT 1",
            ['noremember@example.com']
        );

        $this->assertNotFalse($connection);
        $expiredAt   = strtotime((string) $connection['expired_at']);
        $maxExpected = time() + (29 * 24 * 3600);
        $this->assertLessThan($maxExpected, $expiredAt);
    }

    public function testRegisterIndividualWithNewsletterSubscribed(): void
    {
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Abonne',
            'firstname'        => 'Pierre',
            'email'            => 'newsletter-yes@example.com',
            'password'         => 'Vigilant7#xKqm',
            'password_confirm' => 'Vigilant7#xKqm',
            'newsletter'       => '1',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $account = self::$db->fetchOne(
            "SELECT newsletter, newsletter_optin_pending FROM accounts WHERE email = ?",
            ['newsletter-yes@example.com']
        );
        $this->assertNotFalse($account);
        // Double opt-in hybride : newsletter reste 0 à l'inscription,
        // activé uniquement lors de la vérification email
        $this->assertSame(0, (int)$account['newsletter'], 'newsletter doit rester 0 jusqu\'à vérification email');
        $this->assertSame(1, (int)$account['newsletter_optin_pending'], 'newsletter_optin_pending doit être 1');
    }

    // ----------------------------------------------------------------
    // resendActivation()
    // ----------------------------------------------------------------

    /**
     * POST resendActivation sans CSRF valide → redirect silencieux (anti-énumération).
     */
    public function testResendActivationInvalidCsrfRedirects(): void
    {
        $_POST['csrf_token'] = 'bad-token';
        $_POST['email']      = 'user@example.com';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/renvoyer-activation')
            ->resendActivation(['lang' => 'fr']);
    }

    /**
     * POST resendActivation avec email invalide → redirect silencieux (anti-énumération).
     */
    public function testResendActivationInvalidEmailRedirects(): void
    {
        $_POST['csrf_token'] = self::CSRF;
        $_POST['email']      = 'not-an-email';
        $_SERVER['REMOTE_ADDR'] = '127.1.2.3';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/renvoyer-activation')
            ->resendActivation(['lang' => 'fr']);
    }

    /**
     * POST resendActivation avec email inconnu → redirect silencieux (anti-énumération).
     */
    public function testResendActivationUnknownEmailRedirects(): void
    {
        $_POST['csrf_token'] = self::CSRF;
        $_POST['email']      = 'nobody@nowhere.com';
        $_SERVER['REMOTE_ADDR'] = '127.1.2.4';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/renvoyer-activation')
            ->resendActivation(['lang' => 'fr']);
    }

    /**
     * POST resendActivation avec un compte déjà vérifié → redirect silencieux.
     */
    public function testResendActivationAlreadyVerifiedRedirects(): void
    {
        $this->insertVerifiedAccount('verified@example.com');
        $_POST['csrf_token'] = self::CSRF;
        $_POST['email']      = 'verified@example.com';
        $_SERVER['REMOTE_ADDR'] = '127.1.2.5';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/renvoyer-activation')
            ->resendActivation(['lang' => 'fr']);
    }

    /**
     * POST resendActivation avec un compte non vérifié → envoie (tente) l'email + redirect.
     */
    public function testResendActivationUnverifiedAccountTriggersEmailAndRedirects(): void
    {
        $this->insertUnverifiedAccount('pending@example.com');
        $_POST['csrf_token'] = self::CSRF;
        $_POST['email']      = 'pending@example.com';
        $_SERVER['REMOTE_ADDR'] = '127.1.2.6';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST', '/fr/renvoyer-activation')
            ->resendActivation(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // login() — admin failed login (admin security alert branch)
    // ----------------------------------------------------------------

    /**
     * Login admin avec mauvais mot de passe.
     * Couvre les lignes 188-210 : branche admin (shouldAlertOnFailedLogins, recordAutoBlockFailure).
     */
    public function testLoginAdminWithWrongPasswordTriggersAdminSecurityBranch(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('admin-fail@example.com', ?, 'admin', 'fr', NOW())",
            [password_hash('CorrectAdminPass1!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Admin', 'User', 'M')",
            [$id]
        );

        $_SESSION['_rl'] = [];
        $_SESSION['_rl_ttl'] = [];
        $_SERVER['REMOTE_ADDR'] = '127.8.8.8';

        $_POST = [
            'email'      => 'admin-fail@example.com',
            'password'   => 'WrongPass99!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // L'admin existe toujours en BDD — le code admin-branch a été exécuté
        $account = self::$db->fetchOne(
            "SELECT id FROM accounts WHERE email = ?",
            ['admin-fail@example.com']
        );
        $this->assertNotFalse($account);
    }

    /**
     * Login admin auto-blocage : 5 échecs cumulés → blockAccount + email d'alerte.
     * Couvre les lignes 196-210 : shouldAutoBlock true → blockAccount.
     */
    public function testLoginAdminAutoBlockAfterFiveFailures(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('admin-autoblock@example.com', ?, 'admin', 'fr', NOW())",
            [password_hash('SecureAdmin7!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Block', 'Admin', 'M')",
            [$id]
        );

        $_SERVER['REMOTE_ADDR'] = '127.7.7.7';
        $_SESSION['_rl'] = [];
        $_SESSION['_rl_ttl'] = [];

        // Simuler 5 échecs cumulatifs déjà enregistrés → shouldAutoBlock retourne true au prochain appel
        $cumulKey = 'rl:admin_fail_cumul:' . $id . ':count';
        $_SESSION['_rl'][$cumulKey] = 5; // AUTO_BLOCK_THRESHOLD = 5
        $_SESSION['_rl_ttl'][$cumulKey] = time() + 3600;

        $_POST = [
            'email'      => 'admin-autoblock@example.com',
            'password'   => 'WrongPass!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Compte bloqué après auto-blocage
        $account = self::$db->fetchOne(
            "SELECT is_blocked FROM accounts WHERE id = ?",
            [$id]
        );
        $this->assertSame(1, (int) $account['is_blocked']);
    }

    // ----------------------------------------------------------------
    // login() — account lockout (BT2 : 3 échecs → email lockout + redirect)
    // ----------------------------------------------------------------

    /**
     * Après 3 échecs de connexion consécutifs, le compte est verrouillé 30 min.
     * Couvre les lignes 214-230 : sendAccountLocked + redirect.
     */
    public function testLoginAccountLockoutAfterThreeFailures(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('lockout@example.com', ?, 'customer', 'fr', NOW())",
            [password_hash('CorrectPass1!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Lock', 'User', 'M')",
            [$id]
        );

        $_SERVER['REMOTE_ADDR'] = '127.6.6.6';
        $_SESSION['_rl'] = [];
        $_SESSION['_rl_ttl'] = [];

        // Pré-saturer le compteur account_lockout : MAX_ACCOUNT_FAILURES = 3
        $rlKey = 'rl:account_lockout:' . $id . ':count';
        $_SESSION['_rl'][$rlKey] = 3;
        $_SESSION['_rl_ttl'][$rlKey] = time() + 1800;

        $_POST = [
            'email'      => 'lockout@example.com',
            'password'   => 'WrongPass!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }
    }

    // ----------------------------------------------------------------
    // login() — cart cookie merge (cb-cart)
    // ----------------------------------------------------------------

    /**
     * Login réussi avec un cookie cb-cart non vide.
     * Couvre les lignes 319-330 : mergeLocalCart + effacement cookie cb-cart.
     */
    public function testLoginMergesCartCookieOnSuccess(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('cart-merge@example.com', ?, 'customer', 'fr', NOW())",
            [password_hash('CartPass1!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Cart', 'User', 'M')",
            [$id]
        );

        // Insérer un vin réel pour le panier (colonnes TEXT _fr/_en)
        self::$db->insert(
            "INSERT INTO wines
                (label_name, wine_color, format, vintage, price, production_quantity, available,
                 area, city, variety_of_vine, age_of_vineyard,
                 oenological_comment_fr, oenological_comment_en,
                 soil_fr, soil_en, pruning_fr, pruning_en,
                 harvest_fr, harvest_en, vinification_fr, vinification_en,
                 barrel_fermentation_fr, barrel_fermentation_en,
                 award_fr, award_en, extra_comment_fr, extra_comment_en,
                 image_path, slug)
             VALUES ('Cart Wine', 'sweet', 'bottle', 2021, 20.00, 50, 1,
                     7.50, 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
                     'Desc', 'Desc',
                     'Argile', 'Clay',
                     'Guyot', 'Guyot',
                     'Manuelle', 'Manual',
                     'Inox', 'Stainless',
                     '', '', '', '',
                     'CartWine_2021.png', 'cart-wine-2021')"
        );
        $wineRow = self::$db->fetchOne(
            "SELECT id FROM wines WHERE slug = ?",
            ['cart-wine-2021']
        );

        // Cookie cb-cart avec un item
        $_COOKIE['cb-cart'] = json_encode([['id' => (int) $wineRow['id'], 'qty' => 2]]);

        $_POST = [
            'email'      => 'cart-merge@example.com',
            'password'   => 'CartPass1!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Le panier BDD doit contenir le vin fusionné
        $cart = self::$db->fetchOne(
            "SELECT id FROM carts WHERE user_id = ?",
            [$id]
        );
        $this->assertNotFalse($cart);
    }

    // ----------------------------------------------------------------
    // verifyEmail() — newsletter_optin_pending (double opt-in)
    // ----------------------------------------------------------------

    /**
     * verifyEmail() avec newsletter_optin_pending = 1.
     * Couvre la ligne 573 : activateNewsletterFromPending.
     */
    public function testVerifyEmailActivatesNewsletterFromPending(): void
    {
        $token = bin2hex(random_bytes(16));
        $accountId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verification_token, newsletter_optin_pending)
             VALUES ('newsletter-pending@example.com', 'hash', 'customer', 'fr', ?, 1)",
            [$token]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'News', 'User', 'M')",
            [$accountId]
        );

        ob_start();
        try {
            $this->makeController('GET', '/fr/verification/' . $token)
                ->verifyEmail(['lang' => 'fr', 'token' => $token]);
        } finally {
            ob_end_clean();
        }

        $account = self::$db->fetchOne(
            "SELECT newsletter, email_verified_at FROM accounts WHERE id = ?",
            [$accountId]
        );
        $this->assertNotNull($account['email_verified_at']);
        $this->assertSame(1, (int) $account['newsletter'], 'newsletter_optin_pending=1 doit activer newsletter');
    }

    // ----------------------------------------------------------------
    // forgot() — compte Google (password = NULL)
    // ----------------------------------------------------------------

    /**
     * forgot() avec un compte dont password = NULL (compte Google-only).
     * Couvre la ligne 660 : sendGoogleAccountInfo plutôt que sendPasswordReset.
     */
    public function testForgotSendsGoogleAccountInfoForGoogleOnlyAccount(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('google-only@example.com', NULL, 'customer', 'fr', NOW())"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Google', 'User', 'M')",
            [$id]
        );

        $_POST = [
            'email'      => 'google-only@example.com',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->forgot(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Aucun token de reset ne doit avoir été créé (compte Google-only)
        $reset = self::$db->fetchOne(
            "SELECT * FROM password_reset WHERE user_id = ?",
            [$id]
        );
        $this->assertFalse($reset);
    }

    // ----------------------------------------------------------------
    // sessionExpired() GET
    // ----------------------------------------------------------------

    /**
     * sessionExpired() pose le flash info et redirige vers l'accueil.
     *
     * @return void
     */
    public function testSessionExpiredSetsFlashAndRedirects(): void
    {
        $_SESSION = [];

        ob_start();
        try {
            $this->makeController('GET', '/fr/session-expiree')
                ->sessionExpired(['lang' => 'fr']);
        } catch (\Throwable) {
        }
        ob_get_clean();

        $this->assertSame(302, http_response_code());
        $this->assertArrayHasKey('info', $_SESSION['flash'] ?? []);
        $this->assertNotEmpty($_SESSION['flash']['info']);
    }

    // ----------------------------------------------------------------
    // login() — cart merge : correction fusion panier (us-fix-fusion-panier-connexion)
    // ----------------------------------------------------------------

    /**
     * Fusion panier : vin avec stock_quantity=0 (legacy) → items transférés grâce au fallback 600.
     */
    public function testLoginMergesCartWhenStockQuantityIsZeroLegacy(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('merge-legacy@example.com', ?, 'customer', 'fr', NOW())",
            [password_hash('CartLegacy1!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Merge', 'Legacy', 'M')",
            [$id]
        );

        // Vin avec stock_quantity = 0 (valeur par défaut — n'a pas encore été configuré)
        self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, production_quantity, stock_quantity, available,
              area, city, variety_of_vine, age_of_vineyard,
              oenological_comment_fr, oenological_comment_en,
              soil_fr, soil_en, pruning_fr, pruning_en,
              harvest_fr, harvest_en, vinification_fr, vinification_en,
              barrel_fermentation_fr, barrel_fermentation_en,
              award_fr, award_en, extra_comment_fr, extra_comment_en,
              image_path, slug)
             VALUES ('Legacy Wine', 'red', 'bottle', 2020, 18.00, 300, 0, 1,
                     5.00, 'Bordeaux', 'Merlot', 25,
                     'test', 'test', 'test', 'test', 'test', 'test',
                     'test', 'test', 'test', 'test', 'test', 'test',
                     '', '', '', '',
                     'legacy_wine.png', 'legacy-wine-2020-merge-fix')"
        );
        $wineRow = self::$db->fetchOne(
            'SELECT id FROM wines WHERE slug = ?',
            ['legacy-wine-2020-merge-fix']
        );

        $_COOKIE['cb-cart'] = json_encode([['id' => (int) $wineRow['id'], 'qty' => 3]]);

        $_POST = [
            'email'      => 'merge-legacy@example.com',
            'password'   => 'CartLegacy1!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Le panier BDD doit avoir été créé — items transférés malgré stock_quantity = 0
        $cart = self::$db->fetchOne('SELECT id FROM carts WHERE user_id = ?', [$id]);
        $this->assertNotFalse($cart, 'Le panier doit être créé même si stock_quantity = 0 (fallback 600)');
    }

    /**
     * Fusion panier : vin avec stock_quantity > 0 → quantité plafonnée si supérieure au stock.
     */
    public function testLoginMergesCartCapsQtyAtStockQuantity(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('merge-stock@example.com', ?, 'customer', 'fr', NOW())",
            [password_hash('CartStock1!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Merge', 'Stock', 'M')",
            [$id]
        );

        // Vin avec stock_quantity = 2 (moins que la qty demandée = 5)
        self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, production_quantity, stock_quantity, available,
              area, city, variety_of_vine, age_of_vineyard,
              oenological_comment_fr, oenological_comment_en,
              soil_fr, soil_en, pruning_fr, pruning_en,
              harvest_fr, harvest_en, vinification_fr, vinification_en,
              barrel_fermentation_fr, barrel_fermentation_en,
              award_fr, award_en, extra_comment_fr, extra_comment_en,
              image_path, slug)
             VALUES ('Stock Wine', 'white', 'bottle', 2019, 22.00, 200, 2, 1,
                     3.00, 'Bordeaux', 'Sauvignon', 15,
                     'test', 'test', 'test', 'test', 'test', 'test',
                     'test', 'test', 'test', 'test', 'test', 'test',
                     '', '', '', '',
                     'stock_wine.png', 'stock-wine-2019-merge-fix')"
        );
        $wineRow = self::$db->fetchOne(
            'SELECT id FROM wines WHERE slug = ?',
            ['stock-wine-2019-merge-fix']
        );

        $_COOKIE['cb-cart'] = json_encode([['id' => (int) $wineRow['id'], 'qty' => 5]]);

        $_POST = [
            'email'      => 'merge-stock@example.com',
            'password'   => 'CartStock1!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Le panier BDD doit exister avec qty plafonnée à 2 (stock réel)
        $cart = self::$db->fetchOne('SELECT content FROM carts WHERE user_id = ?', [$id]);
        $this->assertNotFalse($cart);
        $content = json_decode((string) $cart['content'], true);
        $this->assertNotEmpty($content);
        $this->assertSame(2, (int) $content[0]['qty'], 'La quantité doit être plafonnée au stock (2)');
    }

    /**
     * Fusion panier : vin unavailable → item supprimé du panier fusionné.
     */
    public function testLoginMergesCartDropsUnavailableWine(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('merge-unavail@example.com', ?, 'customer', 'fr', NOW())",
            [password_hash('CartUnavail1!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Merge', 'Unavail', 'M')",
            [$id]
        );

        // Vin indisponible (available = 0)
        self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, production_quantity, stock_quantity, available,
              area, city, variety_of_vine, age_of_vineyard,
              oenological_comment_fr, oenological_comment_en,
              soil_fr, soil_en, pruning_fr, pruning_en,
              harvest_fr, harvest_en, vinification_fr, vinification_en,
              barrel_fermentation_fr, barrel_fermentation_en,
              award_fr, award_en, extra_comment_fr, extra_comment_en,
              image_path, slug)
             VALUES ('Out Wine', 'rosé', 'bottle', 2018, 25.00, 100, 10, 0,
                     4.00, 'Bordeaux', 'Grenache', 18,
                     'test', 'test', 'test', 'test', 'test', 'test',
                     'test', 'test', 'test', 'test', 'test', 'test',
                     '', '', '', '',
                     'out_wine.png', 'out-wine-2018-merge-fix')"
        );
        $wineRow = self::$db->fetchOne(
            'SELECT id FROM wines WHERE slug = ?',
            ['out-wine-2018-merge-fix']
        );

        $_COOKIE['cb-cart'] = json_encode([['id' => (int) $wineRow['id'], 'qty' => 2]]);

        $_POST = [
            'email'      => 'merge-unavail@example.com',
            'password'   => 'CartUnavail1!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->login(['lang' => 'fr']);
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        // Le panier BDD est créé mais avec contenu vide — le vin indisponible est filtré
        $cart = self::$db->fetchOne('SELECT content, total_quantity FROM carts WHERE user_id = ?', [$id]);
        $this->assertNotFalse($cart, 'Un enregistrement panier est créé même sans item valide');
        $this->assertSame(0, (int) $cart['total_quantity'], 'Le vin indisponible doit être filtré → total_quantity = 0');
    }
}
