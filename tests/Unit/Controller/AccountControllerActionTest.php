<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AccountController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\AccountModel;
use Model\AddressModel;
use Model\ConnectionModel;
use Model\DeviceConfirmTokenModel;
use Model\FavoriteModel;
use Model\OrderModel;
use Model\RgpdExportLogModel;
use Model\TrustedDeviceModel;
use PHPUnit\Framework\TestCase;
use Service\RateLimiterService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires des actions publiques d'AccountController (legacy).
 * Utilise TestableAccountController pour isoler auth, CSRF, view, redirect, abort.
 */
#[AllowMockObjectsWithoutExpectations]
class AccountControllerActionTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $_ENV['JWT_SECRET'] = 'test-secret-key-32-chars-long!!!';

        $dbStub = $this->createStub(Database::class);
        // fetchOne doit retourner une session active pour AuthMiddleware
        $dbStub->method('fetchOne')->willReturn(['id' => 1]);
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbStub);

        $_SESSION = ['csrf' => 'test-csrf-token'];
        $_GET     = [];
        $_POST    = ['csrf_token' => 'test-csrf-token'];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
        $_ENV['APP_URL'] = 'https://example.com';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function makeController(): TestableAccountController
    {
        $ctrl = new TestableAccountController(new Request());
        $ctrl->bootAuth();
        return $ctrl;
    }

    /**
     * Injecte un mock de modèle via réflexion sur AccountController.
     */
    private function inject(TestableAccountController $ctrl, string $prop, object $mock): void
    {
        $ref = new \ReflectionProperty(AccountController::class, $prop);
        $ref->setValue($ctrl, $mock);
    }

    // ----------------------------------------------------------------
    // newPaymentService() / newMailService() — seams
    // ----------------------------------------------------------------

    /**
     * newPaymentService() retourne un PaymentService.
     */
    public function testNewPaymentServiceReturnsInstance(): void
    {
        $ctrl = new AccountController(new Request());
        $ref  = new \ReflectionMethod(AccountController::class, 'newPaymentService');
        $result = $ref->invoke($ctrl);
        $this->assertInstanceOf(\Service\PaymentService::class, $result);
    }

    /**
     * newMailService() retourne un MailService.
     */
    public function testNewMailServiceReturnsInstance(): void
    {
        $ctrl = new AccountController(new Request());
        $ref  = new \ReflectionMethod(AccountController::class, 'newMailService');
        $result = $ref->invoke($ctrl);
        $this->assertInstanceOf(\Service\MailService::class, $result);
    }

    // ----------------------------------------------------------------
    // index()
    // ----------------------------------------------------------------

    /**
     * index() rend la vue account/index pour un compte individuel.
     */
    public function testIndexRendersViewIndividual(): void
    {
        $account  = ['id' => 1, 'account_type' => 'individual', 'email' => 'u@example.com'];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $favorites = $this->createStub(FavoriteModel::class);
        $favorites->method('countForUser')->willReturn(2);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('countForUser')->willReturn(3);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('getByUser')->willReturn([[], []]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'favorites', $favorites);
        $this->inject($ctrl, 'orders', $orders);
        $this->inject($ctrl, 'addresses', $addresses);

        $ctrl->index(['lang' => 'fr']);

        $this->assertSame('account/index', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['isCompany'] ?? true);
        $this->assertSame(2, $ctrl->lastViewData['favoriteCount'] ?? null);
        $this->assertSame(3, $ctrl->lastViewData['orderCount'] ?? null);
        $this->assertSame(2, $ctrl->lastViewData['addressCount'] ?? null);
    }

    /**
     * index() rend la vue account/index pour un compte company — orderCount/addressCount = 0.
     */
    public function testIndexRendersViewCompany(): void
    {
        $account  = ['id' => 2, 'account_type' => 'company', 'email' => 'c@example.com'];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $favorites = $this->createStub(FavoriteModel::class);
        $favorites->method('countForUser')->willReturn(0);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'favorites', $favorites);

        $ctrl->index(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['isCompany'] ?? false);
        $this->assertSame(0, $ctrl->lastViewData['orderCount'] ?? -1);
        $this->assertSame(0, $ctrl->lastViewData['addressCount'] ?? -1);
    }

    /**
     * index() lit et efface le flash info.
     */
    public function testIndexClearsFlashInfo(): void
    {
        $_SESSION['flash']['info'] = 'msg';

        $account  = ['id' => 1, 'account_type' => 'individual', 'email' => 'u@example.com'];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $favorites = $this->createStub(FavoriteModel::class);
        $favorites->method('countForUser')->willReturn(0);
        $orders    = $this->createStub(OrderModel::class);
        $orders->method('countForUser')->willReturn(0);
        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('getByUser')->willReturn([]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'favorites', $favorites);
        $this->inject($ctrl, 'orders', $orders);
        $this->inject($ctrl, 'addresses', $addresses);

        $ctrl->index(['lang' => 'fr']);

        $this->assertSame('msg', $ctrl->lastViewData['info'] ?? null);
        $this->assertArrayNotHasKey('info', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // orders()
    // ----------------------------------------------------------------

    /**
     * orders() rend la vue account/orders.
     */
    public function testOrdersRendersView(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('countForUser')->willReturn(5);
        $orders->method('getForUser')->willReturn([]);
        $orders->method('getAvailableYearsForUser')->willReturn([2025, 2024]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->orders(['lang' => 'fr']);

        $this->assertSame('account/orders', $ctrl->lastView);
    }

    /**
     * orders() per_page invalide → fallback 10.
     */
    public function testOrdersInvalidPerPageFallback(): void
    {
        $_GET['per_page'] = '999';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('countForUser')->willReturn(0);
        $orders->method('getForUser')->willReturn([]);
        $orders->method('getAvailableYearsForUser')->willReturn([]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->orders(['lang' => 'fr']);

        $this->assertSame(10, $ctrl->lastViewData['perPage'] ?? null);
    }

    /**
     * orders() period = year (int) → $year et $period='year'.
     */
    public function testOrdersYearPeriodExtracted(): void
    {
        $_GET['period'] = '2024';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('countForUser')->willReturn(0);
        $orders->method('getForUser')->willReturn([]);
        $orders->method('getAvailableYearsForUser')->willReturn([2024]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->orders(['lang' => 'fr']);

        $this->assertSame('account/orders', $ctrl->lastView);
    }

    /**
     * orders() status invalide → null (pas de filtre).
     */
    public function testOrdersInvalidStatusIgnored(): void
    {
        $_GET['status'] = 'hacked';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('countForUser')->willReturn(0);
        $orders->method('getForUser')->willReturn([]);
        $orders->method('getAvailableYearsForUser')->willReturn([]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->orders(['lang' => 'fr']);

        // statusFilter dans la vue = $rawStatus (la valeur brute), le filtre SQL est null séparément
        $this->assertSame('hacked', $ctrl->lastViewData['statusFilter'] ?? 'notset');
    }

    // ----------------------------------------------------------------
    // orderDetail()
    // ----------------------------------------------------------------

    /**
     * orderDetail() commande introuvable → redirect.
     */
    public function testOrderDetailNotFoundRedirects(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn(null);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->orderDetail(['lang' => 'fr', 'id' => '99']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * orderDetail() commande delivered sans delivered_at → deliveredNoDate=true.
     */
    public function testOrderDetailDeliveredWithoutDateSetsDeliveredNoDate(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id' => 1, 'status' => 'delivered', 'delivered_at' => '',
            'content' => '[]', 'shipping_discount' => 0,
            'order_reference' => 'REF001',
        ];

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->orderDetail(['lang' => 'fr', 'id' => '1']);

        $this->assertTrue($ctrl->lastViewData['deliveredNoDate'] ?? false);
    }

    /**
     * orderDetail() commande delivered dans la fenêtre → cancellableReturn=true.
     */
    public function testOrderDetailDeliveredInWindowSetsCancellableReturn(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id'               => 1,
            'status'           => 'delivered',
            'delivered_at'     => date('Y-m-d H:i:s', time() - 86400), // hier
            'content'          => '[]',
            'shipping_discount' => 0,
            'order_reference'  => 'REF002',
        ];

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->orderDetail(['lang' => 'fr', 'id' => '1']);

        $this->assertTrue($ctrl->lastViewData['cancellableReturn'] ?? false);
    }

    /**
     * orderDetail() commande delivered hors fenêtre → returnExpired=true.
     */
    public function testOrderDetailDeliveredExpiredSetsReturnExpired(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id'               => 1,
            'status'           => 'delivered',
            'delivered_at'     => date('Y-m-d H:i:s', time() - (21 * 86400)), // 21 jours
            'content'          => '[]',
            'shipping_discount' => 0,
            'order_reference'  => 'REF003',
        ];

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->orderDetail(['lang' => 'fr', 'id' => '1']);

        $this->assertTrue($ctrl->lastViewData['returnExpired'] ?? false);
    }

    /**
     * orderDetail() commande shipped (pas delivered) → flags tous false.
     */
    public function testOrderDetailShippedStatus(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id'               => 1,
            'status'           => 'shipped',
            'delivered_at'     => null,
            'content'          => '[]',
            'shipping_discount' => 5.0,
            'order_reference'  => 'REF004',
        ];

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->orderDetail(['lang' => 'fr', 'id' => '1']);

        $this->assertFalse($ctrl->lastViewData['cancellableReturn'] ?? true);
        $this->assertFalse($ctrl->lastViewData['returnExpired'] ?? true);
        $this->assertSame(5.0, $ctrl->lastViewData['shippingDiscount'] ?? null);
    }

    /**
     * orderDetail() lit et efface les flashes order_success et order_error.
     */
    public function testOrderDetailClearsOrderFlashes(): void
    {
        $_SESSION['flash']['order_success'] = 'ok';
        $_SESSION['flash']['order_error']   = 'err';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id' => 1, 'status' => 'paid', 'delivered_at' => null,
            'content' => '[]', 'shipping_discount' => 0,
            'order_reference' => 'REF005',
        ];

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->orderDetail(['lang' => 'fr', 'id' => '1']);

        $this->assertSame('ok', $ctrl->lastViewData['success'] ?? null);
        $this->assertSame('err', $ctrl->lastViewData['error'] ?? null);
        $this->assertArrayNotHasKey('order_success', $_SESSION['flash'] ?? []);
        $this->assertArrayNotHasKey('order_error', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // cancelOrder()
    // ----------------------------------------------------------------

    /**
     * cancelOrder() CSRF invalide → redirect.
     */
    public function testCancelOrderCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * cancelOrder() card+paid sans numappel/numtrans → annulation normale.
     */
    public function testCancelOrderCardPaidMissingRefsCancelNormal(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id'             => 1,
            'payment_method' => 'card',
            'status'         => 'paid',
            'ca_numappel'    => '',
            'ca_numtrans'    => '',
            'price'          => 100.0,
            'order_reference' => 'REF001',
        ];

        $orders = $this->createMock(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);
        $orders->method('cancelForUser')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * cancelOrder() card+paid avec numappel/numtrans + refund OK → statut 'refunded'.
     */
    public function testCancelOrderCardPaidWithRefsRefundSucceeds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id'             => 1,
            'payment_method' => 'card',
            'status'         => 'paid',
            'ca_numappel'    => 'APP001',
            'ca_numtrans'    => 'TRS001',
            'price'          => 50.0,
            'order_reference' => 'REF002',
        ];

        $orders = $this->createMock(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);
        $orders->expects($this->once())->method('updateStatus')->with(1, 'refunded');

        $paymentService = $this->createStub(\Service\PaymentService::class);
        $paymentService->method('callGaeCancelOrRefund')->willReturn(true);

        $ctrl = $this->makeController();
        $ctrl->fakePaymentService = $paymentService;
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * cancelOrder() card+paid avec numappel/numtrans + refund KO → flash cancel_refund_failed.
     */
    public function testCancelOrderCardPaidWithRefsRefundFails(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id'             => 1,
            'payment_method' => 'card',
            'status'         => 'paid',
            'ca_numappel'    => 'APP001',
            'ca_numtrans'    => 'TRS001',
            'price'          => 50.0,
            'order_reference' => 'REF003',
        ];

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);

        $paymentService = $this->createStub(\Service\PaymentService::class);
        $paymentService->method('callGaeCancelOrRefund')->willReturn(false);

        $ctrl = $this->makeController();
        $ctrl->fakePaymentService = $paymentService;
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }

        $this->assertSame('account.cancel_refund_failed', $_SESSION['flash']['order_error'] ?? null);
    }

    /**
     * cancelOrder() cancelForUser fails + requestReturnForUser OK → flash return_requested.
     */
    public function testCancelOrderReturnRequestedSucceeds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id'             => 1,
            'payment_method' => 'check',
            'status'         => 'delivered',
            'order_reference' => 'REF004',
        ];

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);
        $orders->method('cancelForUser')->willReturn(false);
        $orders->method('requestReturnForUser')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * cancelOrder() cancelForUser fails + requestReturnForUser fails → flash cancel_failed.
     */
    public function testCancelOrderBothFail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id'             => 1,
            'payment_method' => 'check',
            'status'         => 'processing',
            'order_reference' => 'REF005',
        ];

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);
        $orders->method('cancelForUser')->willReturn(false);
        $orders->method('requestReturnForUser')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }

        $this->assertSame('account.order_cancel_failed', $_SESSION['flash']['order_error'] ?? null);
    }

    // ----------------------------------------------------------------
    // requestRefund()
    // ----------------------------------------------------------------

    /**
     * requestRefund() CSRF invalide → redirect.
     */
    public function testRequestRefundCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestRefund(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * requestRefund() commande introuvable → 404.
     */
    public function testRequestRefundOrderNotFoundAborts(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn(null);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestRefund(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * requestRefund() statut non éligible (shipped) → flash not_eligible.
     */
    public function testRequestRefundNonEligibleStatusRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn(['id' => 1, 'status' => 'shipped']);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestRefund(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }

        $this->assertSame('account.order_refund_not_eligible', $_SESSION['flash']['order_error'] ?? null);
    }

    /**
     * requestRefund() statut éligible + refund OK → flash refund_initiated.
     */
    public function testRequestRefundEligibleSucceeds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn(['id' => 1, 'status' => 'paid']);

        $refundService = $this->createStub(\Service\RefundService::class);
        $refundService->method('refund')->willReturn('ok');

        $ctrl = $this->makeController();
        $ctrl->fakeRefundService = $refundService;
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestRefund(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * requestRefund() statut éligible + refund throws → flash refund_failed.
     */
    public function testRequestRefundEligibleServiceThrowsRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn(['id' => 1, 'status' => 'processing']);

        $refundService = $this->createStub(\Service\RefundService::class);
        $refundService->method('refund')->willThrowException(new \RuntimeException('fail'));

        $ctrl = $this->makeController();
        $ctrl->fakeRefundService = $refundService;
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestRefund(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }

        $this->assertSame('account.order_refund_failed', $_SESSION['flash']['order_error'] ?? null);
    }

    // ----------------------------------------------------------------
    // addresses()
    // ----------------------------------------------------------------

    /**
     * addresses() rend la vue account/addresses.
     */
    public function testAddressesRendersView(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('getByUser')->willReturn([['id' => 10], ['id' => 11]]);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('getAddressIdsWithActiveOrders')->willReturn([11]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->addresses(['lang' => 'fr']);

        $this->assertSame('account/addresses', $ctrl->lastView);
        $this->assertSame([11], $ctrl->lastViewData['lockedIds'] ?? null);
    }

    // ----------------------------------------------------------------
    // addAddress()
    // ----------------------------------------------------------------

    /**
     * addAddress() CSRF invalide → redirect.
     */
    public function testAddAddressCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * addAddress() champs requis manquants → flash address_required_fields.
     */
    public function testAddAddressMissingFieldsRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'firstname' => '', 'lastname' => 'Dupont', 'street' => '1 rue',
            'city' => 'Paris', 'zip_code' => '75001', 'phone' => '0601020304',
            'country' => 'France', 'csrf_token' => 'test-csrf-token',
        ];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * addAddress() code postal France invalide → flash address_zip_invalid.
     */
    public function testAddAddressInvalidZipFranceRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'firstname'   => 'Jean', 'lastname' => 'Dupont',
            'street'      => '1 rue Rivoli', 'city' => 'Paris',
            'zip_code'    => '20000', // Corse invalide
            'phone'       => '0601020304', 'country' => 'France',
            'csrf_token'  => 'test-csrf-token',
        ];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * addAddress() valide → addresses->create + redirect succès.
     */
    public function testAddAddressValidCreatesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'type'       => 'billing',
            'civility'   => 'M',
            'firstname'  => 'Jean', 'lastname' => 'Dupont',
            'street'     => '1 rue Rivoli', 'city' => 'Paris',
            'zip_code'   => '75001', 'phone' => '0601020304',
            'country'    => 'France',
            'csrf_token' => 'test-csrf-token',
        ];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createMock(AddressModel::class);
        $addresses->expects($this->once())->method('create');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->addAddress(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // editAddress()
    // ----------------------------------------------------------------

    /**
     * editAddress() adresse introuvable → redirect.
     */
    public function testEditAddressNotFoundRedirects(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn(null);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->editAddress(['lang' => 'fr', 'id' => '99']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * editAddress() adresse existante → vue account/address_form.
     */
    public function testEditAddressRendersForm(): void
    {
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $address = ['id' => 10, 'firstname' => 'Jean', 'lastname' => 'Dupont'];
        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($address);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);

        $ctrl->editAddress(['lang' => 'fr', 'id' => '10']);

        $this->assertSame('account/address_form', $ctrl->lastView);
        $this->assertSame($address, $ctrl->lastViewData['address'] ?? null);
    }

    // ----------------------------------------------------------------
    // updateAddress()
    // ----------------------------------------------------------------

    /**
     * updateAddress() CSRF invalide → redirect.
     */
    public function testUpdateAddressCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateAddress() adresse introuvable → redirect.
     */
    public function testUpdateAddressNotFoundRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn(null);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateAddress() champs manquants → flash address_required_fields.
     */
    public function testUpdateAddressMissingFieldsRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'firstname' => '', 'lastname' => 'Dupont', 'street' => '1 rue',
            'city' => 'Paris', 'zip_code' => '75001', 'phone' => '0601020304',
            'country' => 'France', 'csrf_token' => 'test-csrf-token',
        ];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $address = ['id' => 1, 'firstname' => 'Jean'];
        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($address);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateAddress() commande active → flash address_edit_blocked.
     */
    public function testUpdateAddressLockedByOrderRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '1 rue', 'city' => 'Paris', 'zip_code' => '75001',
            'phone' => '0601020304', 'country' => 'France', 'csrf_token' => 'test-csrf-token',
        ];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $address = ['id' => 1, 'firstname' => 'Jean'];
        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($address);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('hasActiveOrderForAddress')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateAddress() valide → addresses->update + redirect succès.
     */
    public function testUpdateAddressValidUpdatesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont',
            'street' => '1 rue', 'city' => 'Paris', 'zip_code' => '75001',
            'phone' => '0601020304', 'country' => 'France', 'csrf_token' => 'test-csrf-token',
        ];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $address = ['id' => 1, 'firstname' => 'Jean'];
        $addresses = $this->createMock(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($address);
        $addresses->expects($this->once())->method('update');

        $orders = $this->createStub(OrderModel::class);
        $orders->method('hasActiveOrderForAddress')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // deleteAddress()
    // ----------------------------------------------------------------

    /**
     * deleteAddress() CSRF invalide → redirect.
     */
    public function testDeleteAddressCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * deleteAddress() adresse introuvable → redirect.
     */
    public function testDeleteAddressNotFoundRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn(null);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * deleteAddress() commande active → flash address_delete_blocked.
     */
    public function testDeleteAddressLockedByOrderRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn(['id' => 1]);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('hasActiveOrderForAddress')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * deleteAddress() valide → deleteForUser + redirect succès.
     */
    public function testDeleteAddressValidDeletesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $addresses = $this->createMock(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn(['id' => 1]);
        $addresses->expects($this->once())->method('deleteForUser');

        $orders = $this->createStub(OrderModel::class);
        $orders->method('hasActiveOrderForAddress')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'addresses', $addresses);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAddress(['lang' => 'fr', 'id' => '1']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // favorites()
    // ----------------------------------------------------------------

    /**
     * favorites() rend la vue account/favorites.
     */
    public function testFavoritesRendersView(): void
    {
        $account  = ['id' => 1, 'email' => 'u@example.com'];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $favorites = $this->createStub(FavoriteModel::class);
        $favorites->method('getByUser')->willReturn([['wine_id' => 5]]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'favorites', $favorites);

        $ctrl->favorites(['lang' => 'fr']);

        $this->assertSame('account/favorites', $ctrl->lastView);
    }

    // ----------------------------------------------------------------
    // security() (legacy AccountController)
    // ----------------------------------------------------------------

    /**
     * security() rend la vue account/security.
     */
    public function testSecurityRendersView(): void
    {
        $account  = ['id' => 1, 'password' => 'hash'];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $connections = $this->createStub(ConnectionModel::class);
        $connections->method('getActiveForUser')->willReturn([]);

        $devices = $this->createStub(TrustedDeviceModel::class);
        $devices->method('getForUser')->willReturn([]);

        $orders = $this->createStub(OrderModel::class);
        $orders->method('countActiveOrdersForUser')->willReturn(0);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'connections', $connections);
        $this->inject($ctrl, 'trustedDevices', $devices);
        $this->inject($ctrl, 'orders', $orders);

        $ctrl->security(['lang' => 'fr']);

        $this->assertSame('account/security', $ctrl->lastView);
    }

    // ----------------------------------------------------------------
    // changePassword() (legacy AccountController)
    // ----------------------------------------------------------------

    /**
     * changePassword() CSRF invalide → redirect.
     */
    public function testChangePasswordCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * changePassword() rate limit dépassé → redirect.
     */
    public function testChangePasswordRateLimitExceededRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(false);
        $rateLimiter->method('getRetryAfter')->willReturn(600);

        $ctrl = $this->makeController();
        $ref = new \ReflectionProperty(AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * changePassword() mot de passe actuel incorrect → flash.
     */
    public function testChangePasswordWrongCurrentPasswordRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'current_password'      => 'wrong',
            'new_password'          => 'NewStr0ng!Pass',
            'new_password_confirm'  => 'NewStr0ng!Pass',
            'csrf_token'            => 'test-csrf-token',
        ];

        $account  = ['id' => 1, 'email' => 'u@example.com', 'password' => password_hash('correct', PASSWORD_BCRYPT)];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $ref = new \ReflectionProperty(AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * changePassword() nouveau MDP = ancien → flash new_password.
     */
    public function testChangePasswordSameAsCurrentRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $pass = 'S@mePass1!';
        $_POST = ['current_password' => $pass, 'new_password' => $pass, 'new_password_confirm' => $pass, 'csrf_token' => 'test-csrf-token'];

        $account  = ['id' => 1, 'email' => 'u@example.com', 'password' => password_hash($pass, PASSWORD_BCRYPT)];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $ref = new \ReflectionProperty(AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * changePassword() MDP faible → flash new_password.
     */
    public function testChangePasswordWeakNewPasswordRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'current_password' => 'OldStr0ng!Pass',
            'new_password' => 'weak', 'new_password_confirm' => 'weak',
            'csrf_token' => 'test-csrf-token',
        ];

        $account  = ['id' => 1, 'email' => 'u@example.com', 'password' => password_hash('OldStr0ng!Pass', PASSWORD_BCRYPT)];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $ref = new \ReflectionProperty(AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * changePassword() confirmations non concordantes → flash.
     */
    public function testChangePasswordMismatchConfirmRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'current_password' => 'OldStr0ng!Pass',
            'new_password' => 'NewStr0ng!Pass1', 'new_password_confirm' => 'NewStr0ng!Pass2',
            'csrf_token' => 'test-csrf-token',
        ];

        $account  = ['id' => 1, 'email' => 'u@example.com', 'password' => password_hash('OldStr0ng!Pass', PASSWORD_BCRYPT)];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $ref = new \ReflectionProperty(AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * changePassword() valide → savePendingPassword + redirect.
     */
    public function testChangePasswordValidSavesPendingAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'current_password' => 'OldStr0ng!Pass',
            'new_password' => 'NewStr0ng!Pass1', 'new_password_confirm' => 'NewStr0ng!Pass1',
            'csrf_token' => 'test-csrf-token',
        ];

        $account  = [
            'id' => 1, 'email' => 'u@example.com',
            'firstname' => 'Jean', 'lastname' => 'Dupont', 'lang' => 'fr',
            'password' => password_hash('OldStr0ng!Pass', PASSWORD_BCRYPT),
        ];
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findById')->willReturn($account);
        $accounts->expects($this->once())->method('savePendingPassword');
        $accounts->method('saveSecurityConfirmToken');

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $ref = new \ReflectionProperty(AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // revokeAllUserSessions() (legacy)
    // ----------------------------------------------------------------

    /**
     * revokeAllUserSessions() CSRF invalide → redirect sans revoquer.
     */
    public function testRevokeAllSessionsCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createMock(AccountModel::class);
        $accounts->expects($this->never())->method('revokeAllSessions');

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->revokeAllUserSessions(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * revokeAllUserSessions() CSRF valide → revokeAllSessions + redirect /.
     */
    public function testRevokeAllSessionsValidRevokesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createMock(AccountModel::class);
        $accounts->expects($this->once())->method('revokeAllSessions')->with(1);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->revokeAllUserSessions(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // revokeSession() (legacy)
    // ----------------------------------------------------------------

    /**
     * revokeSession() CSRF invalide → redirect sans revoquer.
     */
    public function testRevokeSessionCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $connections = $this->createMock(ConnectionModel::class);
        $connections->expects($this->never())->method('revokeById');

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();
        $this->inject($ctrl, 'connections', $connections);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->revokeSession(['lang' => 'fr', 'id' => '5']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * revokeSession() id=0 → redirect sans revoquer.
     */
    public function testRevokeSessionIdZeroRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $connections = $this->createMock(ConnectionModel::class);
        $connections->expects($this->never())->method('revokeById');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'connections', $connections);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->revokeSession(['lang' => 'fr', 'id' => '0']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * revokeSession() token révoqué = token courant → redirect /.
     */
    public function testRevokeSessionCurrentTokenRedirectsToRoot(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        // Le token courant est le JWT généré par bootAuth()
        $jwtToken = (string) $ctrl->authToken;

        $connections = $this->createMock(ConnectionModel::class);
        $connections->method('getTokenById')->willReturn($jwtToken);
        $connections->expects($this->once())->method('revokeById')->with(5, 1);

        $this->inject($ctrl, 'connections', $connections);

        $ex = null;

        ob_start();
        try {
            $ctrl->revokeSession(['lang' => 'fr', 'id' => '5']);
        } catch (HttpException $e) {
            $ex = $e;
        } finally {
            ob_end_clean();
        }

        $this->assertNotNull($ex);
        $this->assertStringContainsString('/fr', (string) $ex->location);
    }

    /**
     * revokeSession() token révoqué != courant → redirect sécurité.
     */
    public function testRevokeSessionDifferentTokenRedirectsToSecurity(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $connections = $this->createMock(ConnectionModel::class);
        // getTokenById retourne un token différent du JWT courant → pas de déconnexion
        $connections->method('getTokenById')->willReturn('other-session-token-not-current');
        $connections->expects($this->once())->method('revokeById')->with(5, 1);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'connections', $connections);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->revokeSession(['lang' => 'fr', 'id' => '5']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // newDevice() (legacy)
    // ----------------------------------------------------------------

    /**
     * newDevice() sans pending_device → redirect.
     */
    public function testNewDeviceWithoutPendingRedirects(): void
    {
        $ctrl = $this->makeController();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->newDevice(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * newDevice() avec pending_device → vue account/new_device.
     */
    public function testNewDeviceWithPendingRendersView(): void
    {
        $_SESSION['pending_device'] = ['device_name' => 'Firefox', 'mfa_token' => 'tok99'];

        $ctrl = $this->makeController();
        $ctrl->newDevice(['lang' => 'fr']);

        $this->assertSame('account/new_device', $ctrl->lastView);
        $this->assertSame('Firefox', $ctrl->lastViewData['deviceName'] ?? null);
    }

    // ----------------------------------------------------------------
    // confirmDevice() (legacy)
    // ----------------------------------------------------------------

    /**
     * confirmDevice() → vue account/device_confirmed.
     */
    public function testConfirmDeviceRendersView(): void
    {
        $_GET['token'] = 'sometoken';

        $deviceTokens = $this->createStub(DeviceConfirmTokenModel::class);
        $deviceTokens->method('confirm')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'deviceConfirmTokens', $deviceTokens);

        $ctrl->confirmDevice(['lang' => 'fr']);

        $this->assertSame('account/device_confirmed', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // resetSecurity() (legacy)
    // ----------------------------------------------------------------

    /**
     * resetSecurity() CSRF invalide → redirect.
     */
    public function testResetSecurityCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->resetSecurity(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * resetSecurity() mot de passe incorrect → redirect.
     */
    public function testResetSecurityWrongPasswordRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['password' => 'wrong', 'csrf_token' => 'test-csrf-token'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'password' => password_hash('correct', PASSWORD_BCRYPT)]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->resetSecurity(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * resetSecurity() valide → revokeAllSessions + deleteAllForUser + redirect /.
     */
    public function testResetSecurityValidRevokesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['password' => 'Correct1!Pass', 'csrf_token' => 'test-csrf-token'];

        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'password' => password_hash('Correct1!Pass', PASSWORD_BCRYPT)]);
        $accounts->expects($this->once())->method('revokeAllSessions')->with(1);

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->once())->method('deleteAllForUser')->with(1);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'trustedDevices', $devices);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->resetSecurity(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // untrustAllDevices() (legacy)
    // ----------------------------------------------------------------

    /**
     * untrustAllDevices() CSRF invalide → redirect.
     */
    public function testUntrustAllDevicesCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->untrustAllDevices(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * untrustAllDevices() valide → deleteAllForUser + flash success.
     */
    public function testUntrustAllDevicesValidDeletesAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->once())->method('deleteAllForUser')->with(1);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'trustedDevices', $devices);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->untrustAllDevices(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // cancelMfa() (legacy)
    // ----------------------------------------------------------------

    /**
     * cancelMfa() token vide → revoked=false.
     */
    public function testCancelMfaEmptyTokenRendersNotRevoked(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->cancelMfa(['lang' => 'fr']);

        $this->assertSame('account/mfa_cancelled', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['revoked'] ?? true);
    }

    /**
     * cancelMfa() token valide → deleteByToken + revoked=true.
     */
    public function testCancelMfaValidTokenRevokesAndRendersTrue(): void
    {
        $_GET = ['token' => 'goodtoken'];

        $deviceTokens = $this->createMock(DeviceConfirmTokenModel::class);
        $deviceTokens->method('findByToken')->willReturn(['id' => 1]);
        $deviceTokens->expects($this->once())->method('deleteByToken')->with('goodtoken');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'deviceConfirmTokens', $deviceTokens);

        $ctrl->cancelMfa(['lang' => 'fr']);

        $this->assertSame('account/mfa_cancelled', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['revoked'] ?? false);
    }

    // ----------------------------------------------------------------
    // untrustDevice() (legacy)
    // ----------------------------------------------------------------

    /**
     * untrustDevice() CSRF invalide → redirect sans untrust.
     */
    public function testUntrustDeviceCsrfInvalidSkipsUntrust(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['device_token' => 'abc', 'csrf_token' => 'test-csrf-token'];

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->never())->method('untrust');

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();
        $this->inject($ctrl, 'trustedDevices', $devices);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->untrustDevice(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * untrustDevice() valide → untrust + redirect.
     */
    public function testUntrustDeviceValidCallsUntrustAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['device_token' => 'device-xyz', 'csrf_token' => 'test-csrf-token'];

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->once())->method('untrust')->with(1, 'device-xyz');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'trustedDevices', $devices);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->untrustDevice(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // profile() (legacy)
    // ----------------------------------------------------------------

    /**
     * profile() rend la vue account/profile avec les flashes lus et effacés.
     */
    public function testProfileRendersViewAndClearsFlash(): void
    {
        $_SESSION['flash']['profile_success']   = 'ok';
        $_SESSION['flash']['profile_errors']    = [];
        $_SESSION['flash']['email_change_sent'] = true;

        $account  = ['id' => 1, 'email' => 'u@example.com'];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->profile(['lang' => 'fr']);

        $this->assertSame('account/profile', $ctrl->lastView);
        $this->assertSame('ok', $ctrl->lastViewData['success'] ?? null);
        $this->assertTrue($ctrl->lastViewData['emailChangeSent'] ?? false);
        $this->assertArrayNotHasKey('profile_success', $_SESSION['flash'] ?? []);
    }

    // ----------------------------------------------------------------
    // updateProfile() — CSRF invalide
    // ----------------------------------------------------------------

    /**
     * updateProfile() CSRF invalide → redirect.
     */
    public function testUpdateProfileCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateProfile() individual valide → updateIndividualProfile + redirect.
     */
    public function testUpdateProfileIndividualValidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont', 'newsletter' => '0', 'csrf_token' => 'test-csrf-token'];

        $account  = ['id' => 1, 'account_type' => 'individual', 'newsletter' => false, 'lang' => 'fr'];
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findById')->willReturn($account);
        $accounts->expects($this->once())->method('updateIndividualProfile');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateProfile() company valide → updateCompanyProfile + redirect.
     */
    public function testUpdateProfileCompanyValidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['company_name' => 'ACME', 'siret' => '', 'newsletter' => '0', 'csrf_token' => 'test-csrf-token'];

        $account  = ['id' => 2, 'account_type' => 'company', 'newsletter' => false, 'lang' => 'fr'];
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findById')->willReturn($account);
        $accounts->expects($this->once())->method('updateCompanyProfile');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateProfile() newsletter 1→0 → updateNewsletter(false).
     */
    public function testUpdateProfileNewsletterToggleOff(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['civility' => 'M', 'firstname' => 'Jean', 'lastname' => 'Dupont', 'newsletter' => '0', 'csrf_token' => 'test-csrf-token'];

        $account  = ['id' => 1, 'account_type' => 'individual', 'newsletter' => true, 'lang' => 'fr'];
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findById')->willReturn($account);
        $accounts->method('updateIndividualProfile');
        $accounts->expects($this->once())->method('updateNewsletter')->with(1, false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateProfile(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // requestEmailChange() (legacy)
    // ----------------------------------------------------------------

    /**
     * requestEmailChange() CSRF invalide → redirect.
     */
    public function testRequestEmailChangeCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['new_email' => 'new@example.com', 'current_password' => 'pass', 'csrf_token' => 'test-csrf-token'];

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestEmailChange(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * requestEmailChange() email invalide → redirect.
     */
    public function testRequestEmailChangeInvalidEmailRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['new_email' => 'not-an-email', 'current_password' => 'pass', 'csrf_token' => 'test-csrf-token'];

        $ctrl = $this->makeController();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestEmailChange(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * requestEmailChange() email vide → redirect.
     */
    public function testRequestEmailChangeEmptyEmailRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['new_email' => '', 'current_password' => 'pass', 'csrf_token' => 'test-csrf-token'];

        $ctrl = $this->makeController();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestEmailChange(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // confirmEmailChange() (legacy)
    // ----------------------------------------------------------------

    /**
     * confirmEmailChange() token vide → vue success=false.
     */
    public function testConfirmEmailChangeEmptyTokenRendersError(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->confirmEmailChange(['lang' => 'fr']);

        $this->assertSame('account/email_change_confirm', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    // ----------------------------------------------------------------
    // revokeEmailChange() (legacy)
    // ----------------------------------------------------------------

    /**
     * revokeEmailChange() token vide → vue success=false.
     */
    public function testRevokeEmailChangeEmptyTokenRendersError(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->revokeEmailChange(['lang' => 'fr']);

        $this->assertSame('account/email_change_confirm', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * revokeEmailChange() token inconnu → vue success=false.
     */
    public function testRevokeEmailChangeUnknownTokenRendersError(): void
    {
        $_GET = ['token' => 'unknown'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByEmailChangeToken')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->revokeEmailChange(['lang' => 'fr']);

        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * revokeEmailChange() token valide → clearEmailChangeToken + vue success + revoked.
     */
    public function testRevokeEmailChangeValidTokenSucceeds(): void
    {
        $_GET = ['token' => 'validtoken'];

        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findByEmailChangeToken')->willReturn(['id' => 5, 'email' => 'u@example.com']);
        $accounts->expects($this->once())->method('clearEmailChangeToken')->with(5);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->revokeEmailChange(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
        $this->assertTrue($ctrl->lastViewData['revoked'] ?? false);
    }

    // ----------------------------------------------------------------
    // cancelEmailChange() (legacy)
    // ----------------------------------------------------------------

    /**
     * cancelEmailChange() CSRF invalide → redirect.
     */
    public function testCancelEmailChangeCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelEmailChange(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * cancelEmailChange() valide → clearEmailChangeToken + redirect.
     */
    public function testCancelEmailChangeValidCallsClearAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createMock(AccountModel::class);
        $accounts->expects($this->once())->method('clearEmailChangeToken')->with(1);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelEmailChange(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // deleteAccount() (legacy)
    // ----------------------------------------------------------------

    /**
     * deleteAccount() CSRF invalide → redirect.
     */
    public function testDeleteAccountCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * deleteAccount() confirm_text incorrect → redirect.
     */
    public function testDeleteAccountWrongConfirmTextRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['confirm_text' => 'WRONG', 'confirm_password' => '', 'csrf_token' => 'test-csrf-token'];

        $ctrl = $this->makeController();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * deleteAccount() account null → redirect.
     */
    public function testDeleteAccountNullAccountRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['confirm_text' => 'SUPPRESSION', 'confirm_password' => '', 'csrf_token' => 'test-csrf-token'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * deleteAccount() mot de passe incorrect → redirect.
     */
    public function testDeleteAccountWrongPasswordRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['confirm_text' => 'SUPPRESSION', 'confirm_password' => 'wrong', 'csrf_token' => 'test-csrf-token'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn([
            'id' => 1, 'email' => 'u@example.com',
            'password' => password_hash('correct', PASSWORD_BCRYPT),
        ]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * deleteAccount() Google (password null) → suppression sans vérif MDP.
     */
    public function testDeleteAccountGoogleNoPasswordCheck(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['confirm_text' => 'SUPPRESSION', 'csrf_token' => 'test-csrf-token'];

        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findById')->willReturn([
            'id' => 1, 'email' => 'u@example.com',
            'password' => null, 'firstname' => 'Jean', 'lang' => 'fr',
            'company_name' => null,
        ]);
        $accounts->expects($this->once())->method('revokeAllSessions')->with(1);
        $accounts->expects($this->once())->method('delete')->with(1);
        $accounts->method('getReactivationToken')->willReturn('tok123');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // confirmSecurityAction() (legacy)
    // ----------------------------------------------------------------

    /**
     * confirmSecurityAction() token vide → vue success=false.
     */
    public function testConfirmSecurityActionEmptyTokenRendersNoSuccess(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->confirmSecurityAction(['lang' => 'fr']);

        $this->assertSame('account/security-confirm', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * confirmSecurityAction() token inconnu → vue expired=true.
     */
    public function testConfirmSecurityActionUnknownTokenRendersExpired(): void
    {
        $_GET = ['token' => 'badtoken'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findBySecurityConfirmToken')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->confirmSecurityAction(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['expired'] ?? false);
    }

    /**
     * confirmSecurityAction() token valide → applyPendingPassword + vue success.
     */
    public function testConfirmSecurityActionValidAppliesAndRendersSuccess(): void
    {
        $_GET = ['token' => 'goodtoken'];

        $account = ['id' => 3, 'email' => 'u@example.com'];
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findBySecurityConfirmToken')->willReturn($account);
        $accounts->expects($this->once())->method('applyPendingPassword')->with(3);
        $accounts->expects($this->once())->method('consumeSecurityConfirmToken')->with(3);
        $accounts->method('getPendingSessionToken')->willReturn(null);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->confirmSecurityAction(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    /**
     * confirmSecurityAction() requestingToken → révoque session + untrust device.
     */
    public function testConfirmSecurityActionWithRequestingTokenRevokesSession(): void
    {
        $_GET = ['token' => 'goodtoken'];

        $account = ['id' => 3];
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findBySecurityConfirmToken')->willReturn($account);
        $accounts->method('applyPendingPassword');
        $accounts->method('consumeSecurityConfirmToken');
        $accounts->method('getPendingSessionToken')->willReturn('reqtok');

        $connections = $this->createMock(ConnectionModel::class);
        $connections->method('getDeviceTokenByToken')->willReturn('devtok');
        $connections->expects($this->once())->method('revoke')->with('reqtok');

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->once())->method('untrust')->with(3, 'devtok');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'connections', $connections);
        $this->inject($ctrl, 'trustedDevices', $devices);

        $ctrl->confirmSecurityAction(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // denySecurityAction() (legacy)
    // ----------------------------------------------------------------

    /**
     * denySecurityAction() token vide → vue success=false.
     */
    public function testDenySecurityActionEmptyTokenRendersNoSuccess(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->denySecurityAction(['lang' => 'fr']);

        $this->assertSame('account/security-deny', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * denySecurityAction() token inconnu → vue expired=true.
     */
    public function testDenySecurityActionUnknownTokenRendersExpired(): void
    {
        $_GET = ['token' => 'badtoken'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findBySecurityConfirmToken')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->denySecurityAction(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['expired'] ?? false);
    }

    /**
     * denySecurityAction() token valide → clearPendingPassword + revokeAllSessions + vue success.
     */
    public function testDenySecurityActionValidClearsAndRendersSuccess(): void
    {
        $_GET = ['token' => 'goodtoken'];

        $account = ['id' => 5, 'email' => 'u@example.com', 'lang' => 'fr'];
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findBySecurityConfirmToken')->willReturn($account);
        $accounts->expects($this->once())->method('clearPendingPassword')->with(5);
        $accounts->expects($this->once())->method('revokeAllSessions')->with(5);
        $accounts->expects($this->once())->method('consumeSecurityConfirmToken')->with(5);

        $devices = $this->createMock(TrustedDeviceModel::class);
        $devices->expects($this->once())->method('deleteAllForUser')->with(5);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'trustedDevices', $devices);

        $ctrl->denySecurityAction(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // reactivateAccount() (legacy)
    // ----------------------------------------------------------------

    /**
     * reactivateAccount() token vide → vue success=false.
     */
    public function testReactivateAccountEmptyTokenRendersFailure(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->reactivateAccount(['lang' => 'fr']);

        $this->assertSame('account/reactivate', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * reactivateAccount() token inconnu → vue success=false.
     */
    public function testReactivateAccountUnknownTokenRendersFailure(): void
    {
        $_GET = ['token' => 'badtoken'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByReactivationToken')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->reactivateAccount(['lang' => 'fr']);

        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * reactivateAccount() token valide → reactivate + vue success=true.
     */
    public function testReactivateAccountValidTokenSucceeds(): void
    {
        $_GET = ['token' => 'goodtoken'];

        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findByReactivationToken')->willReturn(['id' => 7]);
        $accounts->expects($this->once())->method('reactivate')->with(7);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->reactivateAccount(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // unsubscribePage()
    // ----------------------------------------------------------------

    /**
     * unsubscribePage() token vide → vue confirm=false.
     */
    public function testUnsubscribePageEmptyTokenRendersNoConfirm(): void
    {
        $_GET = ['token' => ''];

        $ctrl = $this->makeController();
        $ctrl->unsubscribePage(['lang' => 'fr']);

        $this->assertSame('account/unsubscribe', $ctrl->lastView);
        $this->assertFalse($ctrl->lastViewData['confirm'] ?? true);
    }

    /**
     * unsubscribePage() token inconnu → vue confirm=false.
     */
    public function testUnsubscribePageUnknownTokenRendersNoConfirm(): void
    {
        $_GET = ['token' => 'unknown'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByUnsubscribeToken')->willReturn(false);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->unsubscribePage(['lang' => 'fr']);

        $this->assertFalse($ctrl->lastViewData['confirm'] ?? true);
    }

    /**
     * unsubscribePage() token valide → vue confirm=true + unsubToken + campaignId null si cid=0.
     */
    public function testUnsubscribePageValidTokenRendersConfirm(): void
    {
        $_GET = ['token' => 'validtoken', 'cid' => '0'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByUnsubscribeToken')->willReturn(['id' => 1, 'email' => 'u@example.com']);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->unsubscribePage(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['confirm'] ?? false);
        $this->assertSame('validtoken', $ctrl->lastViewData['unsubToken'] ?? null);
        $this->assertArrayHasKey('campaignId', (array) $ctrl->lastViewData);
        $this->assertNull($ctrl->lastViewData['campaignId']);
    }

    /**
     * unsubscribePage() token valide avec cid > 0 → campaignId = cid.
     */
    public function testUnsubscribePageValidTokenWithCampaignId(): void
    {
        $_GET = ['token' => 'validtoken', 'cid' => '42'];

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findByUnsubscribeToken')->willReturn(['id' => 1]);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->unsubscribePage(['lang' => 'fr']);

        $this->assertSame(42, $ctrl->lastViewData['campaignId'] ?? null);
    }

    // ----------------------------------------------------------------
    // unsubscribe()
    // ----------------------------------------------------------------

    /**
     * unsubscribe() token vide → success=false.
     */
    public function testUnsubscribeEmptyTokenRendersNoSuccess(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['unsub_token' => '', 'campaign_id' => '0', 'csrf_token' => 'test-csrf-token'];

        $ctrl = $this->makeController();
        $ctrl->unsubscribe(['lang' => 'fr']);

        $this->assertFalse($ctrl->lastViewData['success'] ?? true);
    }

    /**
     * unsubscribe() token valide → unsubscribeByToken + success=true.
     */
    public function testUnsubscribeValidTokenSucceeds(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['unsub_token' => 'tok', 'campaign_id' => '0', 'csrf_token' => 'test-csrf-token'];

        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findByUnsubscribeToken')->willReturn(['id' => 1]);
        $accounts->expects($this->once())->method('unsubscribeByToken')->with('tok');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $ctrl->unsubscribe(['lang' => 'fr']);

        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // exportPage()
    // ----------------------------------------------------------------

    /**
     * exportPage() rend la vue account/export.
     */
    public function testExportPageRendersView(): void
    {
        $account  = ['id' => 1, 'email' => 'u@example.com'];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('recall')->willReturn(null);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $ref = new \ReflectionProperty(AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $ctrl->exportPage(['lang' => 'fr']);

        $this->assertSame('account/export', $ctrl->lastView);
    }

    /**
     * exportPage() lastExportTs non null → lastExportAt formaté.
     */
    public function testExportPageWithLastExportTsFormatsDate(): void
    {
        $account  = ['id' => 1, 'email' => 'u@example.com'];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('recall')->willReturn((string) mktime(10, 30, 0, 4, 1, 2026));

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $ref = new \ReflectionProperty(AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $ctrl->exportPage(['lang' => 'fr']);

        $this->assertNotNull($ctrl->lastViewData['lastExportAt'] ?? null);
    }

    // ----------------------------------------------------------------
    // exportSend()
    // ----------------------------------------------------------------

    /**
     * exportSend() CSRF invalide → redirect.
     */
    public function testExportSendCsrfInvalidRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->exportSend(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * exportSend() rate limit dépassé → redirect.
     */
    public function testExportSendRateLimitExceededRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(false);
        $rateLimiter->method('getRetryAfter')->willReturn(3600);

        $ctrl = $this->makeController();
        $ref = new \ReflectionProperty(AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->exportSend(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * exportSend() valide → sendRgpdExport + redirect.
     */
    public function testExportSendValidSendsMailAndRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $account  = ['id' => 1, 'email' => 'u@example.com', 'firstname' => 'Jean', 'lang' => 'fr'];
        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn($account);

        $rateLimiter = $this->createMock(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);
        $rateLimiter->method('recall')->willReturn(null);
        $rateLimiter->expects($this->once())->method('recordAttempt');
        $rateLimiter->expects($this->once())->method('remember');

        $mailService = $this->createMock(\Service\MailService::class);
        $mailService->expects($this->once())->method('sendRgpdExport');

        $rgpdLogs = $this->createMock(RgpdExportLogModel::class);
        $rgpdLogs->expects($this->once())->method('log');

        $ctrl = $this->makeController();
        $ctrl->fakeMailService    = $mailService;
        $ctrl->fakeExportData    = ['profile' => []];
        $ctrl->fakeExportContent = ['{"data": true}', false];
        $this->inject($ctrl, 'accounts', $accounts);
        $ref = new \ReflectionProperty(AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);
        $refLogs = new \ReflectionProperty(AccountController::class, 'rgpdExportLogs');
        $refLogs->setValue($ctrl, $rgpdLogs);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->exportSend(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // updateLangPreference() (legacy)
    // ----------------------------------------------------------------

    /**
     * updateLangPreference() CSRF invalide → JSON 403.
     */
    public function testUpdateLangPreferenceCsrfInvalidReturns403(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $ctrl->prepareCsrf();

        ob_start();
        $ctrl->updateLangPreference(['lang' => 'fr']);
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertFalse($decoded['success'] ?? true);
        $this->assertSame('csrf', $decoded['error'] ?? null);
    }

    /**
     * updateLangPreference() lang invalide → JSON 422.
     */
    public function testUpdateLangPreferenceInvalidLangReturns422(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['lang' => 'es', 'csrf_token' => 'test-csrf-token'];

        $accounts = $this->createStub(AccountModel::class);
        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        ob_start();
        $ctrl->updateLangPreference(['lang' => 'fr']);
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertFalse($decoded['success'] ?? true);
        $this->assertSame('invalid_lang', $decoded['error'] ?? null);
    }

    /**
     * updateLangPreference() lang valide + redirect_to valide → redirect.
     */
    public function testUpdateLangPreferenceValidWithRedirectToRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['lang' => 'en', 'redirect_to' => '/en/mon-compte', 'csrf_token' => 'test-csrf-token'];

        $accounts = $this->createMock(AccountModel::class);
        $accounts->expects($this->once())->method('updateLang')->with(1, 'en');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->updateLangPreference(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * updateLangPreference() lang valide sans redirect_to → JSON success.
     */
    public function testUpdateLangPreferenceValidJsonResponse(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['lang' => 'fr', 'csrf_token' => 'test-csrf-token'];

        $accounts = $this->createMock(AccountModel::class);
        $accounts->expects($this->once())->method('updateLang')->with(1, 'fr');

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        ob_start();
        $ctrl->updateLangPreference(['lang' => 'fr']);
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);
        $this->assertTrue($decoded['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // cancelOrder() — sendReturnEmails throw → catch L340-341 (L339 = call site)
    // ----------------------------------------------------------------

    /**
     * cancelOrder() cancelForUser=false + requestReturnForUser=true + sendReturnEmails throws
     * → catch L340-341 swallows exception → redirect (no crash).
     */
    public function testCancelOrderSendReturnEmailsThrowSwallowed(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn(['id' => 1, 'account_type' => 'individual']);

        $order = [
            'id'              => 10,
            'payment_method'  => 'check',
            'status'          => 'delivered',
            'order_reference' => 'REF-THROW',
        ];

        $callCount = 0;
        $orders = $this->createMock(OrderModel::class);
        $orders->method('findDetailForUser')->willReturnCallback(
            function () use (&$callCount, $order): mixed {
                $callCount++;
                if ($callCount === 1) {
                    return $order; // Premier appel dans cancelOrder()
                }
                // Deuxième appel dans sendReturnEmails() → throw
                throw new \RuntimeException('db-unavailable-in-test');
            }
        );
        $orders->method('cancelForUser')->willReturn(false);
        $orders->method('requestReturnForUser')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '10']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // newRefundService() — retourne un RefundService (seam L409-411)
    // ----------------------------------------------------------------

    /**
     * newRefundService() retourne un RefundService (appel direct sur AccountController).
     * Couvre les lignes 409-411 de la méthode protégée (seam de testabilité).
     */
    public function testNewRefundServiceReturnsInstance(): void
    {
        $ctrl = new \Controller\AccountController(new \Core\Request());
        $ref  = new \ReflectionMethod(\Controller\AccountController::class, 'newRefundService');
        $result = $ref->invoke($ctrl);
        $this->assertInstanceOf(\Service\RefundService::class, $result);
    }

    // ----------------------------------------------------------------
    // sendPdfResponse() — headers + body → L498-504
    // ----------------------------------------------------------------

    /**
     * returnSlip() appelle sendPdfResponse() qui pose les headers et sort le PDF.
     * TestableAccountController intercepte sendPdfResponse() (L498-504) et capture le filename.
     * Note : buildReturnSlipPdf() (qui inclut TCPDF) est aussi exercé ici.
     */
    public function testReturnSlipCallsSendPdfResponse(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn([
            'id' => 1, 'account_type' => 'individual',
        ]);

        $order = [
            'id'              => 5,
            'status'          => 'return_requested',
            'order_reference' => 'PDF-REF-001',
            'content'         => json_encode([
                ['label_name' => 'Blanc sec', 'qty' => 6, 'price' => 12.5, 'vintage' => 2022],
            ]),
            'ordered_at'      => '2025-01-10 12:00:00',
            'bill_civility'   => 'M',
            'bill_firstname'  => 'Jean',
            'bill_lastname'   => 'Dupont',
            'bill_street'     => '12 rue de la Vigne',
            'bill_zip'        => '33000',
            'bill_city'       => 'Bordeaux',
            'bill_country'    => 'France',
        ];

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        try {
            $ctrl->returnSlip(['lang' => 'fr', 'id' => '5']);
        } catch (HttpException $e) {
            // TestableAccountController lance HttpException(200) depuis sendPdfResponse
            $this->assertSame(200, $e->status);
            $this->assertStringContainsString('PDF-REF-001', $ctrl->lastPdfFilename ?? '');
            return;
        }

        $this->fail('sendPdfResponse() should have thrown HttpException(200)');
    }

    // ----------------------------------------------------------------
    // returnSlip() — is_cuvee_speciale branch → L550
    // ----------------------------------------------------------------

    /**
     * returnSlip() avec un item is_cuvee_speciale → couvre L550 de buildReturnSlipPdf.
     */
    public function testReturnSlipWithCuveeSpecialeCoversBranch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $accounts = $this->createStub(AccountModel::class);
        $accounts->method('findById')->willReturn([
            'id' => 1, 'account_type' => 'individual',
        ]);

        $order = [
            'id'              => 6,
            'status'          => 'return_requested',
            'order_reference' => 'PDF-CS-002',
            'content'         => json_encode([
                [
                    'label_name'       => 'Cuvée Or',
                    'qty'              => 3,
                    'price'            => 25.0,
                    'vintage'          => 2021,
                    'is_cuvee_speciale' => true,  // ← couvre L550
                ],
            ]),
            'ordered_at'      => '2025-03-01 10:00:00',
            'bill_civility'   => 'Mme',
            'bill_firstname'  => 'Marie',
            'bill_lastname'   => 'Martin',
            'bill_street'     => '5 av. des Vins',
            'bill_zip'        => '33000',
            'bill_city'       => 'Bordeaux',
            'bill_country'    => 'France',
        ];

        $orders = $this->createStub(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        try {
            $ctrl->returnSlip(['lang' => 'fr', 'id' => '6']);
        } catch (HttpException $e) {
            $this->assertSame(200, $e->status);
            $this->assertStringContainsString('PDF-CS-002', $ctrl->lastPdfFilename ?? '');
            return;
        }

        $this->fail('sendPdfResponse() should have thrown HttpException(200)');
    }

    // ----------------------------------------------------------------
    // changePassword() — MailService throw → catch L963-964
    // ----------------------------------------------------------------

    /**
     * changePassword() valide + MailService throw dans sendPasswordChangeConfirmation
     * → catch L963-964 swallows → flash security_success + redirect (L968-969).
     */
    public function testChangePasswordMailThrowCoveredByCatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'current_password'     => 'OldStr0ng!Pass',
            'new_password'         => 'NewStr0ng!Pass1',
            'new_password_confirm' => 'NewStr0ng!Pass1',
            'csrf_token'           => 'test-csrf-token',
        ];

        $account = [
            'id'        => 1,
            'email'     => 'u@example.com',
            'firstname' => 'Jean',
            'lastname'  => 'Dupont',
            'lang'      => 'fr',
            'password'  => password_hash('OldStr0ng!Pass', PASSWORD_BCRYPT),
        ];
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findById')->willReturn($account);
        $accounts->method('savePendingPassword');
        $accounts->method('saveSecurityConfirmToken');

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        $mailMock = $this->createMock(\Service\MailService::class);
        $mailMock->method('sendPasswordChangeConfirmation')
            ->willThrowException(new \RuntimeException('smtp-unavailable'));

        $ctrl = $this->makeController();
        $ctrl->fakeMailService = $mailMock;
        $this->inject($ctrl, 'accounts', $accounts);
        $ref = new \ReflectionProperty(\Controller\AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // requestEmailChange() — AccountService throw \Throwable → catch L1297-1300
    // ----------------------------------------------------------------

    /**
     * requestEmailChange() + AccountService lance \RuntimeException (pas HttpException)
     * → catch L1297-1300 → flash profile_errors + redirect.
     *
     * Stratégie : accounts->findById() lève RuntimeException dans AccountService,
     * ce qui est capturé par le catch \Throwable (L1297) dans AccountController.
     */
    public function testRequestEmailChangeGenericThrowableFlashesError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'new_email'        => 'newemail@example.com',
            'current_password' => 'AnyPass',
            'csrf_token'       => 'test-csrf-token',
        ];

        // accounts->findById() lance RuntimeException (L65 de AccountService::requestEmailChange)
        // → ce n'est pas un HttpException → capturé L1297 dans AccountController
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findById')
            ->willThrowException(new \RuntimeException('unexpected-db-error'));

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->requestEmailChange(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // deleteAccount() — MailService throw → catch L1494-1495
    // ----------------------------------------------------------------

    /**
     * deleteAccount() valid + MailService throw dans sendAccountDeletionConfirmation
     * → catch L1494-1495 swallows → redirect quand même.
     */
    public function testDeleteAccountMailThrowCoveredByCatch(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'confirm_text'     => 'SUPPRESSION',
            'confirm_password' => 'ValidPass1!',
            'csrf_token'       => 'test-csrf-token',
        ];

        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findById')->willReturn([
            'id'           => 1,
            'email'        => 'u@example.com',
            'password'     => password_hash('ValidPass1!', PASSWORD_BCRYPT),
            'firstname'    => 'Jean',
            'lang'         => 'fr',
            'company_name' => null,
            'account_type' => 'individual',
        ]);
        $accounts->method('revokeAllSessions');
        $accounts->method('delete');
        $accounts->method('getReactivationToken')->willReturn('react-tok-123');

        $mailMock = $this->createMock(\Service\MailService::class);
        $mailMock->method('sendAccountDeletionConfirmation')
            ->willThrowException(new \RuntimeException('smtp-unavailable'));

        $ctrl = $this->makeController();
        $ctrl->fakeMailService = $mailMock;
        $this->inject($ctrl, 'accounts', $accounts);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->deleteAccount(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // denySecurityAction() — MailService throw → catch L1595-1596
    // ----------------------------------------------------------------

    /**
     * denySecurityAction() token valide + MailService throw dans sendPasswordChangeDenied
     * → catch L1595-1596 swallows → vue success=true quand même.
     */
    public function testDenySecurityActionMailThrowCoveredByCatch(): void
    {
        $_GET = ['token' => 'denytoken1595'];

        $account = ['id' => 7, 'email' => 'deny@example.com', 'lang' => 'fr'];
        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findBySecurityConfirmToken')->willReturn($account);
        $accounts->method('clearPendingPassword');
        $accounts->method('revokeAllSessions');
        $accounts->method('consumeSecurityConfirmToken');

        $devices = $this->createMock(\Model\TrustedDeviceModel::class);
        $devices->method('deleteAllForUser');

        $mailMock = $this->createMock(\Service\MailService::class);
        $mailMock->method('sendPasswordChangeDenied')
            ->willThrowException(new \RuntimeException('smtp-unavailable'));

        $ctrl = $this->makeController();
        $ctrl->fakeMailService = $mailMock;
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'trustedDevices', $devices);

        $ctrl->denySecurityAction(['lang' => 'fr']);

        // MailService throw → catch L1595-1596 → vue rendue quand même
        $this->assertSame('account/security-deny', $ctrl->lastView);
        $this->assertTrue($ctrl->lastViewData['success'] ?? false);
    }

    // ----------------------------------------------------------------
    // sendReturnEmails() — early return !account → L426
    // ----------------------------------------------------------------

    /**
     * sendReturnEmails() appelé via cancelOrder() quand accounts->findById retourne null
     * → early return L426 (pas d'envoi d'email).
     * Flash order_return_requested doit quand même être posé.
     */
    public function testSendReturnEmailsAccountNullHitsEarlyReturn(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $callCount = 0;
        $accounts  = $this->createMock(AccountModel::class);
        // Premier appel : requireIndividual (findById → individual)
        // Deuxième appel (dans sendReturnEmails) : retourne null → early return L426
        $accounts->method('findById')->willReturnCallback(
            function () use (&$callCount): array|false {
                $callCount++;
                if ($callCount === 1) {
                    return ['id' => 1, 'account_type' => 'individual'];
                }
                return false;
            }
        );

        $order = [
            'id'              => 20,
            'payment_method'  => 'check',
            'status'          => 'delivered',
            'order_reference' => 'REF-ACCT-NULL',
        ];

        $orders = $this->createMock(OrderModel::class);
        $orders->method('findDetailForUser')->willReturn($order);
        $orders->method('cancelForUser')->willReturn(false);
        $orders->method('requestReturnForUser')->willReturn(true);

        $ctrl = $this->makeController();
        $this->inject($ctrl, 'accounts', $accounts);
        $this->inject($ctrl, 'orders', $orders);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '20']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // sendPdfResponse() — corps réel (headers + echo + doExit) → L498-504
    // ----------------------------------------------------------------

    /**
     * sendPdfResponse() réel : vérifie que le PDF est écrit dans l'output
     * et que doExit() est appelé.
     *
     * Utilise une sous-classe anonyme qui surcharge uniquement doExit()
     * pour lever une exception au lieu d'appeler exit — les lignes 498-504
     * sont ainsi toutes exécutées.
     */
    public function testSendPdfResponseRealBodyEchoesPdfAndCallsDoExit(): void
    {
        $ctrl = new class (new \Core\Request()) extends \Controller\AccountController {
            /** @var string Contenu capturé par echo. */
            public string $echoed = '';

            protected function doExit(): never
            {
                throw new \RuntimeException('doExit-called');
            }
        };

        $ref = new \ReflectionMethod(\Controller\AccountController::class, 'sendPdfResponse');

        ob_start();
        $caught = null;
        try {
            $ref->invoke($ctrl, '%PDF-test-content', 'test.pdf');
        } catch (\RuntimeException $e) {
            $caught = $e;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertNotNull($caught, 'doExit() doit être appelé');
        $this->assertSame('doExit-called', $caught->getMessage());
        $this->assertStringContainsString('%PDF-test-content', (string) $output);
    }

    // ----------------------------------------------------------------
    // changePassword() — company_name fallback → L952
    // ----------------------------------------------------------------

    /**
     * changePassword() avec account dont firstname et lastname sont vides
     * → $notifName utilise company_name (L952 couverte).
     */
    public function testChangePasswordCompanyNameFallbackUsed(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'current_password'     => 'OldStr0ng!Pass',
            'new_password'         => 'NewStr0ng!Pass1',
            'new_password_confirm' => 'NewStr0ng!Pass1',
            'csrf_token'           => 'test-csrf-token',
        ];

        $account = [
            'id'           => 1,
            'email'        => 'company@example.com',
            'firstname'    => '',    // vide → fallback company_name
            'lastname'     => '',    // vide → fallback company_name
            'company_name' => 'SARL Vins du Soleil',
            'lang'         => 'fr',
            'password'     => password_hash('OldStr0ng!Pass', PASSWORD_BCRYPT),
        ];

        $accounts = $this->createMock(AccountModel::class);
        $accounts->method('findById')->willReturn($account);
        $accounts->method('savePendingPassword');
        $accounts->method('saveSecurityConfirmToken');

        $rateLimiter = $this->createStub(RateLimiterService::class);
        $rateLimiter->method('checkLimit')->willReturn(true);

        // MailService lance une exception : l'important est que le catch L963
        // soit atteint, prouvant que la branche company_name a été exécutée.
        $mailMock = $this->createMock(\Service\MailService::class);
        $mailMock->method('sendPasswordChangeConfirmation')
            ->willThrowException(new \RuntimeException('smtp-down'));

        $ctrl = $this->makeController();
        $ctrl->fakeMailService = $mailMock;
        $this->inject($ctrl, 'accounts', $accounts);
        $ref = new \ReflectionProperty(\Controller\AccountController::class, 'rateLimiter');
        $ref->setValue($ctrl, $rateLimiter);

        $this->expectException(HttpException::class);

        ob_start();
        try {
            $ctrl->changePassword(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }
}
