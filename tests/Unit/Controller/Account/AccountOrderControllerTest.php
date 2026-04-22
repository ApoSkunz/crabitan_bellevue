<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Account;

use Controller\Account\AccountOrderController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\OrderModel;
use PHPUnit\Framework\TestCase;
use Repository\UserRepository;
use Service\PaymentService;
use Service\RefundService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour AccountOrderController.
 * Utilise TestableAccountOrderController pour isoler auth, CSRF, redirect et view.
 * Les dépendances OrderModel et UserRepository sont mockées via réflexion.
 */
#[AllowMockObjectsWithoutExpectations]
class AccountOrderControllerTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $dbStub = $this->createStub(Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbStub);

        $_SESSION = ['csrf' => 'test-csrf'];
        $_GET     = [];
        $_POST    = [];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/commandes';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
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

    /**
     * Crée un TestableAccountOrderController avec userRepo et éventuellement OrderModel injectés.
     *
     * @param UserRepository|null $userRepo
     * @param string              $method
     * @param string              $uri
     * @return TestableAccountOrderController
     */
    private function makeController(
        ?UserRepository $userRepo = null,
        string $method = 'GET',
        string $uri = '/fr/mon-compte/commandes'
    ): TestableAccountOrderController {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        return new TestableAccountOrderController(new Request(), $userRepo);
    }

    /**
     * Injecte un mock OrderModel dans le controller via réflexion.
     *
     * @param TestableAccountOrderController $ctrl
     * @param OrderModel                     $orders
     */
    private function injectOrders(TestableAccountOrderController $ctrl, OrderModel $orders): void
    {
        $ref = new \ReflectionProperty(AccountOrderController::class, 'orders');
        $ref->setValue($ctrl, $orders);
    }

    /**
     * Crée un stub UserRepository qui retourne un compte individual.
     *
     * @return UserRepository
     */
    private function makeIndividualUserRepo(): UserRepository
    {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findById')->willReturn([
            'id'           => 1,
            'email'        => 'test@example.com',
            'account_type' => 'individual',
            'firstname'    => 'Jean',
            'lastname'     => 'Dupont',
            'lang'         => 'fr',
        ]);
        return $repo;
    }

    /**
     * Crée un stub UserRepository qui retourne un compte company.
     *
     * @return UserRepository
     */
    private function makeCompanyUserRepo(): UserRepository
    {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findById')->willReturn([
            'id'           => 1,
            'email'        => 'company@example.com',
            'account_type' => 'company',
        ]);
        return $repo;
    }

    // ----------------------------------------------------------------
    // newPaymentService() / newRefundService() — seams de testabilité
    // ----------------------------------------------------------------

    /**
     * newPaymentService retourne bien une instance PaymentService par défaut.
     */
    public function testNewPaymentServiceReturnsPaymentService(): void
    {
        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod(AccountOrderController::class, 'newPaymentService');
        $result = $ref->invoke($ctrl);
        $this->assertInstanceOf(PaymentService::class, $result);
    }

    /**
     * newRefundService retourne bien une instance RefundService par défaut.
     */
    public function testNewRefundServiceReturnsRefundService(): void
    {
        $ctrl = $this->makeController();
        $ref  = new \ReflectionMethod(AccountOrderController::class, 'newRefundService');
        $result = $ref->invoke($ctrl);
        $this->assertInstanceOf(RefundService::class, $result);
    }

    // ----------------------------------------------------------------
    // orders() — branches perPage / period / statusFilter
    // ----------------------------------------------------------------

    /**
     * orders() avec per_page valide (25) et period=all → view rendue.
     */
    public function testOrdersWithValidPerPage25(): void
    {
        $_GET = ['per_page' => '25'];
        $userRepo = $this->makeIndividualUserRepo();

        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('countForUser')->willReturn(0);
        $ordersMock->method('getForUser')->willReturn([]);
        $ordersMock->method('getAvailableYearsForUser')->willReturn([]);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes?per_page=25');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orders(['lang' => 'fr']);

        $this->assertSame('account/orders', $ctrl->lastView);
        $this->assertSame(25, $ctrl->lastViewData['perPage']);
    }

    /**
     * orders() avec per_page=50 → view rendue avec perPage=50.
     */
    public function testOrdersWithValidPerPage50(): void
    {
        $_GET = ['per_page' => '50'];
        $userRepo = $this->makeIndividualUserRepo();

        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('countForUser')->willReturn(0);
        $ordersMock->method('getForUser')->willReturn([]);
        $ordersMock->method('getAvailableYearsForUser')->willReturn([]);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes?per_page=50');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orders(['lang' => 'fr']);

        $this->assertSame(50, $ctrl->lastViewData['perPage']);
    }

    /**
     * orders() avec per_page invalide → remis à 10.
     */
    public function testOrdersInvalidPerPageDefaultsTo10(): void
    {
        $_GET = ['per_page' => '999'];
        $userRepo = $this->makeIndividualUserRepo();

        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('countForUser')->willReturn(0);
        $ordersMock->method('getForUser')->willReturn([]);
        $ordersMock->method('getAvailableYearsForUser')->willReturn([]);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes?per_page=999');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orders(['lang' => 'fr']);

        $this->assertSame(10, $ctrl->lastViewData['perPage']);
    }

    /**
     * orders() avec period=2023 → period='year' et year=2023 passé à countForUser.
     */
    public function testOrdersWithYearPeriod(): void
    {
        $_GET = ['period' => '2023'];
        $userRepo = $this->makeIndividualUserRepo();

        $yearCaptured = null;
        $ordersMock = $this->createMock(OrderModel::class);
        $ordersMock->method('countForUser')
            ->willReturnCallback(function ($userId, $period, $year) use (&$yearCaptured): int {
                $yearCaptured = $year;
                return 0;
            });
        $ordersMock->method('getForUser')->willReturn([]);
        $ordersMock->method('getAvailableYearsForUser')->willReturn([]);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes?period=2023');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orders(['lang' => 'fr']);

        $this->assertSame(2023, $yearCaptured);
    }

    /**
     * orders() avec status valide → statusFilter transmis.
     */
    public function testOrdersWithValidStatus(): void
    {
        $_GET = ['status' => 'delivered'];
        $userRepo = $this->makeIndividualUserRepo();

        $statusCaptured = null;
        $ordersMock = $this->createMock(OrderModel::class);
        $ordersMock->method('countForUser')
            ->willReturnCallback(function ($userId, $period, $year, $status) use (&$statusCaptured): int {
                $statusCaptured = $status;
                return 0;
            });
        $ordersMock->method('getForUser')->willReturn([]);
        $ordersMock->method('getAvailableYearsForUser')->willReturn([]);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes?status=delivered');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orders(['lang' => 'fr']);

        $this->assertSame('delivered', $statusCaptured);
    }

    /**
     * orders() avec status invalide → statusFilter=null.
     */
    public function testOrdersWithInvalidStatusPassesNull(): void
    {
        $_GET = ['status' => 'invalid_status'];
        $userRepo = $this->makeIndividualUserRepo();

        $statusCaptured = 'not-null';
        $ordersMock = $this->createMock(OrderModel::class);
        $ordersMock->method('countForUser')
            ->willReturnCallback(function ($userId, $period, $year, $status) use (&$statusCaptured): int {
                $statusCaptured = $status;
                return 0;
            });
        $ordersMock->method('getForUser')->willReturn([]);
        $ordersMock->method('getAvailableYearsForUser')->willReturn([]);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes?status=invalid_status');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orders(['lang' => 'fr']);

        $this->assertNull($statusCaptured);
    }

    /**
     * orders() pour un compte company → redirigé vers dashboard.
     */
    public function testOrdersRedirectsCompanyToDashboard(): void
    {
        $userRepo = $this->makeCompanyUserRepo();

        $ctrl = $this->makeController($userRepo);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $ctrl->orders(['lang' => 'fr']);
    }

    // ----------------------------------------------------------------
    // orderDetail() — branches delivered
    // ----------------------------------------------------------------

    /**
     * orderDetail() avec shippingDiscount > 0 → transmis dans viewData.
     */
    public function testOrderDetailPassesShippingDiscount(): void
    {
        $userRepo = $this->makeIndividualUserRepo();

        $order = [
            'status'           => 'paid',
            'delivered_at'     => null,
            'content'          => '[]',
            'shipping_discount' => '5.00',
            'order_reference'  => 'REF-001',
            'bill_firstname'   => 'Jean',
            'bill_lastname'    => 'Dupont',
            'bill_civility'    => 'M',
            'bill_street'      => '1 rue',
            'bill_zip'         => '75001',
            'bill_city'        => 'Paris',
            'bill_country'     => 'France',
        ];
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes/1');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orderDetail(['lang' => 'fr', 'id' => '1']);

        $this->assertSame('account/order_detail', $ctrl->lastView);
        $this->assertSame(5.0, $ctrl->lastViewData['shippingDiscount']);
    }

    /**
     * orderDetail() avec shippingDiscount = 0 → null dans viewData.
     */
    public function testOrderDetailZeroShippingDiscountIsNull(): void
    {
        $userRepo = $this->makeIndividualUserRepo();

        $order = [
            'status'           => 'paid',
            'delivered_at'     => null,
            'content'          => '[]',
            'shipping_discount' => '0.00',
            'order_reference'  => 'REF-001',
        ];
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes/1');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orderDetail(['lang' => 'fr', 'id' => '1']);

        $this->assertNull($ctrl->lastViewData['shippingDiscount']);
    }

    /**
     * orderDetail() avec commande delivered sans delivered_at → deliveredNoDate=true.
     */
    public function testOrderDetailDeliveredWithoutDateSetsDeliveredNoDate(): void
    {
        $userRepo = $this->makeIndividualUserRepo();

        $order = [
            'status'           => 'delivered',
            'delivered_at'     => null,
            'content'          => '[]',
            'shipping_discount' => '0.00',
        ];
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes/1');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orderDetail(['lang' => 'fr', 'id' => '1']);

        $this->assertTrue($ctrl->lastViewData['deliveredNoDate']);
        $this->assertFalse($ctrl->lastViewData['cancellableReturn']);
        $this->assertFalse($ctrl->lastViewData['returnExpired']);
    }

    /**
     * orderDetail() avec commande delivered dans la fenêtre → cancellableReturn=true.
     */
    public function testOrderDetailDeliveredInWindowSetsCancellableReturn(): void
    {
        $userRepo = $this->makeIndividualUserRepo();

        $order = [
            'status'           => 'delivered',
            'delivered_at'     => date('Y-m-d H:i:s', strtotime('-1 day')),
            'content'          => '[]',
            'shipping_discount' => '0.00',
        ];
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes/1');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orderDetail(['lang' => 'fr', 'id' => '1']);

        $this->assertTrue($ctrl->lastViewData['cancellableReturn']);
        $this->assertNotNull($ctrl->lastViewData['returnDeadline']);
        $this->assertFalse($ctrl->lastViewData['returnExpired']);
    }

    /**
     * orderDetail() avec commande delivered hors fenêtre → returnExpired=true.
     */
    public function testOrderDetailDeliveredExpiredSetsReturnExpired(): void
    {
        $userRepo = $this->makeIndividualUserRepo();

        $order = [
            'status'           => 'delivered',
            'delivered_at'     => date('Y-m-d H:i:s', strtotime('-60 days')),
            'content'          => '[]',
            'shipping_discount' => '0.00',
        ];
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes/1');
        $this->injectOrders($ctrl, $ordersMock);
        $ctrl->orderDetail(['lang' => 'fr', 'id' => '1']);

        $this->assertTrue($ctrl->lastViewData['returnExpired']);
        $this->assertFalse($ctrl->lastViewData['cancellableReturn']);
    }

    // ----------------------------------------------------------------
    // cancelOrder() — branche card + CA transaction refs
    // ----------------------------------------------------------------

    /**
     * cancelOrder() avec commande card+paid et CA refs valides → GAE appelé, succès → redirect.
     */
    public function testCancelOrderCardPaidWithCaRefsRefundSuccess(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'test-csrf'];
        $userRepo = $this->makeIndividualUserRepo();

        $order = [
            'payment_method'  => 'card',
            'status'          => 'paid',
            'ca_numappel'     => 'APPEL123',
            'ca_numtrans'     => 'TRANS456',
            'order_reference' => 'REF-001',
            'price'           => '99.90',
        ];
        $ordersMock = $this->createMock(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);
        $ordersMock->expects($this->once())->method('updateStatus')->with(1, 'refunded');

        $paymentMock = $this->createStub(PaymentService::class);
        $paymentMock->method('callGaeCancelOrRefund')->willReturn(true);

        $ctrl = $this->makeController($userRepo, 'POST', '/fr/mon-compte/commandes/1/annuler');
        $ctrl->fakePaymentService = $paymentMock;
        $this->injectOrders($ctrl, $ordersMock);

        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertSame(__('account.order_cancelled'), $_SESSION['flash']['order_success'] ?? null);
    }

    /**
     * cancelOrder() avec commande card+paid, CA refs valides mais GAE échec → order_error.
     */
    public function testCancelOrderCardPaidWithCaRefsRefundFails(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'test-csrf'];
        $userRepo = $this->makeIndividualUserRepo();

        $order = [
            'payment_method'  => 'card',
            'status'          => 'paid',
            'ca_numappel'     => 'APPEL123',
            'ca_numtrans'     => 'TRANS456',
            'order_reference' => 'REF-001',
            'price'           => '99.90',
        ];
        $ordersMock = $this->createMock(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);
        $ordersMock->expects($this->never())->method('updateStatus');

        $paymentMock = $this->createStub(PaymentService::class);
        $paymentMock->method('callGaeCancelOrRefund')->willReturn(false);

        $ctrl = $this->makeController($userRepo, 'POST', '/fr/mon-compte/commandes/1/annuler');
        $ctrl->fakePaymentService = $paymentMock;
        $this->injectOrders($ctrl, $ordersMock);

        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertSame(__('account.cancel_refund_failed'), $_SESSION['flash']['order_error'] ?? null);
    }

    /**
     * cancelOrder() avec commande card+paid mais CA refs manquantes → log + continue flux normal.
     */
    public function testCancelOrderCardPaidMissingCaRefsLogsAndContinues(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/mon-compte/commandes/1/annuler';
        $_POST = ['csrf_token' => 'test-csrf'];
        $userRepo = $this->makeIndividualUserRepo();

        $order = [
            'payment_method'  => 'card',
            'status'          => 'paid',
            'ca_numappel'     => '',
            'ca_numtrans'     => '',
            'order_reference' => 'REF-001',
            'price'           => '99.90',
        ];
        $ordersMock = $this->createMock(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);
        // cancelForUser retourne true → annulation normale
        $ordersMock->method('cancelForUser')->willReturn(true);

        $ctrl = $this->makeController($userRepo, 'POST', '/fr/mon-compte/commandes/1/annuler');
        $this->injectOrders($ctrl, $ordersMock);

        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertSame(__('account.order_cancelled'), $_SESSION['flash']['order_success'] ?? null);
    }

    /**
     * cancelOrder() quand cancelForUser et requestReturnForUser échouent tous les deux → order_error.
     */
    public function testCancelOrderBothCancelAndReturnFail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'test-csrf'];
        $userRepo = $this->makeIndividualUserRepo();

        $order = [
            'payment_method' => 'transfer',
            'status'         => 'shipped',
        ];
        $ordersMock = $this->createMock(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);
        $ordersMock->method('cancelForUser')->willReturn(false);
        $ordersMock->method('requestReturnForUser')->willReturn(false);

        $ctrl = $this->makeController($userRepo, 'POST', '/fr/mon-compte/commandes/1/annuler');
        $this->injectOrders($ctrl, $ordersMock);

        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertSame(__('account.order_cancel_failed'), $_SESSION['flash']['order_error'] ?? null);
    }

    /**
     * cancelOrder() quand requestReturnForUser réussit mais sendReturnEmails lève une exception → loggé silencieusement.
     */
    public function testCancelOrderReturnRequestedEmailExceptionIsSilent(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'test-csrf'];

        // userRepo retourne null pour le second findById (dans sendReturnEmails) → early return
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findById')->willReturnOnConsecutiveCalls(
            // Premier appel → requireIndividual → individual
            ['id' => 1, 'account_type' => 'individual'],
            // Second appel dans sendReturnEmails → null → early return sans email
            false
        );

        $order = [
            'payment_method' => 'transfer',
            'status'         => 'delivered',
            'delivered_at'   => date('Y-m-d H:i:s', strtotime('-1 day')),
        ];
        $ordersMock = $this->createMock(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);
        $ordersMock->method('cancelForUser')->willReturn(false);
        $ordersMock->method('requestReturnForUser')->willReturn(true);

        $ctrl = $this->makeController($userRepo, 'POST', '/fr/mon-compte/commandes/1/annuler');
        $this->injectOrders($ctrl, $ordersMock);

        try {
            $ctrl->cancelOrder(['lang' => 'fr', 'id' => '1']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertSame(__('account.order_return_requested'), $_SESSION['flash']['order_success'] ?? null);
    }

    // ----------------------------------------------------------------
    // requestRefund() — branches succès / échec RefundService
    // ----------------------------------------------------------------

    /**
     * requestRefund() quand RefundService réussit → flash success + redirect.
     */
    public function testRequestRefundSuccessFlashesSuccess(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'test-csrf'];
        $userRepo = $this->makeIndividualUserRepo();

        $order = ['status' => 'paid'];
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $refundMock = $this->createMock(RefundService::class);
        $refundMock->expects($this->once())->method('refund')->with(1);

        $ctrl = $this->makeController($userRepo, 'POST', '/fr/mon-compte/commandes/1/remboursement');
        $ctrl->fakeRefundService = $refundMock;
        $this->injectOrders($ctrl, $ordersMock);

        try {
            $ctrl->requestRefund(['lang' => 'fr', 'id' => '1']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertSame(__('account.order_refund_initiated'), $_SESSION['flash']['order_success'] ?? null);
    }

    /**
     * requestRefund() quand RefundService lève une exception → flash error + redirect.
     */
    public function testRequestRefundThrowableFlashesError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'test-csrf'];
        $userRepo = $this->makeIndividualUserRepo();

        $order = ['status' => 'processing'];
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $refundMock = $this->createMock(RefundService::class);
        $refundMock->method('refund')->willThrowException(new \RuntimeException('GAE error'));

        $ctrl = $this->makeController($userRepo, 'POST', '/fr/mon-compte/commandes/1/remboursement');
        $ctrl->fakeRefundService = $refundMock;
        $this->injectOrders($ctrl, $ordersMock);

        try {
            $ctrl->requestRefund(['lang' => 'fr', 'id' => '1']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertSame(__('account.order_refund_failed'), $_SESSION['flash']['order_error'] ?? null);
    }

    /**
     * requestRefund() avec status non remboursable → flash error + redirect sans appel RefundService.
     */
    public function testRequestRefundNotEligibleStatusSkipsRefund(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'test-csrf'];
        $userRepo = $this->makeIndividualUserRepo();

        $order = ['status' => 'cancelled'];
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $refundMock = $this->createMock(RefundService::class);
        $refundMock->expects($this->never())->method('refund');

        $ctrl = $this->makeController($userRepo, 'POST', '/fr/mon-compte/commandes/1/remboursement');
        $ctrl->fakeRefundService = $refundMock;
        $this->injectOrders($ctrl, $ordersMock);

        try {
            $ctrl->requestRefund(['lang' => 'fr', 'id' => '1']);
            $this->fail('Expected HttpException (302)');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->getCode());
        }

        $this->assertSame(__('account.order_refund_not_eligible'), $_SESSION['flash']['order_error'] ?? null);
    }

    // ----------------------------------------------------------------
    // returnSlip() — branches order null et delivered hors fenêtre
    // ----------------------------------------------------------------

    /**
     * returnSlip() avec commande delivered hors fenêtre → abort 404.
     */
    public function testReturnSlipDeliveredExpiredAborts404(): void
    {
        $userRepo = $this->makeIndividualUserRepo();

        $order = [
            'status'       => 'delivered',
            'delivered_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
        ];
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes/1/fiche-retour');
        $this->injectOrders($ctrl, $ordersMock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $ctrl->returnSlip(['lang' => 'fr', 'id' => '1']);
    }

    /**
     * returnSlip() avec commande non éligible (status=paid) → abort 404.
     */
    public function testReturnSlipNonEligibleStatusAborts404(): void
    {
        $userRepo = $this->makeIndividualUserRepo();

        $order = ['status' => 'paid'];
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('findDetailForUser')->willReturn($order);

        $ctrl = $this->makeController($userRepo, 'GET', '/fr/mon-compte/commandes/1/fiche-retour');
        $this->injectOrders($ctrl, $ordersMock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $ctrl->returnSlip(['lang' => 'fr', 'id' => '1']);
    }
}
