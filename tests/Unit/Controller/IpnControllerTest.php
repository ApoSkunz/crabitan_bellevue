<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\IpnController;
use Core\Exception\HttpException;
use Model\CartModel;
use Model\OrderModel;
use PHPUnit\Framework\TestCase;
use Service\MailService;
use Service\PaymentService;

/**
 * Tests unitaires pour IpnController.
 *
 * Couvre : vérification de signature, idempotence sur référence dupliquée,
 * paiement refusé, création de commande en cas de succès, mismatch de référence.
 */
class IpnControllerTest extends TestCase
{
    // ================================================================
    // Helpers
    // ================================================================

    private function bootstrapApp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';

        $_ENV['JWT_SECRET'] = 'test-secret-key-minimum-32-chars!!';
    }

    /**
     * Crée un IpnController sans appeler le constructeur réel,
     * puis injecte les mocks dans ses propriétés privées via ReflectionProperty.
     *
     * @param PaymentService&\PHPUnit\Framework\MockObject\MockObject $paymentMock
     * @param OrderModel&\PHPUnit\Framework\MockObject\MockObject     $orderMock
     * @param CartModel&\PHPUnit\Framework\MockObject\MockObject      $cartMock
     * @param MailService&\PHPUnit\Framework\MockObject\MockObject    $mailMock
     * @return IpnController
     */
    private function makeController(
        \PHPUnit\Framework\MockObject\MockObject $paymentMock,
        \PHPUnit\Framework\MockObject\MockObject $orderMock,
        \PHPUnit\Framework\MockObject\MockObject $cartMock,
        \PHPUnit\Framework\MockObject\MockObject $mailMock
    ): IpnController {
        $ref  = new \ReflectionClass(IpnController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();

        $ref->getProperty('payment')->setValue($ctrl, $paymentMock);
        $ref->getProperty('orders')->setValue($ctrl, $orderMock);
        $ref->getProperty('carts')->setValue($ctrl, $cartMock);
        $ref->getProperty('mail')->setValue($ctrl, $mailMock);

        return $ctrl;
    }

    /**
     * Construit un IpnController avec tous les mocks PHPUnit standard.
     *
     * @return array{
     *   0: IpnController,
     *   1: PaymentService&\PHPUnit\Framework\MockObject\MockObject,
     *   2: OrderModel&\PHPUnit\Framework\MockObject\MockObject,
     *   3: CartModel&\PHPUnit\Framework\MockObject\MockObject,
     *   4: MailService&\PHPUnit\Framework\MockObject\MockObject
     * }
     */
    private function buildController(): array
    {
        $paymentMock = $this->createMock(PaymentService::class);
        $orderMock   = $this->createMock(OrderModel::class);
        $cartMock    = $this->createMock(CartModel::class);
        $mailMock    = $this->createMock(MailService::class);

        $ctrl = $this->makeController($paymentMock, $orderMock, $cartMock, $mailMock);

        return [$ctrl, $paymentMock, $orderMock, $cartMock, $mailMock];
    }

    private function defaultSession(string $reference = 'WEB-CB-AABBCCDD-2026'): void
    {
        $_SESSION['ca_payment'] = [
            'reference'           => $reference,
            'user_id'             => 42,
            'items'               => [['wine_id' => 1, 'qty' => 2, 'price' => 15.0]],
            'total'               => 30.0,
            'delivery_discount'   => 0.0,
            'billing_address_id'  => 1,
            'delivery_address_id' => 1,
            'cgv_version'         => '1.0',
            'lang'                => 'fr',
            'newsletter'          => false,
            'client_email'        => 'client@example.com',
            'client_name'         => 'Jean Dupont',
        ];
    }

    protected function setUp(): void
    {
        $this->bootstrapApp();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SERVER['QUERY_STRING'] = 'Erreur=00000&Ref=WEB-CB-AABBCCDD-2026&Appel=1234&Trans=5678';
        $_GET = [
            'Erreur' => '00000',
            'Ref'    => 'WEB-CB-AABBCCDD-2026',
            'Appel'  => '1234',
            'Trans'  => '5678',
        ];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_GET     = [];
        $_SESSION = [];
        $_SERVER['QUERY_STRING'] = '';
    }

    // ================================================================
    // 1. Signature invalide → 400 INVALID_SIGNATURE
    // ================================================================

    public function testHandleReturnsBadRequestOnInvalidSignature(): void
    {
        [$ctrl, $paymentMock] = $this->buildController();

        $paymentMock->method('verifyIpnSignature')->willReturn(false);

        $caught = null;
        $output = '';
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (HttpException $e) {
            $output = (string) ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(400, $caught->status);
        $this->assertSame('INVALID_SIGNATURE', $output);
    }

    // ================================================================
    // 2. Référence déjà existante → 200 OK (idempotence)
    // ================================================================

    public function testHandleReturns200OkOnDuplicateReference(): void
    {
        [$ctrl, $paymentMock, $orderMock] = $this->buildController();

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(
            ['id' => 99, 'order_reference' => 'WEB-CB-AABBCCDD-2026']
        );
        $orderMock->expects($this->never())->method('createFromIpn');

        $caught = null;
        $output = '';
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (HttpException $e) {
            $output = (string) ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
        $this->assertSame('OK', $output);
    }

    // ================================================================
    // 3. Paiement refusé (Erreur ≠ 00000) → 200 REFUSED
    // ================================================================

    public function testHandleReturns200OnRefusedPayment(): void
    {
        $_GET['Erreur'] = '00001';

        [$ctrl, $paymentMock, $orderMock] = $this->buildController();

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $orderMock->expects($this->never())->method('createFromIpn');

        $caught = null;
        $output = '';
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (HttpException $e) {
            $output = (string) ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
        $this->assertSame('REFUSED', $output);
    }

    // ================================================================
    // 4. Paiement accepté + snapshot valide → commande créée, echo OK
    // ================================================================

    public function testHandleCreatesOrderOnSuccess(): void
    {
        $this->defaultSession();

        [$ctrl, $paymentMock, $orderMock, $cartMock, $mailMock] = $this->buildController();

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $orderMock->expects($this->once())
            ->method('createFromIpn')
            ->with(
                42,
                $this->anything(),
                30.0,
                0.0,
                1,
                1,
                '1.0',
                'WEB-CB-AABBCCDD-2026',
                '1234',
                '5678'
            )
            ->willReturn('WEB-CB-AABBCCDD-2026');

        $cartMock->expects($this->once())->method('clear')->with(42);
        $mailMock->expects($this->once())->method('sendOrderConfirmationToClient');
        $mailMock->expects($this->once())->method('sendOrderConfirmationToOwner');

        $caught = null;
        $output = '';
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (HttpException $e) {
            $output = (string) ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
        $this->assertSame('OK', $output);
        $this->assertArrayNotHasKey('ca_payment', $_SESSION);
    }

    // ================================================================
    // 5. Mismatch référence session vs GET → 200 REF_MISMATCH
    // ================================================================

    public function testHandleReturns200OnRefMismatch(): void
    {
        // Snapshot avec une référence différente de celle reçue en GET
        $this->defaultSession('WEB-CB-DIFFERENT-2026');

        [$ctrl, $paymentMock, $orderMock] = $this->buildController();

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $orderMock->expects($this->never())->method('createFromIpn');

        $caught = null;
        $output = '';
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (HttpException $e) {
            $output = (string) ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
        $this->assertSame('REF_MISMATCH', $output);
    }
}
