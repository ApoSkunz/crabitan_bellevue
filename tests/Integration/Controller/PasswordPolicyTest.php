<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\AuthController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration — politique mot de passe ANSSI MDP 2021.
 *
 * Couvre :
 * - Inscription avec mot de passe faible → erreur de validation
 * - Réinitialisation avec mot de passe faible → erreur de validation
 * - Inscription avec mot de passe fort → succès (compte créé)
 * - Réinitialisation avec mot de passe fort → succès
 */
class PasswordPolicyTest extends IntegrationTestCase
{
    private const CSRF = 'policy-csrf-token';

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
        $_POST    = [];
        $_COOKIE  = [];
        $_SESSION = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function makeController(string $method = 'POST', string $uri = '/fr/test'): AuthController
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET = [];
        return new AuthController(new Request());
    }

    private function insertVerifiedAccount(string $email, string $password = 'Password123!'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', NOW())",
            [$email, password_hash($password, PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Policy', 'Test', 'M')",
            [$id]
        );
        return $id;
    }

    // ----------------------------------------------------------------
    // Inscription — mot de passe faible
    // ----------------------------------------------------------------

    public function testRegisterWithWeakPasswordNoUppercaseProducesError(): void
    {
        // Pas de majuscule
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Test',
            'firstname'        => 'User',
            'email'            => 'no-upper@example.com',
            'password'         => 'password123!abc',
            'password_confirm' => 'password123!abc',
            'newsletter'       => '0',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayHasKey('password', $errors);

        // Aucun compte créé
        $account = self::$db->fetchOne(
            "SELECT id FROM accounts WHERE email = ?",
            ['no-upper@example.com']
        );
        $this->assertFalse($account);
    }

    public function testRegisterWithWeakPasswordNoSpecialCharProducesError(): void
    {
        // Pas de caractère spécial
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Test',
            'firstname'        => 'User',
            'email'            => 'no-special@example.com',
            'password'         => 'Password123456',
            'password_confirm' => 'Password123456',
            'newsletter'       => '0',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayHasKey('password', $errors);

        $account = self::$db->fetchOne(
            "SELECT id FROM accounts WHERE email = ?",
            ['no-special@example.com']
        );
        $this->assertFalse($account);
    }

    public function testRegisterWithWeakPasswordTooShortProducesError(): void
    {
        // Moins de 12 caractères
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Test',
            'firstname'        => 'User',
            'email'            => 'short-pwd@example.com',
            'password'         => 'Short1!',
            'password_confirm' => 'Short1!',
            'newsletter'       => '0',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayHasKey('password', $errors);

        $account = self::$db->fetchOne(
            "SELECT id FROM accounts WHERE email = ?",
            ['short-pwd@example.com']
        );
        $this->assertFalse($account);
    }

    public function testRegisterWithWeakPasswordNoDigitProducesError(): void
    {
        // Pas de chiffre
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Test',
            'firstname'        => 'User',
            'email'            => 'no-digit@example.com',
            'password'         => 'PasswordNoDigit!',
            'password_confirm' => 'PasswordNoDigit!',
            'newsletter'       => '0',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayHasKey('password', $errors);

        $account = self::$db->fetchOne(
            "SELECT id FROM accounts WHERE email = ?",
            ['no-digit@example.com']
        );
        $this->assertFalse($account);
    }

    public function testRegisterWithStrongPasswordSucceeds(): void
    {
        // Mot de passe ANSSI-conforme
        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'F',
            'lastname'         => 'Policy',
            'firstname'        => 'Valid',
            'email'            => 'strong-pwd@example.com',
            'password'         => 'Str0ng&Secure!',
            'password_confirm' => 'Str0ng&Secure!',
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
            "SELECT email FROM accounts WHERE email = ?",
            ['strong-pwd@example.com']
        );
        $this->assertNotFalse($account);
    }

    // ----------------------------------------------------------------
    // Réinitialisation — mot de passe faible
    // ----------------------------------------------------------------

    public function testResetWithWeakPasswordNoUppercaseProducesError(): void
    {
        $userId = $this->insertVerifiedAccount('reset-weak-upper@example.com');
        $token  = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );

        // Pas de majuscule
        $_POST = [
            'password'         => 'password123!abc',
            'password_confirm' => 'password123!abc',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr?modal=reset', $e->location);
        }

        $resetData = $_SESSION['reset_modal'] ?? [];
        $this->assertNotEmpty($resetData['error']);
    }

    public function testResetWithWeakPasswordNoSpecialCharProducesError(): void
    {
        $userId = $this->insertVerifiedAccount('reset-weak-special@example.com');
        $token  = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );

        // Pas de caractère spécial
        $_POST = [
            'password'         => 'Password123456',
            'password_confirm' => 'Password123456',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr?modal=reset', $e->location);
        }

        $resetData = $_SESSION['reset_modal'] ?? [];
        $this->assertNotEmpty($resetData['error']);
    }

    public function testResetWithStrongPasswordSucceeds(): void
    {
        $userId = $this->insertVerifiedAccount('reset-strong@example.com', 'OldPass1!valid');
        $token  = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );

        $_POST = [
            'password'         => 'NewStr0ng&Pass!',
            'password_confirm' => 'NewStr0ng&Pass!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr?login=1', $e->location);
        }

        // Token supprimé après succès
        $reset = self::$db->fetchOne(
            "SELECT * FROM password_reset WHERE user_id = ?",
            [$userId]
        );
        $this->assertFalse($reset);
    }

    // ----------------------------------------------------------------
    // R5 — Anti-énumération : email déjà utilisé à l'inscription
    // ----------------------------------------------------------------

    /**
     * Si l'email est déjà enregistré, register() affiche le même message de confirmation
     * générique qu'une inscription réussie et ne retourne PAS d'erreur inline sur le champ email.
     * Aucun nouveau compte ne doit être créé.
     */
    public function testRegisterWithExistingEmailShowsGenericSuccessAndCreatesNoAccount(): void
    {
        // Compte existant et vérifié
        $this->insertVerifiedAccount('duplicate@example.com');

        $_POST = [
            'account_type'     => 'individual',
            'civility'         => 'M',
            'lastname'         => 'Dupont',
            'firstname'        => 'Jean',
            'email'            => 'duplicate@example.com',
            'password'         => 'Str0ng&Secure!',
            'password_confirm' => 'Str0ng&Secure!',
            'newsletter'       => '0',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->register(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            // Même redirection que le succès (message générique, pas d'erreur inline)
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }

        // Aucune erreur email exposée dans la session
        $errors = $_SESSION['flash']['register_errors'] ?? [];
        $this->assertArrayNotHasKey('email', $errors);

        // Le flash doit contenir le message de succès générique
        $this->assertSame(__('auth.register_success'), $_SESSION['flash']['info'] ?? null);

        // Un seul compte avec cet email en base (le compte original)
        $count = self::$db->fetchOne(
            "SELECT COUNT(*) as cnt FROM accounts WHERE email = ?",
            ['duplicate@example.com']
        );
        $this->assertSame(1, (int) ($count['cnt'] ?? 0));
    }
}
