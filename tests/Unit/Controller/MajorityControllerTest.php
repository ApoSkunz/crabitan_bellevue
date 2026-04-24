<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\MajorityController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour MajorityController::declare().
 *
 * Vérifie le guard CSRF, la validation JWT, et les deux branches de traitement
 * (checkbox non cochée → majority_pending, cochée → declareMajority + redirect).
 */
class MajorityControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_SESSION = ['csrf' => 'valid-token'];
        $_POST    = [];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
    }

    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return $stub;
    }

    private function validToken(int $id = 1): string
    {
        $_ENV['JWT_SECRET'] ??= 'test-secret-key-for-unit-tests';
        return Jwt::generate($id, 'customer', 3600);
    }

    // ----------------------------------------------------------------
    // CSRF invalide
    // ----------------------------------------------------------------

    /**
     * CSRF manquant → redirect /fr sans action.
     */
    public function testDeclareRedirectsOnInvalidCsrf(): void
    {
        $_POST   = ['csrf_token' => 'wrong'];
        $_COOKIE = [];

        $caught = null;
        try {
            (new MajorityController(new Request()))->declare(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('/fr', $caught->location);
    }

    // ----------------------------------------------------------------
    // JWT absent ou invalide
    // ----------------------------------------------------------------

    /**
     * Pas de cookie auth_token → redirect /fr.
     */
    public function testDeclareRedirectsWhenNoJwt(): void
    {
        $_POST   = ['csrf_token' => 'valid-token'];
        $_COOKIE = [];

        $caught = null;
        try {
            (new MajorityController(new Request()))->declare(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('/fr', $caught->location);
    }

    /**
     * JWT invalide → redirect /fr.
     */
    public function testDeclareRedirectsOnInvalidJwt(): void
    {
        $_POST              = ['csrf_token' => 'valid-token'];
        $_COOKIE['auth_token'] = 'not.a.valid.jwt';

        $caught = null;
        try {
            (new MajorityController(new Request()))->declare(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('/fr', $caught->location);
    }

    // ----------------------------------------------------------------
    // JWT valide mais sub = 0 → accountId <= 0 → redirect /fr (line 57)
    // ----------------------------------------------------------------

    /**
     * JWT valide mais sub = 0 → accountId <= 0 → redirect vers /fr sans aucune action.
     *
     * Couvre la branche ligne 57 : `if ($accountId <= 0)`.
     */
    public function testDeclareRedirectsWhenAccountIdIsZero(): void
    {
        $_ENV['JWT_SECRET'] ??= 'test-secret-key-for-unit-tests';

        // Générer un JWT valide avec sub = 0 (accountId invalide)
        $jwt = \Core\Jwt::generate(0, 'customer', 3600);
        $_COOKIE['auth_token'] = $jwt;
        $_POST = ['csrf_token' => 'valid-token'];

        $caught = null;
        try {
            (new MajorityController(new Request()))->declare(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('/fr', $caught->location);
    }

    // ----------------------------------------------------------------
    // Checkbox non cochée → majority_pending rétabli
    // ----------------------------------------------------------------

    /**
     * majority_confirmed absent → majority_pending = true, redirect vers la page courante.
     */
    public function testDeclareRestoresMajorityPendingWhenCheckboxUnchecked(): void
    {
        $token               = $this->validToken(1);
        $_COOKIE['auth_token'] = $token;
        $_POST = [
            'csrf_token' => 'valid-token',
            'redirect'   => '/fr/boutique',
            // majority_confirmed absent
        ];

        $caught = null;
        try {
            (new MajorityController(new Request()))->declare(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('/fr/boutique', $caught->location);
        $this->assertTrue($_SESSION['majority_pending'] ?? false);
    }

    /**
     * majority_confirmed = '0' → même comportement que absent.
     */
    public function testDeclareRestoresMajorityPendingWhenCheckboxValueZero(): void
    {
        $token               = $this->validToken(1);
        $_COOKIE['auth_token'] = $token;
        $_POST = [
            'csrf_token'        => 'valid-token',
            'majority_confirmed' => '0',
        ];

        $caught = null;
        try {
            (new MajorityController(new Request()))->declare(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertSame(302, $caught->status);
        $this->assertTrue($_SESSION['majority_pending'] ?? false);
    }

    // ----------------------------------------------------------------
    // Checkbox cochée → declareMajority + redirect
    // ----------------------------------------------------------------

    /**
     * majority_confirmed = '1' → declareMajority appelé, majority_pending effacé, redirect.
     */
    public function testDeclareCallsDeclareMajorityAndClearsFlag(): void
    {
        $token               = $this->validToken(2);
        $_COOKIE['auth_token'] = $token;
        $_SESSION['majority_pending'] = true;
        $_POST = [
            'csrf_token'         => 'valid-token',
            'majority_confirmed' => '1',
            'redirect'           => '/fr/commande',
        ];

        $stub = $this->setDbMock();
        $stub->method('execute')->willReturn(1);  // AccountModel::declareMajority

        $caught = null;
        try {
            (new MajorityController(new Request()))->declare(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertSame('/fr/commande', $caught->location);
        $this->assertArrayNotHasKey('majority_pending', $_SESSION);
    }

    // ----------------------------------------------------------------
    // sanitizeRedirect — open redirect prevention (VULN-002)
    // ----------------------------------------------------------------

    /**
     * URL externe → repli sur /{lang}.
     */
    public function testSanitizeRedirectBlocksExternalUrl(): void
    {
        $method = new \ReflectionMethod(MajorityController::class, 'sanitizeRedirect');
        $ctrl   = new MajorityController(new Request());
        $this->assertSame('/fr', $method->invoke($ctrl, 'https://evil.com', 'fr'));
    }

    /**
     * Protocol-relative URL → repli sur /{lang}.
     */
    public function testSanitizeRedirectBlocksProtocolRelative(): void
    {
        $method = new \ReflectionMethod(MajorityController::class, 'sanitizeRedirect');
        $ctrl   = new MajorityController(new Request());
        $this->assertSame('/en', $method->invoke($ctrl, '//evil.com', 'en'));
    }

    /**
     * URL-encoded bypass /%2F%2Fevil.com → repli sur /{lang}.
     */
    public function testSanitizeRedirectBlocksEncodedBypass(): void
    {
        $method = new \ReflectionMethod(MajorityController::class, 'sanitizeRedirect');
        $ctrl   = new MajorityController(new Request());
        $this->assertSame('/fr', $method->invoke($ctrl, '/%2F%2Fevil.com', 'fr'));
    }

    /**
     * Chemin interne valide → retourné tel quel.
     */
    public function testSanitizeRedirectAllowsInternalPath(): void
    {
        $method = new \ReflectionMethod(MajorityController::class, 'sanitizeRedirect');
        $ctrl   = new MajorityController(new Request());
        $this->assertSame('/fr/vins', $method->invoke($ctrl, '/fr/vins', 'fr'));
    }

    /**
     * Chemin interne EN valide.
     */
    public function testSanitizeRedirectAllowsEnPath(): void
    {
        $method = new \ReflectionMethod(MajorityController::class, 'sanitizeRedirect');
        $ctrl   = new MajorityController(new Request());
        $this->assertSame('/en/wines', $method->invoke($ctrl, '/en/wines', 'en'));
    }

    /**
     * Redirect par défaut vers /fr/mon-compte si POST[redirect] absent.
     */
    public function testDeclareFallsBackToAccountWhenNoRedirect(): void
    {
        $token               = $this->validToken(3);
        $_COOKIE['auth_token'] = $token;
        $_POST = [
            'csrf_token'         => 'valid-token',
            'majority_confirmed' => '1',
        ];

        $stub = $this->setDbMock();
        $stub->method('execute')->willReturn(1);

        $caught = null;
        try {
            (new MajorityController(new Request()))->declare(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertSame(302, $caught->status);
        $this->assertSame('/fr/mon-compte', $caught->location);
    }
}
