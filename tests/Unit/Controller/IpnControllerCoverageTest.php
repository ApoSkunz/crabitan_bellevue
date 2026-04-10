<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\IpnController;
use Core\Exception\HttpException;
use Model\CartModel;
use Model\OrderModel;
use Model\PaymentIntentModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Service\MailService;
use Service\PaymentService;

/**
 * Tests de couverture complémentaires pour IpnController.
 *
 * Couvre les branches non atteintes par IpnControllerTest :
 *  - Signature invalide + envoi d'alerte mail (MAINTAINER_MAIL défini)
 *  - Signature invalide + alerte mail qui lève une exception
 *  - Paiement accepté + erreur lors de l'envoi des emails de confirmation
 *  - Erreur inattendue non-HttpException → catch global → 500 ERROR
 */
#[AllowMockObjectsWithoutExpectations]
class IpnControllerCoverageTest extends TestCase
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
     * @param PaymentService&\PHPUnit\Framework\MockObject\MockObject     $paymentMock
     * @param OrderModel&\PHPUnit\Framework\MockObject\MockObject         $orderMock
     * @param CartModel&\PHPUnit\Framework\MockObject\MockObject          $cartMock
     * @param MailService&\PHPUnit\Framework\MockObject\MockObject        $mailMock
     * @param PaymentIntentModel&\PHPUnit\Framework\MockObject\MockObject $intentsMock
     * @return IpnController
     */
    private function makeController(
        \PHPUnit\Framework\MockObject\MockObject $paymentMock,
        \PHPUnit\Framework\MockObject\MockObject $orderMock,
        \PHPUnit\Framework\MockObject\MockObject $cartMock,
        \PHPUnit\Framework\MockObject\MockObject $mailMock,
        \PHPUnit\Framework\MockObject\MockObject $intentsMock
    ): IpnController {
        $ref  = new \ReflectionClass(IpnController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();

        $ref->getProperty('payment')->setValue($ctrl, $paymentMock);
        $ref->getProperty('orders')->setValue($ctrl, $orderMock);
        $ref->getProperty('carts')->setValue($ctrl, $cartMock);
        $ref->getProperty('mail')->setValue($ctrl, $mailMock);
        $ref->getProperty('intents')->setValue($ctrl, $intentsMock);

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
     *   4: MailService&\PHPUnit\Framework\MockObject\MockObject,
     *   5: PaymentIntentModel&\PHPUnit\Framework\MockObject\MockObject
     * }
     */
    private function buildController(): array
    {
        $paymentMock = $this->createMock(PaymentService::class);
        $orderMock   = $this->createMock(OrderModel::class);
        $cartMock    = $this->createMock(CartModel::class);
        $mailMock    = $this->createMock(MailService::class);
        $intentsMock = $this->createMock(PaymentIntentModel::class);

        $intentsMock->method('purgeExpired');

        $ctrl = $this->makeController($paymentMock, $orderMock, $cartMock, $mailMock, $intentsMock);

        return [$ctrl, $paymentMock, $orderMock, $cartMock, $mailMock, $intentsMock];
    }

    /** @return array<string, mixed> */
    private function defaultSnapshot(string $reference = 'WEB-CB-AABBCCDD-2026'): array
    {
        return [
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
        unset($_ENV['MAINTAINER_MAIL']);
    }

    // ================================================================
    // 1. Signature invalide + MAINTAINER_MAIL défini → alerte mail envoyée
    // ================================================================

    /**
     * Vérifie que sendIpnSignatureAlert est appelé quand MAINTAINER_MAIL est défini
     * et que la signature IPN est invalide.
     */
    public function testHandleSendsAlertMailOnInvalidSignatureWhenMaintainerMailSet(): void
    {
        $_ENV['MAINTAINER_MAIL'] = 'admin@example.com';

        [$ctrl, $paymentMock, , , $mailMock] = $this->buildController();

        $paymentMock->method('verifyIpnSignature')->willReturn(false);
        $mailMock->expects($this->once())
            ->method('sendIpnSignatureAlert')
            ->with(
                'admin@example.com',
                $this->anything(),
                $this->anything()
            );

        $caught = null;
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (HttpException $e) {
            ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(400, $caught->status);
    }

    // ================================================================
    // 2. Signature invalide + MAINTAINER_MAIL vide → alerte NON envoyée
    // ================================================================

    /**
     * Vérifie que sendIpnSignatureAlert n'est PAS appelé si MAINTAINER_MAIL est vide.
     */
    public function testHandleDoesNotSendAlertMailWhenMaintainerMailEmpty(): void
    {
        $_ENV['MAINTAINER_MAIL'] = '';

        [$ctrl, $paymentMock, , , $mailMock] = $this->buildController();

        $paymentMock->method('verifyIpnSignature')->willReturn(false);
        $mailMock->expects($this->never())->method('sendIpnSignatureAlert');

        $caught = null;
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (HttpException $e) {
            ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(400, $caught->status);
    }

    // ================================================================
    // 3. Signature invalide + alerte mail qui lève une exception
    //    → exception swallowée, 400 quand même retourné
    // ================================================================

    /**
     * Vérifie que si sendIpnSignatureAlert lève une exception, elle est absorbée
     * (error_log) et le contrôleur retourne quand même 400.
     */
    public function testHandleSwallowsAlertMailExceptionAndStillReturns400(): void
    {
        $_ENV['MAINTAINER_MAIL'] = 'admin@example.com';

        [$ctrl, $paymentMock, , , $mailMock] = $this->buildController();

        $paymentMock->method('verifyIpnSignature')->willReturn(false);
        $mailMock->method('sendIpnSignatureAlert')
            ->willThrowException(new \RuntimeException('SMTP unavailable'));

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
    // 4. Paiement accepté + erreur lors des emails de confirmation
    //    → exception swallowée, 200 OK quand même retourné
    // ================================================================

    /**
     * Vérifie que si sendOrderConfirmationToClient lève une exception, elle est absorbée
     * (error_log) et le contrôleur retourne quand même 200 OK avec la commande créée.
     */
    public function testHandleSwallowsMailExceptionAndStillReturns200OnSuccess(): void
    {
        [$ctrl, $paymentMock, $orderMock, $cartMock, $mailMock, $intentsMock] = $this->buildController();

        $snapshot = $this->defaultSnapshot();

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $intentsMock->method('findByReference')->willReturn($snapshot);
        $intentsMock->method('delete');
        $orderMock->method('createFromIpn')->willReturn('WEB-CB-AABBCCDD-2026');
        $cartMock->method('clear');

        $mailMock->method('sendOrderConfirmationToClient')
            ->willThrowException(new \RuntimeException('SMTP error'));
        $mailMock->expects($this->never())->method('sendOrderConfirmationToOwner');

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
    // 5. Erreur inattendue non-HttpException → catch global → 500 ERROR
    // ================================================================

    /**
     * Vérifie que toute exception non-HttpException est attrapée par le catch global,
     * produit un echo 'ERROR' et une HttpException(500).
     */
    public function testHandleReturns500OnUnexpectedException(): void
    {
        [$ctrl, $paymentMock, $orderMock, , , $intentsMock] = $this->buildController();

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $intentsMock->method('findByReference')->willReturn($this->defaultSnapshot());
        $orderMock->method('createFromIpn')
            ->willThrowException(new \RuntimeException('Database connection lost'));

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
        $this->assertSame(500, $caught->status);
        $this->assertSame('ERROR', $output);
    }

    // ================================================================
    // 7. Paiement accepté + findByReferenceWithAddresses retourne null
    //    → bloc facture entièrement ignoré
    // ================================================================

    /**
     * Vérifie que le bloc de génération de facture est ignoré quand
     * findByReferenceWithAddresses retourne null.
     */
    public function testHandleSkipsInvoiceWhenOrderNotFoundByReference(): void
    {
        [$ctrl, $paymentMock, $orderMock, $cartMock, $mailMock, $intentsMock] = $this->buildController();

        $snapshot = $this->defaultSnapshot();

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $intentsMock->method('findByReference')->willReturn($snapshot);
        $intentsMock->method('delete');
        $orderMock->method('createFromIpn')->willReturn('WEB-CB-AABBCCDD-2026');
        $cartMock->method('clear');
        $orderMock->method('findByReferenceWithAddresses')->willReturn(null);

        // updateInvoiceData ne doit jamais être appelé
        $orderMock->expects($this->never())->method('updateInvoiceData');
        $mailMock->expects($this->never())->method('sendInvoiceToClient');

        $caught = null;
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (\Core\Exception\HttpException $e) {
            ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
    }

    // ================================================================
    // 8. Paiement accepté + facture générée + email non envoyé (email vide)
    // ================================================================

    /**
     * Vérifie que sendInvoiceToClient n'est pas appelé quand client_email est vide.
     */
    public function testHandleInvoiceGeneratedButEmailSkippedWhenClientEmailEmpty(): void
    {
        [$ctrl, $paymentMock, $orderMock, $cartMock, $mailMock, $intentsMock] = $this->buildController();

        $snapshot                  = $this->defaultSnapshot();
        $snapshot['client_email']  = ''; // email vide

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $intentsMock->method('findByReference')->willReturn($snapshot);
        $intentsMock->method('delete');
        $orderMock->method('createFromIpn')->willReturn('WEB-CB-AABBCCDD-2026');
        $cartMock->method('clear');
        $orderMock->method('findByReferenceWithAddresses')->willReturn([
            'id'               => 999,
            'order_reference'  => 'WEB-CB-AABBCCDD-2026',
            'payment_method'   => 'card',
            'content'          => '[]',
            'price'            => 30.0,
            'shipping_discount' => 0.0,
            'ordered_at'       => date('Y-m-d H:i:s'),
        ]);
        $orderMock->method('updateInvoiceData');

        $mailMock->expects($this->never())->method('sendInvoiceToClient');

        $caught = null;
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (\Core\Exception\HttpException $e) {
            ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
    }

    // ================================================================
    // 9. Paiement accepté + facture générée + email envoyé
    // ================================================================

    /**
     * Vérifie que sendInvoiceToClient est bien appelé avec le bon email
     * quand client_email est défini dans le snapshot.
     */
    public function testHandleInvoiceEmailSentWhenClientEmailSet(): void
    {
        [$ctrl, $paymentMock, $orderMock, $cartMock, $mailMock, $intentsMock] = $this->buildController();

        $snapshot = $this->defaultSnapshot();
        // client_email est déjà défini dans defaultSnapshot

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $intentsMock->method('findByReference')->willReturn($snapshot);
        $intentsMock->method('delete');
        $orderMock->method('createFromIpn')->willReturn('WEB-CB-AABBCCDD-2026');
        $cartMock->method('clear');
        $orderMock->method('findByReferenceWithAddresses')->willReturn([
            'id'               => 999,
            'order_reference'  => 'WEB-CB-AABBCCDD-2026',
            'payment_method'   => 'card',
            'content'          => '[]',
            'price'            => 30.0,
            'shipping_discount' => 0.0,
            'ordered_at'       => date('Y-m-d H:i:s'),
        ]);
        $orderMock->method('updateInvoiceData');

        $mailMock->expects($this->once())
            ->method('sendInvoiceToClient')
            ->with(
                'client@example.com',
                'Jean Dupont',
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'fr'
            );

        $caught = null;
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (\Core\Exception\HttpException $e) {
            ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
    }

    // ================================================================
    // 10. Paiement accepté + sendInvoiceToClient lève exception → catch interne
    // ================================================================

    /**
     * Vérifie que si sendInvoiceToClient lève une exception, elle est absorbée
     * et le traitement continue normalement (200 OK).
     */
    public function testHandleInvoiceEmailExceptionIsCaughtSilently(): void
    {
        [$ctrl, $paymentMock, $orderMock, $cartMock, $mailMock, $intentsMock] = $this->buildController();

        $snapshot = $this->defaultSnapshot();

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $intentsMock->method('findByReference')->willReturn($snapshot);
        $intentsMock->method('delete');
        $orderMock->method('createFromIpn')->willReturn('WEB-CB-AABBCCDD-2026');
        $cartMock->method('clear');
        $orderMock->method('findByReferenceWithAddresses')->willReturn([
            'id'               => 999,
            'order_reference'  => 'WEB-CB-AABBCCDD-2026',
            'payment_method'   => 'card',
            'content'          => '[]',
            'price'            => 30.0,
            'shipping_discount' => 0.0,
            'ordered_at'       => date('Y-m-d H:i:s'),
        ]);
        $orderMock->method('updateInvoiceData');
        $mailMock->method('sendInvoiceToClient')
            ->willThrowException(new \RuntimeException('SMTP unavailable'));

        $caught = null;
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (\Core\Exception\HttpException $e) {
            ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
    }

    // ================================================================
    // 11. Paiement accepté + findByReferenceWithAddresses lève exception
    //     → catch externe bloc facture
    // ================================================================

    /**
     * Vérifie que si findByReferenceWithAddresses lève une exception, elle est
     * absorbée par le catch externe et le traitement continue (200 OK).
     */
    public function testHandleInvoiceOuterExceptionIsCaughtSilently(): void
    {
        [$ctrl, $paymentMock, $orderMock, $cartMock, $mailMock, $intentsMock] = $this->buildController();

        $snapshot = $this->defaultSnapshot();

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $intentsMock->method('findByReference')->willReturn($snapshot);
        $intentsMock->method('delete');
        $orderMock->method('createFromIpn')->willReturn('WEB-CB-AABBCCDD-2026');
        $cartMock->method('clear');
        $orderMock->method('findByReferenceWithAddresses')
            ->willThrowException(new \RuntimeException('DB failure'));

        $caught = null;
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (\Core\Exception\HttpException $e) {
            ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
    }

    // ================================================================
    // 12. Constructeur réel — instanciation sans BDD (les modèles ne
    //     touchent la BDD qu'à la première requête, pas dans __construct)
    // ================================================================

    /**
     * Vérifie que le constructeur de IpnController s'exécute sans erreur
     * quand Database::instance est un mock (aucun appel BDD requis au construct).
     */
    public function testConstructorInitializesAllDependencies(): void
    {
        $dbMock = $this->createStub(\Core\Database::class);
        $prop   = new \ReflectionProperty(\Core\Database::class, 'instance');
        $prop->setValue(null, $dbMock);

        try {
            $ctrl = new IpnController();
            $this->assertInstanceOf(IpnController::class, $ctrl);

            $ref = new \ReflectionClass(IpnController::class);
            $this->assertInstanceOf(PaymentService::class, $ref->getProperty('payment')->getValue($ctrl));
            $this->assertInstanceOf(OrderModel::class, $ref->getProperty('orders')->getValue($ctrl));
            $this->assertInstanceOf(CartModel::class, $ref->getProperty('carts')->getValue($ctrl));
            $this->assertInstanceOf(MailService::class, $ref->getProperty('mail')->getValue($ctrl));
            $this->assertInstanceOf(PaymentIntentModel::class, $ref->getProperty('intents')->getValue($ctrl));
        } finally {
            $prop->setValue(null, null);
        }
    }

    // ================================================================
    // 13. mkdirInvoices() — seam de testabilité
    // ================================================================

    /**
     * Vérifie que mkdirInvoices() crée le répertoire cible.
     */
    public function testMkdirInvoicesCreatesDirectory(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ipn_mkdir_test_' . uniqid();
        $this->assertDirectoryDoesNotExist($tmpDir);

        $ref  = new \ReflectionClass(IpnController::class);
        $ctrl = $ref->newInstanceWithoutConstructor();

        $method = $ref->getMethod('mkdirInvoices');
        $method->invoke($ctrl, $tmpDir);

        $this->assertDirectoryExists($tmpDir);
        rmdir($tmpDir);
    }

    // ================================================================
    // 14. Paiement accepté + répertoire invoices absent → mkdir appelé
    // ================================================================

    /**
     * Vérifie que mkdirInvoices est appelé quand le répertoire storage/invoices n'existe pas.
     */
    public function testHandleCallsMkdirWhenInvoiceDirDoesNotExist(): void
    {
        // Sous-classe qui overrides mkdirInvoices et createInvoiceService
        $ctrl = new class extends IpnController {
            public bool $mkdirCalled = false;
            public string $mkdirPath = '';

            protected function mkdirInvoices(string $dir): void
            {
                $this->mkdirCalled = true;
                $this->mkdirPath   = $dir;
                // Ne pas créer réellement le répertoire
            }

            protected function createInvoiceService(): \Service\InvoiceService
            {
                return new class extends \Service\InvoiceService {
                    public function generate(array $order, string $invoiceNumber): string
                    {
                        return '%PDF-1.4 stub';
                    }
                };
            }
        };

        $ref = new \ReflectionClass(IpnController::class);
        $paymentMock = $this->createMock(PaymentService::class);
        $orderMock   = $this->createMock(OrderModel::class);
        $cartMock    = $this->createMock(CartModel::class);
        $mailMock    = $this->createMock(MailService::class);
        $intentsMock = $this->createMock(PaymentIntentModel::class);

        $ref->getProperty('payment')->setValue($ctrl, $paymentMock);
        $ref->getProperty('orders')->setValue($ctrl, $orderMock);
        $ref->getProperty('carts')->setValue($ctrl, $cartMock);
        $ref->getProperty('mail')->setValue($ctrl, $mailMock);
        $ref->getProperty('intents')->setValue($ctrl, $intentsMock);

        $snapshot = $this->defaultSnapshot();
        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $intentsMock->method('findByReference')->willReturn($snapshot);
        $intentsMock->method('purgeExpired');
        $intentsMock->method('delete');
        $orderMock->method('createFromIpn')->willReturn('WEB-CB-AABBCCDD-2026');
        $cartMock->method('clear');

        // Retourner un order avec un id non-zero pour entrer dans le bloc facture
        $orderMock->method('findByReferenceWithAddresses')->willReturn([
            'id'              => 42,
            'order_reference' => 'WEB-CB-AABBCCDD-2026',
            'payment_method'  => 'card',
            'content'         => '[]',
            'price'           => 30.0,
            'ordered_at'      => date('Y-m-d H:i:s'),
        ]);
        $orderMock->method('updateInvoiceData');

        // S'assurer que le répertoire n'existe PAS pour forcer la branche mkdir
        $invoiceDir = ROOT_PATH . '/storage/invoices';
        $dirExisted = is_dir($invoiceDir);
        if ($dirExisted) {
            // Patcher la constante est impossible — skip si répertoire existe déjà
            $this->markTestSkipped('storage/invoices existe déjà — branche mkdir non atteignable sans supprimer le répertoire');
        }

        $caught = null;
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (\Core\Exception\HttpException $e) {
            ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertTrue($ctrl->mkdirCalled, 'mkdirInvoices aurait dû être appelé');
        $this->assertStringContainsString('storage/invoices', $ctrl->mkdirPath);
    }

    // ================================================================
    // 6. Paiement accepté + snapshot sans lang/client_email/client_name
    //    → valeurs par défaut utilisées (branches ?? dans les casts)
    // ================================================================

    /**
     * Vérifie que les valeurs par défaut ('fr', '', '') sont utilisées quand
     * le snapshot ne contient pas les clés lang, client_email, client_name.
     */
    public function testHandleUsesDefaultsForMissingSnapshotFields(): void
    {
        [$ctrl, $paymentMock, $orderMock, $cartMock, $mailMock, $intentsMock] = $this->buildController();

        // Snapshot minimal sans lang/client_email/client_name
        $snapshot = [
            'user_id'             => 7,
            'items'               => [],
            'total'               => 10.0,
            'delivery_discount'   => 0.0,
            'billing_address_id'  => 2,
            'delivery_address_id' => 2,
            'cgv_version'         => '2.0',
        ];

        $paymentMock->method('verifyIpnSignature')->willReturn(true);
        $orderMock->method('findByReferenceOnly')->willReturn(null);
        $intentsMock->method('findByReference')->willReturn($snapshot);
        $intentsMock->method('delete');
        $orderMock->method('createFromIpn')->willReturn('WEB-CB-AABBCCDD-2026');
        $cartMock->method('clear');

        $mailMock->expects($this->once())
            ->method('sendOrderConfirmationToClient')
            ->with(
                '',       // client_email par défaut
                '',       // client_name par défaut
                $this->anything(),
                'card',
                [],
                10.0,
                'fr'      // lang par défaut
            );

        $caught = null;
        ob_start();
        try {
            $ctrl->handle([]);
        } catch (HttpException $e) {
            ob_get_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
    }
}
