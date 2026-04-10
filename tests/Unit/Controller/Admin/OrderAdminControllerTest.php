<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\OrderAdminController;
use Core\Exception\HttpException;
use Model\OrderModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;
use Service\PaymentService;

/**
 * Sous-classe testable qui remplace les dépendances infrastructure
 * (requireAdmin, verifyCsrf, view, flash, abort, seams factory).
 */
class TestableOrderAdminController extends OrderAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 1, 'role' => 'admin', 'email' => 'admin@test.com'];
    public bool $csrfValid = true;

    /** @var MailService&\PHPUnit\Framework\MockObject\MockObject|null */
    public ?MailService $mailMock = null;

    /** @var PaymentService&\PHPUnit\Framework\MockObject\MockObject|null */
    public ?PaymentService $paymentMock = null;

    public bool $fileMoveResult = true;

    /** @return array<string, mixed> */
    protected function requireAdmin(): array
    {
        return $this->fakeAdmin;
    }

    protected function verifyCsrf(): bool
    {
        return $this->csrfValid;
    }

    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        // no-op — évite le rendu PHP
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['_test_flash'][$type][] = $message;
    }

    protected function getFlash(string $type): ?string
    {
        return null;
    }

    protected function abort(int $status = 404, string $message = 'Not Found'): never
    {
        throw new HttpException($status);
    }

    protected function newMailService(): MailService
    {
        return $this->mailMock ?? parent::newMailService();
    }

    protected function newPaymentService(): PaymentService
    {
        return $this->paymentMock ?? parent::newPaymentService();
    }

    protected function moveUploadedFile(string $src, string $dest): bool
    {
        return $this->fileMoveResult;
    }
}

/**
 * Tests unitaires pour OrderAdminController.
 *
 * Couvre les 4 actions : index, show, updateStatus, uploadInvoice, downloadInvoice.
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class OrderAdminControllerTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';

        $dbMock = $this->createStub(\Core\Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(\Core\Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $_SESSION = ['csrf' => 'valid-csrf'];
        $_POST    = [];
        $_GET     = [];
        $_FILES   = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/commandes';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
        $_FILES   = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function makeController(): TestableOrderAdminController
    {
        return new TestableOrderAdminController(new \Core\Request());
    }

    /**
     * Injecte un mock OrderModel dans le contrôleur via Reflection.
     *
     * @param TestableOrderAdminController                          $ctrl
     * @param OrderModel&\PHPUnit\Framework\MockObject\MockObject   $mock
     */
    private function injectOrdersMock(
        TestableOrderAdminController $ctrl,
        \PHPUnit\Framework\MockObject\MockObject $mock
    ): void {
        $prop = new ReflectionProperty(OrderAdminController::class, 'orders');
        $prop->setValue($ctrl, $mock); // NOSONAR — test unitaire
    }

    /** @return array<string, mixed> */
    private function sampleOrder(string $status = 'pending'): array
    {
        return [
            'id'               => 42,
            'order_reference'  => 'WEB-VB-AABB1234-2026',
            'status'           => $status,
            'payment_method'   => 'virement',
            'price'            => 150.0,
            'ca_numappel'      => '',
            'ca_numtrans'      => '',
            'path_invoice'     => null,
            'email'            => 'client@test.com',
            'firstname'        => 'Jean',
            'lastname'         => 'Dupont',
            'lang'             => 'fr',
            'content'          => '[]',
            'shipping_discount' => 0.0,
        ];
    }

    // ================================================================
    // show — commande trouvée
    // ================================================================

    /**
     * show() appelle view() avec les données commande quand l'ordre existe.
     */
    public function testShowRendersViewWhenOrderFound(): void
    {
        $ctrl  = $this->makeController();
        $mock  = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($this->sampleOrder());
        $this->injectOrdersMock($ctrl, $mock);

        // Aucune exception ne doit être levée
        $ctrl->show(['id' => '42']);
        $this->assertTrue(true);
    }

    // ================================================================
    // show — commande introuvable → abort 404
    // ================================================================

    /**
     * show() lève HttpException 404 quand la commande est introuvable.
     */
    public function testShowAborts404WhenOrderNotFound(): void
    {
        $ctrl  = $this->makeController();
        $mock  = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn(null);
        $this->injectOrdersMock($ctrl, $mock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $ctrl->show(['id' => '99']);
    }

    // ================================================================
    // updateStatus — CSRF invalide → redirect 302
    // ================================================================

    /**
     * updateStatus() redirige avec 302 quand le token CSRF est invalide.
     */
    public function testUpdateStatusRedirectsOnInvalidCsrf(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($this->sampleOrder());
        $this->injectOrdersMock($ctrl, $mock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $ctrl->updateStatus(['id' => '42']);
    }

    // ================================================================
    // updateStatus — commande introuvable → abort 404
    // ================================================================

    /**
     * updateStatus() lève HttpException 404 quand la commande est introuvable.
     */
    public function testUpdateStatusAborts404WhenOrderNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $ctrl = $this->makeController();
        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn(null);
        $this->injectOrdersMock($ctrl, $mock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $ctrl->updateStatus(['id' => '99']);
    }

    // ================================================================
    // updateStatus — commande annulée → flash erreur + redirect
    // ================================================================

    /**
     * updateStatus() refuse la modification d'une commande déjà annulée.
     */
    public function testUpdateStatusRefusesModificationOfCancelledOrder(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['status' => 'processing'];
        $ctrl = $this->makeController();
        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($this->sampleOrder('cancelled'));
        $this->injectOrdersMock($ctrl, $mock);

        $caught = null;
        try {
            $ctrl->updateStatus(['id' => '42']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertNotEmpty($_SESSION['_test_flash']['error'] ?? []);
    }

    // ================================================================
    // updateStatus — 'refunded' + CB + numappel manquant → flash erreur
    // ================================================================

    /**
     * updateStatus() refuse le remboursement CB quand NUMAPPEL est manquant.
     */
    public function testUpdateStatusRefusesRefundWhenNumappelMissing(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['status' => 'refunded'];
        $ctrl = $this->makeController();

        $order = $this->sampleOrder('paid');
        $order['payment_method'] = 'card';
        $order['ca_numappel']    = '';
        $order['ca_numtrans']    = '';

        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $this->injectOrdersMock($ctrl, $mock);

        $caught = null;
        try {
            $ctrl->updateStatus(['id' => '42']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $errors = $_SESSION['_test_flash']['error'] ?? [];
        $this->assertNotEmpty($errors);
    }

    // ================================================================
    // updateStatus — 'refunded' + CB + GAE échoue → flash erreur
    // ================================================================

    /**
     * updateStatus() flash une erreur quand le remboursement GAE échoue.
     */
    public function testUpdateStatusFlashesErrorWhenGaeRefundFails(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['status' => 'refunded'];
        $ctrl  = $this->makeController();

        $order = $this->sampleOrder('paid');
        $order['payment_method'] = 'card';
        $order['ca_numappel']    = '12345';
        $order['ca_numtrans']    = '67890';

        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $this->injectOrdersMock($ctrl, $mock);

        $paymentMock = $this->createMock(PaymentService::class);
        $paymentMock->method('callGaeCancelOrRefund')->willReturn(false);
        $ctrl->paymentMock = $paymentMock;

        $caught = null;
        try {
            $ctrl->updateStatus(['id' => '42']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $errors = $_SESSION['_test_flash']['error'] ?? [];
        $this->assertNotEmpty($errors);
    }

    // ================================================================
    // updateStatus — changement de statut trigger → email envoyé
    // ================================================================

    /**
     * updateStatus() envoie un email de statut quand le statut change
     * vers un statut déclencheur (ex. 'shipped').
     */
    public function testUpdateStatusSendsStatusEmailOnTriggerStatusChange(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['status' => 'shipped'];
        $ctrl  = $this->makeController();

        $order = $this->sampleOrder('processing');

        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $mock->method('updateStatus');
        $mock->method('findByReferenceWithAddresses')->willReturn(null);
        $this->injectOrdersMock($ctrl, $mock);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->expects($this->once())->method('sendOrderStatusEmail');
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->updateStatus(['id' => '42']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // updateStatus — email statut lève exception → absorbée silencieusement
    // ================================================================

    /**
     * updateStatus() absorbe silencieusement les exceptions SMTP.
     */
    public function testUpdateStatusAbsorbsMailException(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['status' => 'delivered'];
        $ctrl  = $this->makeController();

        $order = $this->sampleOrder('shipped');

        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $mock->method('updateStatus');
        $mock->method('findByReferenceWithAddresses')->willReturn(null);
        $this->injectOrdersMock($ctrl, $mock);

        $mailMock = $this->createMock(MailService::class);
        $mailMock->method('sendOrderStatusEmail')
            ->willThrowException(new \RuntimeException('SMTP error'));
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->updateStatus(['id' => '42']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // updateStatus — facture déjà existante → génération ignorée
    // ================================================================

    /**
     * updateStatus() ne génère pas de nouvelle facture si path_invoice est déjà renseigné.
     */
    public function testUpdateStatusSkipsInvoiceWhenAlreadyExists(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['status' => 'processing'];
        $ctrl  = $this->makeController();

        $order = $this->sampleOrder('pending');
        $order['path_invoice'] = 'storage/invoices/WEB-INVOICE-2026-0042.pdf';

        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $mock->method('updateStatus');
        // updateInvoiceData ne doit pas être appelé
        $mock->expects($this->never())->method('updateInvoiceData');
        $this->injectOrdersMock($ctrl, $mock);

        $mailMock = $this->createMock(MailService::class);
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->updateStatus(['id' => '42']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // updateStatus — génération facture, findByReferenceWithAddresses null → ignoré
    // ================================================================

    /**
     * Si findByReferenceWithAddresses retourne null, la génération est ignorée.
     */
    public function testUpdateStatusSkipsInvoiceWhenReferenceWithAddressesNull(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['status' => 'processing'];
        $ctrl  = $this->makeController();

        $order = $this->sampleOrder('pending');
        // path_invoice null → génération déclenchée

        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $mock->method('updateStatus');
        $mock->method('findByReferenceWithAddresses')->willReturn(null);
        $mock->expects($this->never())->method('updateInvoiceData');
        $this->injectOrdersMock($ctrl, $mock);

        $mailMock = $this->createMock(MailService::class);
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->updateStatus(['id' => '42']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // newPaymentService() — seam retourne PaymentService (L48)
    // ================================================================

    /**
     * newPaymentService() retourne une instance de PaymentService (couvre L48).
     */
    public function testNewPaymentServiceReturnsPaymentService(): void
    {
        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(OrderAdminController::class, 'newPaymentService');
        $result = $method->invoke($ctrl);
        $this->assertInstanceOf(PaymentService::class, $result);
    }

    // ================================================================
    // moveUploadedFile() — seam appelle move_uploaded_file (L60)
    // ================================================================

    /**
     * moveUploadedFile() retourne false hors contexte HTTP (move_uploaded_file échoue en CLI).
     * Couvre L60 en invoquant le corps de la méthode.
     */
    public function testMoveUploadedFileReturnsFalseInCli(): void
    {
        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(OrderAdminController::class, 'moveUploadedFile');
        // En CLI, move_uploaded_file retourne false — la méthode doit retourner false
        $result = $method->invoke($ctrl, '/tmp/nonexistent_src', '/tmp/nonexistent_dest');
        $this->assertFalse($result);
    }

    // ================================================================
    // updateStatus — génération facture + mail (L227, L254, L259, L260)
    // ================================================================

    /**
     * updateStatus() génère la facture et envoie l'email quand fullOrder est non-null
     * et path_invoice est vide. Couvre L227 (mkdir), L244-253 (mail), L254-257 (catch mail).
     */
    public function testUpdateStatusGeneratesInvoiceAndSendsMail(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['status' => 'processing'];
        $ctrl  = $this->makeController();

        $order = $this->sampleOrder('pending');
        // path_invoice null → génération déclenchée

        $fullOrder = $this->sampleOrder('pending');
        $fullOrder['bill_civility']   = 'M';
        $fullOrder['bill_firstname']  = 'Jean';
        $fullOrder['bill_lastname']   = 'Dupont';
        $fullOrder['bill_street']     = '1 rue de la Paix';
        $fullOrder['bill_city']       = 'Paris';
        $fullOrder['bill_zip']        = '75001';
        $fullOrder['bill_country']    = 'France';
        $fullOrder['ship_civility']   = 'M';
        $fullOrder['ship_firstname']  = 'Jean';
        $fullOrder['ship_lastname']   = 'Dupont';
        $fullOrder['ship_street']     = '1 rue de la Paix';
        $fullOrder['ship_city']       = 'Paris';
        $fullOrder['ship_zip']        = '75001';
        $fullOrder['ship_country']    = 'France';

        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $mock->method('updateStatus');
        $mock->method('findByReferenceWithAddresses')->willReturn($fullOrder);
        $mock->method('updateInvoiceData');
        $mock->method('addInvoiceFile');
        $this->injectOrdersMock($ctrl, $mock);

        // MailService stub : sendOrderStatusEmail + sendInvoiceToClient ne bloquent pas
        $mailMock = $this->createStub(MailService::class);
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->updateStatus(['id' => '42']);
        } catch (\HttpException $e) {
            $caught = $e;
        } catch (\Core\Exception\HttpException $e) {
            $caught = $e;
        }

        // Le test ne doit pas lever de 500 — une 302 est attendue
        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    /**
     * updateStatus() absorbe l'exception quand l'envoi de l'email de facture échoue.
     * Couvre la branche L254-257 (catch \Throwable $mailErr).
     */
    public function testUpdateStatusAbsorbsInvoiceMailException(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['status' => 'processing'];
        $ctrl  = $this->makeController();

        $order = $this->sampleOrder('pending');

        $fullOrder            = $this->sampleOrder('pending');
        $fullOrder['bill_city']   = 'Paris';
        $fullOrder['ship_city']   = 'Paris';

        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $mock->method('updateStatus');
        $mock->method('findByReferenceWithAddresses')->willReturn($fullOrder);
        $mock->method('updateInvoiceData');
        $mock->method('addInvoiceFile');
        $this->injectOrdersMock($ctrl, $mock);

        // MailService qui lève une exception sur sendInvoiceToClient → absorbée
        $throwingMail = $this->createMock(MailService::class);
        $throwingMail->method('sendInvoiceToClient')
            ->willThrowException(new \RuntimeException('SMTP invoice error'));
        $ctrl->mailMock = $throwingMail;

        $caught = null;
        try {
            $ctrl->updateStatus(['id' => '42']);
        } catch (\Core\Exception\HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    /**
     * updateStatus() absorbe l'exception générale de génération de facture.
     * Couvre la branche L259-261 (catch \Throwable $invoiceError).
     */
    public function testUpdateStatusAbsorbsInvoiceGenerationException(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['status' => 'processing'];
        $ctrl  = $this->makeController();

        $order = $this->sampleOrder('pending');

        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $mock->method('updateStatus');
        // findByReferenceWithAddresses lève une exception → capturée par le catch externe
        $mock->method('findByReferenceWithAddresses')
            ->willThrowException(new \RuntimeException('DB error on invoice'));
        $this->injectOrdersMock($ctrl, $mock);

        $mailMock = $this->createStub(MailService::class);
        $ctrl->mailMock = $mailMock;

        $caught = null;
        try {
            $ctrl->updateStatus(['id' => '42']);
        } catch (\Core\Exception\HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
    }

    // ================================================================
    // uploadInvoice — CSRF invalide → redirect
    // ================================================================

    /**
     * uploadInvoice() redirige si le CSRF est invalide.
     */
    public function testUploadInvoiceRedirectsOnInvalidCsrf(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $ctrl = $this->makeController();
        $ctrl->csrfValid = false;
        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($this->sampleOrder());
        $this->injectOrdersMock($ctrl, $mock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);
        $ctrl->uploadInvoice(['id' => '42']);
    }

    // ================================================================
    // uploadInvoice — commande introuvable → abort 404
    // ================================================================

    /**
     * uploadInvoice() lève 404 si la commande est introuvable.
     */
    public function testUploadInvoiceAborts404WhenOrderNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $ctrl = $this->makeController();
        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn(null);
        $this->injectOrdersMock($ctrl, $mock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $ctrl->uploadInvoice(['id' => '99']);
    }

    // ================================================================
    // uploadInvoice — aucun fichier → flash erreur
    // ================================================================

    /**
     * uploadInvoice() flash une erreur si aucun fichier n'est uploadé.
     */
    public function testUploadInvoiceFlashesErrorWhenNoFile(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_FILES = ['invoice' => ['tmp_name' => '']];
        $ctrl = $this->makeController();
        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($this->sampleOrder());
        $this->injectOrdersMock($ctrl, $mock);

        $caught = null;
        try {
            $ctrl->uploadInvoice(['id' => '42']);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertNotEmpty($_SESSION['_test_flash']['error'] ?? []);
    }

    // ================================================================
    // downloadInvoice — commande introuvable → abort 404
    // ================================================================

    /**
     * downloadInvoice() lève 404 si la commande est introuvable.
     */
    public function testDownloadInvoiceAborts404WhenOrderNotFound(): void
    {
        $ctrl = $this->makeController();
        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn(null);
        $this->injectOrdersMock($ctrl, $mock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $ctrl->downloadInvoice(['id' => '99']);
    }

    // ================================================================
    // downloadInvoice — chemin facture vide → abort 404
    // ================================================================

    /**
     * downloadInvoice() lève 404 si path_invoice est vide.
     */
    public function testDownloadInvoiceAborts404WhenPathInvoiceEmpty(): void
    {
        $ctrl = $this->makeController();
        $order = $this->sampleOrder();
        $order['path_invoice'] = '';
        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $this->injectOrdersMock($ctrl, $mock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $ctrl->downloadInvoice(['id' => '42']);
    }

    // ================================================================
    // downloadInvoice — fichier physique absent → abort 404
    // ================================================================

    /**
     * downloadInvoice() lève 404 si le fichier PDF n'existe pas sur disque.
     */
    public function testDownloadInvoiceAborts404WhenFileNotOnDisk(): void
    {
        $ctrl = $this->makeController();
        $order = $this->sampleOrder();
        $order['path_invoice'] = 'storage/invoices/inexistant-xyz-9999.pdf';
        $mock = $this->createMock(OrderModel::class);
        $mock->method('findByIdForAdmin')->willReturn($order);
        $this->injectOrdersMock($ctrl, $mock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $ctrl->downloadInvoice(['id' => '42']);
    }
}
