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
        $_SESSION['submit_token']  = 'test-submit-token';
        $_POST = ['csrf_token' => 'valid-csrf', 'submit_token' => 'test-submit-token'];

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
        $_SESSION['submit_token']  = 'test-submit-token';
        $_POST = [
            'csrf_token'     => 'valid-csrf',
            'submit_token'   => 'test-submit-token',
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
        $_SESSION['submit_token']  = 'test-submit-token';
        $_POST = [
            'csrf_token'     => 'valid-csrf',
            'submit_token'   => 'test-submit-token',
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

    // ================================================================
    // isMainlandFranceZip — via ReflectionMethod
    // ================================================================

    public function testIsMainlandFranceZipAcceptsValidZip(): void
    {
        $method = new \ReflectionMethod(OrderController::class, 'isMainlandFranceZip');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande';
        $ctrl = new OrderController(new Request());

        $this->assertTrue($method->invoke($ctrl, '75001'));
        $this->assertTrue($method->invoke($ctrl, '13001'));
        $this->assertTrue($method->invoke($ctrl, '67000'));
    }

    public function testIsMainlandFranceZipRejectsCorse(): void
    {
        $method = new \ReflectionMethod(OrderController::class, 'isMainlandFranceZip');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande';
        $ctrl = new OrderController(new Request());

        $this->assertFalse($method->invoke($ctrl, '20200'));
    }

    public function testIsMainlandFranceZipRejectsDomTom(): void
    {
        $method = new \ReflectionMethod(OrderController::class, 'isMainlandFranceZip');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande';
        $ctrl = new OrderController(new Request());

        $this->assertFalse($method->invoke($ctrl, '97100'));
        $this->assertFalse($method->invoke($ctrl, '97200'));
        $this->assertFalse($method->invoke($ctrl, '98000'));
    }

    public function testIsMainlandFranceZipAcceptsNonNumeric(): void
    {
        $method = new \ReflectionMethod(OrderController::class, 'isMainlandFranceZip');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande';
        $ctrl = new OrderController(new Request());

        $this->assertTrue($method->invoke($ctrl, 'abc'));
    }

    // ================================================================
    // enrichItems — via ReflectionMethod
    // ================================================================

    public function testEnrichItemsWithKnownWine(): void
    {
        $stub = $this->setDbMock();
        // AuthMiddleware n'est pas appelé ici (méthode privée directe)
        $stub->method('fetchOne')->willReturn([
            'id'               => 1,
            'price'            => 15.0,
            'label_name'       => 'TestWine',
            'is_cuvee_speciale' => false,
        ]);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande';
        $ctrl = new OrderController(new Request());

        $method = new \ReflectionMethod(OrderController::class, 'enrichItems');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $result = $method->invoke($ctrl, [['wine_id' => 1, 'qty' => 2]]);

        $this->assertSame(15.0, $result[0]['price']);
        $this->assertSame('TestWine', $result[0]['name']);
    }

    public function testEnrichItemsWithUnknownWine(): void
    {
        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(false);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande';
        $ctrl = new OrderController(new Request());

        $method = new \ReflectionMethod(OrderController::class, 'enrichItems');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $result = $method->invoke($ctrl, [['wine_id' => 99, 'qty' => 1]]);

        $this->assertSame(0.0, $result[0]['price']);
    }

    // ================================================================
    // payment() — POST : quantité non multiple de 12 → redirect panier
    // ================================================================

    public function testPaymentRedirectsOnNonMultipleOf12(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['submit_token']  = 'test-submit-token';
        $_POST = [
            'csrf_token'     => 'valid-csrf',
            'submit_token'   => 'test-submit-token',
            'payment_method' => 'card',
            'cgv'            => '1',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // AuthMiddleware
            ['user_id' => 1, 'content' => json_encode([['wine_id' => 1, 'qty' => 13]])], // cart
            ['id' => 1, 'price' => 10.0, 'label_name' => 'Wine', 'is_cuvee_speciale' => false]  // wine
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
    // payment() — POST : code postal DOM-TOM → redirect commande
    // ================================================================

    public function testPaymentRedirectsOnDomTomZip(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['submit_token']  = 'test-submit-token';
        $_POST = [
            'csrf_token'           => 'valid-csrf',
            'submit_token'         => 'test-submit-token',
            'payment_method'       => 'card',
            'cgv'                  => '1',
            'same_address'         => '1',
            'delivery_address_id'  => '5',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // AuthMiddleware
            ['user_id' => 1, 'content' => json_encode([['wine_id' => 1, 'qty' => 12]])], // cart
            ['id' => 1, 'price' => 10.0, 'label_name' => 'Wine', 'is_cuvee_speciale' => false], // wine
            ['id' => 5, 'firstname' => 'Test', 'lastname' => 'User'],  // resolveAddress findByIdForUser
            ['id' => 5, 'zip_code' => '97100', 'firstname' => 'Test', 'lastname' => 'User'] // mainland check
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('delivery', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // payment() — POST : adresse de livraison manquante (champs requis vides)
    // ================================================================

    public function testPaymentRedirectsOnMissingAddressFields(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['submit_token']  = 'test-submit-token';
        $_POST = [
            'csrf_token'           => 'valid-csrf',
            'submit_token'         => 'test-submit-token',
            'payment_method'       => 'card',
            'cgv'                  => '1',
            'same_address'         => '1',
            'delivery_address_id'  => '0',
            'del_firstname'        => '',  // champ requis vide
            'del_lastname'         => 'User',
            'del_street'           => '1 Rue Test',
            'del_city'             => 'Paris',
            'del_zip_code'         => '75001',
            'del_phone'            => '0612345678',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // AuthMiddleware
            ['user_id' => 1, 'content' => json_encode([['wine_id' => 1, 'qty' => 12]])], // cart
            ['id' => 1, 'price' => 10.0, 'label_name' => 'Wine', 'is_cuvee_speciale' => false]  // wine
        );

        $caught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('delivery', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // payment() — POST : submit_token invalide → redirect
    // ================================================================

    public function testPaymentRedirectsOnInvalidSubmitToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/commande/paiement';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['submit_token']  = 'valid-token';
        $_POST = [
            'csrf_token'   => 'valid-csrf',
            'submit_token' => 'wrong-token',
        ];

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturn(['id' => 1]); // AuthMiddleware

        $caught = null;
        try {
            (new OrderController(new Request()))->payment(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertArrayHasKey('submit', $_SESSION['flash']['checkout_errors'] ?? []);
    }

    // ================================================================
    // confirmation() — GET : clears session et rend la vue
    // ================================================================

    public function testConfirmationClearsSessionAndRendersView(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/commande/confirmation';
        $_COOKIE['auth_token']     = $this->makeValidJwt(1, 'customer');
        $_SESSION['last_order_ref']        = 'WEB-CB-TEST-2026';
        $_SESSION['last_order_payment']    = 'card';
        $_SESSION['last_order_newsletter'] = false;

        $stub = $this->setDbMock();
        $stub->method('fetchOne')->willReturnOnConsecutiveCalls(
            ['id' => 1],  // AuthMiddleware
            [
                'order_reference'   => 'WEB-CB-TEST-2026',
                'payment_method'    => 'card',
                'price'             => 120.0,
                'shipping_discount' => 0.0,
                'content'           => json_encode([['wine_id' => 1, 'qty' => 12, 'price' => 10.0, 'name' => 'Wine']]),
                'bill_firstname'    => 'Jean',
                'bill_lastname'     => 'Dupont',
                'bill_street'       => '1 Rue Test',
                'bill_zip'          => '75001',
                'bill_city'         => 'Paris',
                'bill_country'      => 'France',
                'del_firstname'     => 'Jean',
                'del_lastname'      => 'Dupont',
                'del_street'        => '1 Rue Test',
                'del_zip'           => '75001',
                'del_city'          => 'Paris',
                'del_country'       => 'France',
            ]
        );

        try {
            (new OrderController(new Request()))->confirmation(['lang' => 'fr']);
        } catch (HttpException $e) {
            // Attendu : rendu de vue lance une exception car pas de template disponible en test
        }

        $this->assertArrayNotHasKey('last_order_ref', $_SESSION);
    }
}
