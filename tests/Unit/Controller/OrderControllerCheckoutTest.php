<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\OrderController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests du flux checkout dans OrderController.
 *
 * AuthMiddleware::handle() effectue un fetchOne sur la table connections.
 * Chaque test doit donc prévoir ce premier appel avant ses propres fetchOne.
 *
 * Couvre :
 *   - checkout() → redirection si panier vide
 *   - payment()  → redirection CSRF invalide
 *   - payment()  → redirection si méthode de paiement invalide
 *   - payment()  → redirection si CGV non acceptées
 *   - payment()  → redirection si panier vide
 *   - confirmation() → redirection si pas de référence en session
 *   - confirmation() → redirection si commande introuvable
 */
class OrderControllerCheckoutTest extends TestCase
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
        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_COOKIE  = [];
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    private function setDbMock(): \PHPUnit\Framework\MockObject\Stub
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return $stub;
    }

    private function makeValidJwt(int $userId, string $role): string
    {
        return \Core\Jwt::generate($userId, $role, 3600);
    }

    // ================================================================
    // checkout() — GET : panier vide → redirect panier
    // ================================================================

    public function testCheckoutRedirectsToCartWhenCartEmpty(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // AuthMiddleware::handle() → connections check
            false,          // CartModel::removeUnavailableItems → findByUserId → pas de panier
            false           // CartModel::findByUserId → pas de panier (explicit check)
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->checkout(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // payment() — POST : CSRF invalide → redirect
    // ================================================================

    public function testPaymentRedirectsOnInvalidCsrf(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_POST = ['csrf_token' => 'wrong-token'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware session check

        $caught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('csrf', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // payment() — POST : panier vide après CSRF → redirect
    // ================================================================

    public function testPaymentRedirectsToCartWhenCartEmpty(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_POST = ['csrf_token' => 'valid-csrf'];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // AuthMiddleware session check
            false           // CartModel::findByUserId → pas de panier
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // payment() — POST : méthode de paiement invalide → redirect
    // ================================================================

    public function testPaymentRedirectsOnInvalidPaymentMethod(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_POST = [
            'csrf_token'     => 'valid-csrf',
            'payment_method' => 'bitcoin',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // AuthMiddleware session check
            // CartModel::findByUserId — panier avec contenu
            ['user_id' => 1, 'content' => json_encode([['wine_id' => 1, 'qty' => 2]])]
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('payment_method', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // payment() — POST : CGV non acceptées → redirect
    // ================================================================

    public function testPaymentRedirectsWhenCgvNotAccepted(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_POST = [
            'csrf_token'     => 'valid-csrf',
            'payment_method' => 'card',
            // cgv absent → non acceptées
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],
            ['user_id' => 1, 'content' => json_encode([['wine_id' => 1, 'qty' => 2]])]
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('cgv', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // confirmation() — GET : pas de référence → redirect commandes
    // ================================================================

    public function testConfirmationRedirectsWhenNoOrderRefInSession(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/confirmation';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        unset($_SESSION['last_order_ref']);

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware session check

        $caught = null;
        try {
            (new OrderController(new Request()))->confirmation(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // confirmation() — GET : référence présente mais commande introuvable
    // ================================================================

    public function testConfirmationRedirectsWhenOrderNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/confirmation';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['last_order_ref'] = 'ORD-UNKNOWN-2025';

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],   // AuthMiddleware session check
            false           // OrderModel::findByReference → commande introuvable
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->confirmation(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }
}
