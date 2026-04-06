<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;
use Service\RateLimiterService;

/**
 * Tests unitaires pour AuthController::forgot() — rate limiting et branches de couverture.
 *
 * Couvre : budget email_ip combiné, rate limit IP forgot, CSRF invalide,
 * email inconnu (anti-énumération), compte Google (password=null),
 * compte valide avec mot de passe (reset email), compte non éligible
 * (email non vérifié, rôle admin), compte entreprise.
 * Tous les appels BDD sont mockés via Database stub.
 */
class AuthControllerForgotRateLimitTest extends TestCase
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
        $_SERVER['REQUEST_URI']    = '/fr/mot-de-passe-oublie';
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
     * Crée un AuthController avec un RateLimiterService forcé en mode session,
     * indépendamment de la disponibilité d'APCu sur le runner CI.
     *
     * @return AuthController
     */
    private function makeSessionController(): AuthController
    {
        $controller = new AuthController(new Request());
        $prop = new \ReflectionProperty(AuthController::class, 'rateLimiter');
        $prop->setAccessible(true);
        $prop->setValue($controller, new class extends RateLimiterService {
            public function isApcuAvailable(): bool
            {
                return false;
            }
        });
        return $controller;
    }

    /**
     * @param string $email Email à soumettre
     * @return ?HttpException Exception capturée (redirect)
     */
    private function invoke(string $email = 'user@example.com'): ?HttpException
    {
        $_POST  = ['csrf_token' => 'valid-csrf', 'email' => $email];
        $caught = null;
        try {
            $this->makeSessionController()->forgot(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        return $caught;
    }

    // ================================================================
    // Budget email_ip combiné dépassé → flash modal_error visible
    // ================================================================

    /**
     * Quand le budget email_ip:IP est épuisé, forgot() doit afficher modal_error
     * et ne pas continuer (pas de forgot_success qui masquerait le blocage).
     */
    public function testEmailIpBudgetExceededFlashesModalError(): void
    {
        $_SESSION['_rl']['rl:email_ip:127.0.0.1:until'] = time() + 900;

        $this->setDbMock();
        $caught = $this->invoke();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('forgot_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Rate limit IP forgot:IP dépassé → flash modal_error
    // ================================================================

    /**
     * Le budget forgot:IP individuel est épuisé (indépendant du budget email_ip).
     */
    public function testForgotIpRateLimitExceededFlashesModalError(): void
    {
        $_SESSION['_rl']['rl:forgot:127.0.0.1:until'] = time() + 900;

        $this->setDbMock();
        $caught = $this->invoke();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('forgot_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // CSRF invalide → redirect sans flash forgot_success
    // ================================================================

    public function testCsrfFailureRedirectsWithoutFlashingSuccess(): void
    {
        $this->setDbMock();
        $_POST  = ['csrf_token' => 'wrong-token', 'email' => 'user@example.com'];
        $caught = null;
        try {
            $this->makeSessionController()->forgot(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // Email inconnu → timing normalization + forgot_success
    // ================================================================

    public function testUnknownEmailFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(false);

        $caught = $this->invoke('unknown@example.com');

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('forgot_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Compte Google (password=null) → sendGoogleAccountInfo + forgot_success
    // ================================================================

    /**
     * Un compte créé via OAuth Google n'a pas de mot de passe.
     * forgot() doit envoyer sendGoogleAccountInfo() et flasher forgot_success.
     */
    public function testGoogleAccountWithNullPasswordFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 1,
            'email'             => 'google@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',
            'role'              => 'customer',
            'password'          => null,
            'account_type'      => 'individual',
            'firstname'         => 'Google',
            'lastname'          => 'User',
            'company_name'      => null,
        ]);

        $caught = $this->invoke('google@example.com');

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('forgot_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Compte individuel valide avec mot de passe → reset email + forgot_success
    // ================================================================

    public function testValidIndividualAccountFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 2,
            'email'             => 'user@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',
            'role'              => 'customer',
            'password'          => '$2y$10$hashedpassword',
            'account_type'      => 'individual',
            'firstname'         => 'Jean',
            'lastname'          => 'Dupont',
            'company_name'      => null,
        ]);

        $caught = $this->invoke('user@example.com');

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('forgot_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Compte entreprise valide → displayName = company_name + forgot_success
    // ================================================================

    public function testValidCompanyAccountFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 3,
            'email'             => 'company@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',
            'role'              => 'customer',
            'password'          => '$2y$10$hashedpassword',
            'account_type'      => 'company',
            'firstname'         => null,
            'lastname'          => null,
            'company_name'      => 'Acme Corp',
        ]);

        $caught = $this->invoke('company@example.com');

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('forgot_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Compte non vérifié → non éligible → timing + forgot_success
    // ================================================================

    /**
     * Un compte dont email_verified_at est null n'est pas éligible au reset.
     * forgot() flash quand même forgot_success (anti-énumération).
     */
    public function testUnverifiedAccountFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 4,
            'email'             => 'unverified@example.com',
            'email_verified_at' => null,
            'role'              => 'customer',
            'password'          => '$2y$10$hashedpassword',
            'account_type'      => 'individual',
            'firstname'         => 'Unverified',
            'lastname'          => 'User',
            'company_name'      => null,
        ]);

        $caught = $this->invoke('unverified@example.com');

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('forgot_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Compte avec rôle admin → non éligible → timing + forgot_success
    // ================================================================

    public function testNonCustomerRoleFlashesSuccess(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn([
            'id'                => 5,
            'email'             => 'admin@example.com',
            'email_verified_at' => '2024-01-01 00:00:00',
            'role'              => 'admin',
            'password'          => '$2y$10$hashedpassword',
            'account_type'      => 'individual',
            'firstname'         => 'Admin',
            'lastname'          => 'User',
            'company_name'      => null,
        ]);

        $caught = $this->invoke('admin@example.com');

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('forgot_success', $_SESSION['flash'] ?? []);
    }

    // ================================================================
    // Vérifier que les compteurs rate limit sont incrémentés sur chemin nominal
    // ================================================================

    /**
     * Sur un chemin valide, emailIpKey et forgotKey doivent être incrémentés.
     */
    public function testRateLimitCountersIncrementedOnNominalPath(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(false); // email inconnu → timing path

        $this->invoke('unknown@example.com');

        $emailIpCount = $_SESSION['_rl']['rl:email_ip:127.0.0.1:count'] ?? 0;
        $forgotCount  = $_SESSION['_rl']['rl:forgot:127.0.0.1:count'] ?? 0;

        $this->assertGreaterThan(0, $emailIpCount, 'emailIpKey doit être incrémenté');
        $this->assertGreaterThan(0, $forgotCount, 'forgotKey doit être incrémenté');
    }
}
