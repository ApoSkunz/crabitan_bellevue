<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\AuthController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour les fonctionnalités forgot/reset de AuthController.
 *
 * Couvre :
 *   - Anti-énumération forgot (email inexistant → même réponse)
 *   - Rate limiting forgot (max 3 tentatives par IP → auth.too_many_reset_requests)
 *   - Reset avec token expiré → modal invalide
 *   - Reset avec token invalide → modal invalide
 *   - Reset avec passwords mismatch → validation.password_match
 *   - Reset avec mot de passe faible ANSSI → erreurs granulaires
 *   - Reset réussi → redirection /{lang}?login=1
 *   - Token à usage unique : second usage → modal invalide
 */
class AuthForgotResetTest extends IntegrationTestCase
{
    private const CSRF = 'integration-csrf-token';

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [
            'csrf' => self::CSRF,
            '_rl'  => [],
        ];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
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

    /**
     * Crée une instance Request + AuthController.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return AuthController
     */
    private function makeController(string $method = 'POST', string $uri = '/fr/test'): AuthController
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET = [];
        return new AuthController(new Request());
    }

    /**
     * Insère un compte vérifié avec rôle customer et retourne son ID.
     *
     * @param string $email    Adresse email du compte
     * @param string $password Mot de passe en clair
     * @return int ID du compte créé
     */
    private function insertVerifiedAccount(
        string $email = 'user@example.com',
        string $password = 'Password123!'
    ): int {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, account_type, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', 'individual', NOW())",
            [$email, password_hash($password, PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Test', 'User', 'M')",
            [$id]
        );
        return $id;
    }

    /**
     * Insère un token de reset valide (expire dans 1 heure) pour un compte.
     *
     * @param int    $userId ID du compte
     * @param string $token  Token hexadécimal
     * @return void
     */
    private function insertValidResetToken(int $userId, string $token): void
    {
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, NOW() + INTERVAL 1 HOUR)",
            [$userId, $token]
        );
    }

    /**
     * Insère un token de reset expiré (expires_at dans le passé) pour un compte.
     *
     * @param int    $userId ID du compte
     * @param string $token  Token hexadécimal
     * @return void
     */
    private function insertExpiredResetToken(int $userId, string $token): void
    {
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at)
             VALUES (?, ?, NOW() - INTERVAL 2 HOUR)",
            [$userId, $token]
        );
    }

    /**
     * Simule N-1 tentatives dans le bucket forgot pour déclencher le rate limiting
     * à la prochaine tentative (bucket en session : $_SESSION['_rl']).
     *
     * Le rate limiter utilise les clés :
     *   rl:forgot:{ip}:count  → nombre de tentatives
     *   rl:forgot:{ip}:until  → timestamp de fin de lockout
     *
     * @param string $ip         Adresse IP à saturer
     * @param int    $maxAttempts Nombre max avant blocage (MAX_FORGOT_IP = 3)
     * @return void
     */
    private function saturateForgotRateLimit(string $ip = '127.0.0.1', int $maxAttempts = 3): void
    {
        $_SESSION['_rl']['rl:forgot:' . $ip . ':count'] = $maxAttempts;
        // Pas de :until → checkLimit verra count >= max et posera le lockout
    }

    // ----------------------------------------------------------------
    // forgot() — anti-énumération
    // ----------------------------------------------------------------

    /**
     * Un email inexistant doit produire exactement la même réponse
     * (flash 'info' + redirect /fr) qu'un email existant (R5 anti-énumération).
     *
     * @return void
     */
    public function testForgotUnknownEmailReturnsSameResponseAsKnown(): void
    {
        $_POST = [
            'email'      => 'nobody-unknown@example.com',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->forgot(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }

        // Le message flash doit être le même que pour un email existant
        $this->assertSame(
            __('auth.reset_email_sent'),
            $_SESSION['flash']['info'] ?? null
        );
    }

    /**
     * Un email existant et vérifié doit également déclencher le flash
     * 'auth.reset_email_sent' (réponse identique à l'email inconnu).
     *
     * @return void
     */
    public function testForgotKnownEmailReturnsSameResponse(): void
    {
        $this->insertVerifiedAccount('known-forgot@example.com');

        $_POST = [
            'email'      => 'known-forgot@example.com',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->forgot(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }

        $this->assertSame(
            __('auth.reset_email_sent'),
            $_SESSION['flash']['info'] ?? null
        );
    }

    // ----------------------------------------------------------------
    // forgot() — rate limiting
    // ----------------------------------------------------------------

    /**
     * Après avoir saturé le bucket forgot (max 3 tentatives par IP),
     * la prochaine tentative doit être bloquée avec le flash
     * 'auth.too_many_reset_requests'.
     *
     * @return void
     */
    public function testForgotRateLimitingBlocksAfterMaxAttempts(): void
    {
        $this->saturateForgotRateLimit('127.0.0.1', 3);

        $_POST = [
            'email'      => 'ratelimit@example.com',
            'csrf_token' => self::CSRF,
        ];

        try {
            $this->makeController()->forgot(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertSame('/fr', $e->location);
        }

        // Le flash modal_error doit être posé (clé brute ou traduit selon l'état i18n)
        $flash = $_SESSION['flash']['modal_error'] ?? '';
        $this->assertNotEmpty($flash, 'Un flash modal_error doit être présent après dépassement du rate limit');
        // Aucun flash 'info' ne doit avoir été posé
        $this->assertArrayNotHasKey('info', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // reset() — token invalide
    // ----------------------------------------------------------------

    /**
     * Un token invalide (absent de la BDD) doit poser reset_modal['valid'] = false
     * et rediriger vers /{lang}?modal=reset.
     *
     * @return void
     */
    public function testResetWithInvalidTokenSetsModalInvalid(): void
    {
        $fakeToken = bin2hex(random_bytes(32));

        $_POST = [
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $fakeToken]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertStringContainsString('modal=reset', $e->location);
        }

        $modal = $_SESSION['reset_modal'] ?? null;
        $this->assertNotNull($modal);
        $this->assertFalse($modal['valid'], 'reset_modal[valid] doit être false pour un token invalide');
    }

    // ----------------------------------------------------------------
    // reset() — token expiré
    // ----------------------------------------------------------------

    /**
     * Un token expiré (expires_at dans le passé) doit être traité comme
     * invalide : reset_modal['valid'] = false, redirection vers /{lang}?modal=reset.
     *
     * @return void
     */
    public function testResetWithExpiredTokenSetsModalInvalid(): void
    {
        $userId = $this->insertVerifiedAccount('expired-reset@example.com');
        $token  = bin2hex(random_bytes(32));
        $this->insertExpiredResetToken($userId, $token);

        $_POST = [
            'password'         => 'Password123!',
            'password_confirm' => 'Password123!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertStringContainsString('modal=reset', $e->location);
        }

        $modal = $_SESSION['reset_modal'] ?? null;
        $this->assertNotNull($modal);
        $this->assertFalse($modal['valid'], 'Un token expiré doit rendre reset_modal[valid] = false');
    }

    // ----------------------------------------------------------------
    // reset() — passwords mismatch
    // ----------------------------------------------------------------

    /**
     * Quand les deux mots de passe ne correspondent pas, reset_modal['error']
     * doit contenir la traduction de 'validation.password_match'.
     *
     * @return void
     */
    public function testResetWithPasswordMismatchSetsModalError(): void
    {
        $userId = $this->insertVerifiedAccount('mismatch-reset@example.com');
        $token  = bin2hex(random_bytes(32));
        $this->insertValidResetToken($userId, $token);

        $_POST = [
            'password'         => 'Password123!',
            'password_confirm' => 'Different456!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertStringContainsString('modal=reset', $e->location);
        }

        $modal = $_SESSION['reset_modal'] ?? null;
        $this->assertNotNull($modal);
        $this->assertTrue($modal['valid'], 'reset_modal[valid] doit rester true');
        $this->assertStringContainsString(
            __('validation.password_match'),
            $modal['error'] ?? '',
            'Le message doit correspondre à validation.password_match'
        );
    }

    // ----------------------------------------------------------------
    // reset() — mot de passe faible (ANSSI)
    // ----------------------------------------------------------------

    /**
     * Un mot de passe trop court (< 12 caractères) doit déclencher l'erreur
     * granulaire ANSSI 'validation.password_min' dans reset_modal['error'].
     *
     * @return void
     */
    public function testResetWithWeakPasswordTooShortSetsAnssiError(): void
    {
        $userId = $this->insertVerifiedAccount('weakpw-short@example.com');
        $token  = bin2hex(random_bytes(32));
        $this->insertValidResetToken($userId, $token);

        $_POST = [
            'password'         => 'Short1!',   // < 12 caractères
            'password_confirm' => 'Short1!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertStringContainsString('modal=reset', $e->location);
        }

        $modal = $_SESSION['reset_modal'] ?? null;
        $this->assertNotNull($modal);
        $this->assertTrue($modal['valid']);
        $this->assertStringContainsString(
            __('validation.password_min'),
            $modal['error'] ?? '',
            'Le message doit signaler la longueur minimale ANSSI'
        );
    }

    /**
     * Un mot de passe sans majuscule doit déclencher l'erreur
     * granulaire ANSSI 'validation.password_uppercase'.
     *
     * @return void
     */
    public function testResetWithWeakPasswordNoUppercaseSetsAnssiError(): void
    {
        $userId = $this->insertVerifiedAccount('weakpw-upper@example.com');
        $token  = bin2hex(random_bytes(32));
        $this->insertValidResetToken($userId, $token);

        $_POST = [
            'password'         => 'nouppercase123!', // pas de majuscule
            'password_confirm' => 'nouppercase123!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
        }

        $modal = $_SESSION['reset_modal'] ?? null;
        $this->assertNotNull($modal);
        $this->assertTrue($modal['valid']);
        $this->assertStringContainsString(
            __('validation.password_uppercase'),
            $modal['error'] ?? '',
            'Le message doit signaler l\'absence de majuscule ANSSI'
        );
    }

    // ----------------------------------------------------------------
    // reset() — succès
    // ----------------------------------------------------------------

    /**
     * Un reset valide (token non expiré, mots de passe conformes ANSSI et identiques)
     * doit mettre à jour le mot de passe en BDD, supprimer le token, et
     * rediriger vers /{lang}?login=1 avec le flash 'auth.password_updated'.
     *
     * @return void
     */
    public function testResetSuccessUpdatesPasswordAndRedirects(): void
    {
        $userId = $this->insertVerifiedAccount('success-reset@example.com', 'OldPassword1!');
        $token  = bin2hex(random_bytes(32));
        $this->insertValidResetToken($userId, $token);

        $_POST = [
            'password'         => 'NewPassword99!',
            'password_confirm' => 'NewPassword99!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertStringContainsString('login=1', $e->location);
        }

        // Le flash de succès doit être posé
        $this->assertSame(
            __('auth.password_updated'),
            $_SESSION['flash']['info'] ?? null
        );

        // Le token doit avoir été supprimé (usage unique)
        $remaining = self::$db->fetchOne(
            "SELECT id FROM password_reset WHERE user_id = ?",
            [$userId]
        );
        $this->assertFalse($remaining, 'Le token de reset doit être supprimé après usage');

        // Le nouveau mot de passe doit être valide en BDD
        $account = self::$db->fetchOne(
            "SELECT password FROM accounts WHERE id = ?",
            [$userId]
        );
        $this->assertNotFalse($account);
        $this->assertTrue(
            password_verify('NewPassword99!', $account['password']),
            'Le nouveau mot de passe doit être hashé et valide en BDD'
        );
    }

    // ----------------------------------------------------------------
    // reset() — token à usage unique
    // ----------------------------------------------------------------

    /**
     * Après un reset réussi, une seconde tentative avec le même token doit
     * être rejetée (reset_modal['valid'] = false), car le token a été supprimé.
     *
     * @return void
     */
    public function testResetTokenIsOneTimeUse(): void
    {
        $userId = $this->insertVerifiedAccount('one-time-reset@example.com', 'OldPassword1!');
        $token  = bin2hex(random_bytes(32));
        $this->insertValidResetToken($userId, $token);

        // Premier usage : succès
        $_POST = [
            'password'         => 'NewPassword99!',
            'password_confirm' => 'NewPassword99!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
        } catch (HttpException) {
            // attendu
        }

        // Remettre le CSRF et vider le flash pour le second usage
        $_SESSION['csrf']  = self::CSRF;
        $_SESSION['flash'] = [];

        // Second usage avec le même token
        $_POST = [
            'password'         => 'AnotherPass99!',
            'password_confirm' => 'AnotherPass99!',
            'csrf_token'       => self::CSRF,
        ];

        try {
            $this->makeController()->reset(['lang' => 'fr', 'token' => $token]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertStringContainsString('modal=reset', $e->location);
        }

        $modal = $_SESSION['reset_modal'] ?? null;
        $this->assertNotNull($modal);
        $this->assertFalse(
            $modal['valid'],
            'Le second usage du même token doit être rejeté (token déjà consommé)'
        );
    }
}
