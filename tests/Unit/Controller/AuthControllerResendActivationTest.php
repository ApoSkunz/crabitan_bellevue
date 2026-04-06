<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour AuthController::resendActivation().
 *
 * Couvre : budget email_ip combiné, rate limit IP resend, email invalide/vide,
 * anti-énumération (email inconnu, déjà vérifié), happy path individuel et entreprise,
 * rate limit userId, CSRF invalide.
 * Tous les appels BDD sont mockés via Database stub. RateLimiterService utilise le
 * fallback session (APCu désactivé en PHPUnit).
 */
class AuthControllerResendActivationTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = ['csrf' => 'valid-csrf', '_rl' => [], '_rl_ttl' => []];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/renvoyer-activation';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
        unset($_SERVER['REMOTE_ADDR']);
    }

    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return $stub;
    }

    /**
     * @param array<string, string> $extra Surcharge de $_POST
     */
    private function invoke(array $extra = []): ?HttpException
    {
        $_POST  = array_merge(['csrf_token' => 'valid-csrf', 'email' => 'user@example.com'], $extra);
        $caught = null;
        try {
            (new AuthController(new Request()))->resendActivation(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        return $caught;
    }

    // ================================================================
    // Budget email_ip combiné dépassé → flash modal_error visible
    // ================================================================

    /**
     * Quand le budget email_ip:IP est épuisé, resendActivation doit afficher
     * une erreur visible (modal_error) et non masquer le blocage derrière un succès.
     */
    public function testEmailIpBudgetExceededFlashesModalError(): void
    {
        // Lockout actif sur le budget email_ip:127.0.0.1
        $_SESSION['_rl']['rl:email_ip:127.0.0.1:until'] = time() + 900;

        $this->setDbMock();
        $caught = $this->invoke();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Rate limit IP resend_activation_ip dépassé → flash modal_error
    // ================================================================

    /**
     * Le budget resend_activation_ip:IP dépasse son propre seuil (indépendant
     * du budget email_ip). Doit également produire modal_error.
     */
    public function testResendActivationIpRateLimitExceededFlashesModalError(): void
    {
        // Budget email_ip OK, mais resend_activation_ip lockout
        $_SESSION['_rl']['rl:resend_activation_ip:127.0.0.1:until'] = time() + 900;

        $this->setDbMock();
        $caught = $this->invoke();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Email invalide (format incorrect) → resend_activation_success
    // ================================================================

    public function testInvalidEmailFormatFlashesSuccess(): void
    {
        $this->setDbMock();
        $caught = $this->invoke(['email' => 'not-a-valid-email']);

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Email vide → resend_activation_success (anti-enum)
    // ================================================================

    public function testEmptyEmailFlashesSuccess(): void
    {
        $this->setDbMock();
        $caught = $this->invoke(['email' => '']);

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Email inconnu en BDD → resend_activation_success (anti-enum)
    // ================================================================

    public function testUnknownEmailFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(false);

        $caught = $this->invoke();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Compte déjà vérifié → resend_activation_success (anti-enum)
    // ================================================================

    public function testAlreadyVerifiedAccountFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 2,
            'email'             => 'user@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',
            'account_type'      => 'individual',
            'firstname'         => 'Jane',
            'lastname'          => 'Doe',
            'company_name'      => null,
        ]);

        $caught = $this->invoke();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Compte individuel non vérifié → email envoyé + resend_activation_success
    // ================================================================

    public function testValidIndividualAccountFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 3,
            'email'             => 'user@example.com',
            'email_verified_at' => null,
            'account_type'      => 'individual',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => null,
        ]);

        $caught = $this->invoke();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Compte entreprise non vérifié → displayName = company_name
    // ================================================================

    public function testValidCompanyAccountFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 4,
            'email'             => 'company@example.com',
            'email_verified_at' => null,
            'account_type'      => 'company',
            'firstname'         => null,
            'lastname'          => null,
            'company_name'      => 'Acme Corp',
        ]);

        $caught = $this->invoke(['email' => 'company@example.com']);

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Rate limit userId dépassé → pas d'envoi mail, toujours success
    // ================================================================

    /**
     * Quand le compteur resend_activation:{userId} est épuisé, aucun email n'est
     * envoyé mais la réponse reste identique (anti-énumération sur le compte).
     */
    public function testUserRateLimitExceededSkipsMailButFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 5,
            'email'             => 'user@example.com',
            'email_verified_at' => null,
            'account_type'      => 'individual',
            'firstname'         => 'Marc',
            'lastname'          => 'Martin',
            'company_name'      => null,
        ]);

        // Lockout du rate limit par userId pour resend_activation:5
        $_SESSION['_rl']['rl:resend_activation:5:until'] = time() + 900;

        $caught = $this->invoke();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // CSRF invalide → resend_activation_success (anti-enum, pas de révélation)
    // ================================================================

    public function testCsrfFailureFlashesSuccessForAntiEnumeration(): void
    {
        $this->setDbMock();
        $caught = $this->invoke(['csrf_token' => 'invalid-token']);

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('resend_activation_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // recordAttempt incrémente les compteurs sur chemin email valide
    // ================================================================

    /**
     * Après une requête valide (compte trouvé), emailIpKey et ipKey doivent
     * avoir été incrémentés dans la session.
     */
    public function testRateLimitCountersIncrementedOnValidRequest(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 6,
            'email'             => 'user@example.com',
            'email_verified_at' => null,
            'account_type'      => 'individual',
            'firstname'         => 'Alice',
            'lastname'          => 'Exemple',
            'company_name'      => null,
        ]);

        $this->invoke();

        $emailIpCount = $_SESSION['_rl']['rl:email_ip:127.0.0.1:count'] ?? 0;
        $ipCount      = $_SESSION['_rl']['rl:resend_activation_ip:127.0.0.1:count'] ?? 0;

        $this->assertGreaterThan(0, $emailIpCount, 'emailIpKey doit être incrémenté');
        $this->assertGreaterThan(0, $ipCount, 'ipKey doit être incrémenté');
    }
}
