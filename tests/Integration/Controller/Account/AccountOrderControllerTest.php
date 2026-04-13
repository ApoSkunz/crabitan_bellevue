<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Account;

use Controller\Account\AccountOrderController;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AccountOrderController.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class AccountOrderControllerTest extends IntegrationTestCase
{
    private const CSRF = 'test-order-csrf';

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = ['csrf' => self::CSRF];
        $_GET     = [];
        $_POST    = [];
        $_FILES   = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
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

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un compte client vérifié.
     *
     * @param string $email       Adresse e-mail unique
     * @param string $accountType 'individual' ou 'company'
     * @return int Identifiant du compte créé
     */
    private function insertCustomer(string $email, string $accountType = 'individual'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, ?, 'customer', 'fr', NOW())",
            [$email, password_hash('Password123!', PASSWORD_BCRYPT), $accountType]
        );

        if ($accountType === 'company') {
            self::$db->insert(
                "INSERT INTO account_companies (account_id, company_name, siret)
                 VALUES (?, 'Acme SARL', '12345678901234')",
                [$id]
            );
        } else {
            self::$db->insert(
                "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
                 VALUES (?, 'Dupont', 'Jean', 'M')",
                [$id]
            );
        }

        return $id;
    }

    /**
     * Connecte un utilisateur via cookie JWT + connexion en base.
     *
     * @param int    $userId Identifiant du compte
     * @param string $role   Rôle JWT
     * @return string Token JWT généré
     */
    private function loginAs(int $userId, string $role = 'customer'): string
    {
        $token = Jwt::generate($userId, $role);
        $_COOKIE['auth_token'] = $token;
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
        return $token;
    }

    /**
     * Insère une adresse sauvegardée pour l'utilisateur.
     *
     * @param int    $userId Identifiant du compte
     * @param string $type   'billing' ou 'delivery'
     * @return int Identifiant de l'adresse créée
     */
    private function insertAddress(int $userId, string $type = 'billing'): int
    {
        return (int) self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone, saved)
             VALUES (?, ?, 'Jean', 'Dupont', 'M', '12 rue de la Paix', 'Paris', '75001', 'France', '0601020304', 1)",
            [$userId, $type]
        );
    }

    /**
     * Insère une commande pour l'utilisateur.
     *
     * @param int    $userId    Identifiant du compte
     * @param int    $addressId Identifiant de l'adresse de facturation
     * @param string $status    Statut de la commande
     * @return int Identifiant de la commande créée
     */
    private function insertOrder(int $userId, int $addressId, string $status = 'pending'): int
    {
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        return (int) self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_snapshot, status)
             VALUES (?, ?, '[]', 99.90, 'card', 0.00, ?, ?)",
            [$userId, 'TEST-' . bin2hex(random_bytes(4)), $snapshotId, $status]
        );
    }

    /**
     * Insère une commande delivered avec delivered_at configurable.
     *
     * @param int    $userId      Identifiant du compte
     * @param int    $addressId   Identifiant de l'adresse de facturation
     * @param string $deliveredAt Expression SQL pour delivered_at
     * @return int Identifiant de la commande créée
     */
    private function insertDeliveredOrder(int $userId, int $addressId, string $deliveredAt = 'NOW() - INTERVAL 7 DAY'): int
    {
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        return (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_snapshot, status, delivered_at)
             VALUES (?, ?, '[]', 99.90, 'card', 0.00, ?, 'delivered', {$deliveredAt})",
            [$userId, 'TEST-' . bin2hex(random_bytes(4)), $snapshotId]
        );
    }

    /**
     * Crée un objet Request.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return Request
     */
    private function makeRequest(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $uriQuery = [];
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $uriQuery);
        $_GET = array_merge($_GET, $uriQuery);
        return new Request();
    }

    /**
     * Instancie AccountOrderController.
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return AccountOrderController
     */
    private function makeController(string $method, string $uri): AccountOrderController
    {
        return new AccountOrderController($this->makeRequest($method, $uri));
    }

    // ----------------------------------------------------------------
    // orders()
    // ----------------------------------------------------------------

    /**
     * Un compte individual authentifié voit la liste de ses commandes.
     */
    public function testOrdersRendersForIndividual(): void
    {
        $userId = $this->insertCustomer('orders.ind@test.local', 'individual');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes')->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    /**
     * Filtres de statut valides → ne lève pas d'exception.
     */
    public function testOrdersWithStatusFilter(): void
    {
        $userId = $this->insertCustomer('orders.status@test.local');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes?status=pending')
             ->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    /**
     * Filtre période 3months → s'affiche.
     */
    public function testOrdersWithPeriod3Months(): void
    {
        $userId = $this->insertCustomer('orders.3m@test.local');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes?period=3months')
             ->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    /**
     * Filtre période année → s'affiche.
     */
    public function testOrdersWithPeriodYear(): void
    {
        $userId = $this->insertCustomer('orders.year@test.local');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes?period=2024')
             ->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    /**
     * per_page invalide → remis à 10.
     */
    public function testOrdersInvalidPerPage(): void
    {
        $userId = $this->insertCustomer('orders.perpage@test.local');
        $this->loginAs($userId);

        ob_start();
        $this->makeController('GET', '/fr/mon-compte/commandes?per_page=999')
             ->orders(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-filters', $output);
    }

    /**
     * Un compte company est redirigé vers le dashboard.
     */
    public function testOrdersRedirectsForCompany(): void
    {
        $userId = $this->insertCustomer('orders.co@test.local', 'company');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/commandes')->orders(['lang' => 'fr']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testOrdersRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/commandes')->orders(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // orderDetail()
    // ----------------------------------------------------------------

    /**
     * Commande existante → vue détail affichée.
     */
    public function testOrderDetailRenders(): void
    {
        $userId    = $this->insertCustomer('detail.ok@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'paid');

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
             ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('order-detail-header', $output);
    }

    /**
     * Commande introuvable → redirigé vers la liste.
     */
    public function testOrderDetailNotFound(): void
    {
        $userId = $this->insertCustomer('detail.notfound@test.local');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/commandes/99999')
             ->orderDetail(['lang' => 'fr', 'id' => '99999']);
    }

    /**
     * Commande delivered dans la fenêtre de retour → cancellableReturn=true.
     */
    public function testOrderDetailDeliveredInWindow(): void
    {
        $userId    = $this->insertCustomer('detail.delivered@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertDeliveredOrder($userId, $addressId, 'NOW() - INTERVAL 1 DAY');

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
             ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('order-detail-header', $output);
    }

    /**
     * Commande delivered hors fenêtre → returnExpired=true.
     */
    public function testOrderDetailDeliveredExpired(): void
    {
        $userId    = $this->insertCustomer('detail.expired@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertDeliveredOrder($userId, $addressId, 'NOW() - INTERVAL 60 DAY');

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
             ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('order-detail-header', $output);
    }

    /**
     * Commande delivered sans delivered_at → deliveredNoDate=true.
     */
    public function testOrderDetailDeliveredNoDate(): void
    {
        $userId    = $this->insertCustomer('detail.nodate@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        $orderId = (int) self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_snapshot, status)
             VALUES (?, ?, '[]', 99.90, 'card', 0.00, ?, 'delivered')",
            [$userId, 'TEST-' . bin2hex(random_bytes(4)), $snapshotId]
        );

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
             ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        $output = ob_get_clean();

        $this->assertStringContainsString('order-detail-header', $output);
    }

    /**
     * Flash success/error sont consommés.
     */
    public function testOrderDetailConsumesFlash(): void
    {
        $userId    = $this->insertCustomer('detail.flash@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId);
        $_SESSION['flash']['order_success'] = 'ok';
        $_SESSION['flash']['order_error']   = 'err';

        ob_start();
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}")
             ->orderDetail(['lang' => 'fr', 'id' => (string) $orderId]);
        ob_get_clean();

        $this->assertArrayNotHasKey('order_success', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('order_error', $_SESSION['flash'] ?? []);
    }

    /**
     * Compte company → redirigé.
     */
    public function testOrderDetailRedirectsForCompany(): void
    {
        $userId = $this->insertCustomer('detail.co@test.local', 'company');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/commandes/1')
             ->orderDetail(['lang' => 'fr', 'id' => '1']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testOrderDetailRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/commandes/1')
             ->orderDetail(['lang' => 'fr', 'id' => '1']);
    }

    // ----------------------------------------------------------------
    // cancelOrder()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testCancelOrderCsrfFails(): void
    {
        $userId    = $this->insertCustomer('cancel.csrf@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'pending');
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/annuler")
             ->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
    }

    /**
     * Commande pending → annulée avec succès.
     */
    public function testCancelOrderPendingSuccess(): void
    {
        $userId    = $this->insertCustomer('cancel.ok@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'pending');
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/annuler")
             ->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
    }

    /**
     * Commande delivered dans la fenêtre → return_requested.
     */
    public function testCancelOrderDeliveredInWindow(): void
    {
        $userId    = $this->insertCustomer('cancel.delivered@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertDeliveredOrder($userId, $addressId, 'NOW() - INTERVAL 1 DAY');
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/annuler")
             ->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
    }

    /**
     * Commande shipped → annulation échoue → order_error.
     */
    public function testCancelOrderShippedFails(): void
    {
        $userId    = $this->insertCustomer('cancel.shipped@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'shipped');
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/annuler")
             ->cancelOrder(['lang' => 'fr', 'id' => (string) $orderId]);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testCancelOrderRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/commandes/1/annuler')
             ->cancelOrder(['lang' => 'fr', 'id' => '1']);
    }

    // ----------------------------------------------------------------
    // requestRefund()
    // ----------------------------------------------------------------

    /**
     * CSRF invalide → redirige avec erreur.
     */
    public function testRequestRefundCsrfFails(): void
    {
        $userId    = $this->insertCustomer('refund.csrf@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'paid');
        $_POST = ['csrf_token' => 'bad'];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/remboursement")
             ->requestRefund(['lang' => 'fr', 'id' => (string) $orderId]);
    }

    /**
     * Commande introuvable → 404.
     */
    public function testRequestRefundOrderNotFound(): void
    {
        $userId = $this->insertCustomer('refund.notfound@test.local');
        $this->loginAs($userId);
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $this->makeController('POST', '/fr/mon-compte/commandes/99999/remboursement')
             ->requestRefund(['lang' => 'fr', 'id' => '99999']);
    }

    /**
     * Commande non remboursable (pending) → redirige avec erreur.
     */
    public function testRequestRefundNotEligible(): void
    {
        $userId    = $this->insertCustomer('refund.ineligible@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'pending');
        $_POST = ['csrf_token' => self::CSRF];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', "/fr/mon-compte/commandes/{$orderId}/remboursement")
             ->requestRefund(['lang' => 'fr', 'id' => (string) $orderId]);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testRequestRefundRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('POST', '/fr/mon-compte/commandes/1/remboursement')
             ->requestRefund(['lang' => 'fr', 'id' => '1']);
    }

    // ----------------------------------------------------------------
    // returnSlip()
    // ----------------------------------------------------------------

    /**
     * Commande return_requested → fiche de retour générée.
     */
    public function testReturnSlipForReturnRequested(): void
    {
        $userId    = $this->insertCustomer('slip.rr@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'return_requested');

        // Utilise un sous-classe avec sendPdfResponse surchargé pour éviter exit()
        $controller = new class ($this->makeRequest('GET', "/fr/mon-compte/commandes/{$orderId}/fiche-retour")) extends AccountOrderController {
            protected function sendPdfResponse(string $pdfBytes, string $filename): never
            {
                echo 'PDF:' . $filename;
                throw new \Core\Exception\HttpException(200, 'pdf');
            }
        };

        try {
            $controller->returnSlip(['lang' => 'fr', 'id' => (string) $orderId]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(200, $e->getCode());
        }
    }

    /**
     * Commande delivered dans la fenêtre → fiche de retour disponible.
     */
    public function testReturnSlipForDeliveredInWindow(): void
    {
        $userId    = $this->insertCustomer('slip.delivered@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertDeliveredOrder($userId, $addressId, 'NOW() - INTERVAL 1 DAY');

        $controller = new class ($this->makeRequest('GET', "/fr/mon-compte/commandes/{$orderId}/fiche-retour")) extends AccountOrderController {
            protected function sendPdfResponse(string $pdfBytes, string $filename): never
            {
                throw new \Core\Exception\HttpException(200, 'pdf');
            }
        };

        try {
            $controller->returnSlip(['lang' => 'fr', 'id' => (string) $orderId]);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(200, $e->getCode());
        }
    }

    /**
     * Commande non éligible → 404.
     */
    public function testReturnSlipNotEligible(): void
    {
        $userId    = $this->insertCustomer('slip.ineligible@test.local');
        $this->loginAs($userId);
        $addressId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $addressId, 'paid');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $this->makeController('GET', "/fr/mon-compte/commandes/{$orderId}/fiche-retour")
             ->returnSlip(['lang' => 'fr', 'id' => (string) $orderId]);
    }

    /**
     * Commande introuvable → 404.
     */
    public function testReturnSlipOrderNotFound(): void
    {
        $userId = $this->insertCustomer('slip.notfound@test.local');
        $this->loginAs($userId);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $this->makeController('GET', '/fr/mon-compte/commandes/99999/fiche-retour')
             ->returnSlip(['lang' => 'fr', 'id' => '99999']);
    }

    /**
     * Visiteur non authentifié → redirigé (302).
     */
    public function testReturnSlipRedirectsUnauthenticated(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $this->makeController('GET', '/fr/mon-compte/commandes/1/fiche-retour')
             ->returnSlip(['lang' => 'fr', 'id' => '1']);
    }
}
