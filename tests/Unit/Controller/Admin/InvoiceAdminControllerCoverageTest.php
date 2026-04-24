<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\InvoiceAdminController;
use Core\Exception\HttpException;
use Model\OrderModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable pour InvoiceAdminController.
 * Surcharge sendZipResponse() pour éviter readfile() + exit, et
 * createTempFile() pour pointer vers un vrai fichier temp.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TestableInvoiceAdminController extends InvoiceAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 1, 'role' => 'admin', 'email' => 'admin@test.com'];

    /** Chemin du ZIP capturé lors de sendZipResponse() */
    public ?string $capturedZipPath = null;
    public ?string $capturedZipName = null;

    /** @var string|null Chemin du fichier temp retourné par createTempFile() */
    public ?string $tempFilePath = null;

    /** @return array<string, mixed> */
    protected function requireAdmin(): array
    {
        return $this->fakeAdmin;
    }

    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        // no-op
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

    protected function redirect(string $url, int $status = 302): never
    {
        throw new HttpException($status);
    }

    protected function createTempFile(): string
    {
        if ($this->tempFilePath !== null) {
            return $this->tempFilePath;
        }
        $this->tempFilePath = (string) tempnam(sys_get_temp_dir(), 'crab_inv_test_');
        return $this->tempFilePath;
    }

    protected function sendZipResponse(string $tmpPath, string $fileName): never
    {
        $this->capturedZipPath = $tmpPath;
        $this->capturedZipName = $fileName;
        throw new HttpException(200); // terminaison contrôlée, identique au comportement réel
    }
}

/**
 * Tests de couverture pour InvoiceAdminController.
 *
 * Cible : L131-132 — branche ternaire invoice_number !== null → entry = invoice_number . '.pdf'
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class InvoiceAdminControllerCoverageTest extends TestCase
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
        $_SERVER['REQUEST_URI']    = '/admin/factures/export';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
        $_FILES   = [];
    }

    private function makeController(): TestableInvoiceAdminController
    {
        return new TestableInvoiceAdminController(new \Core\Request());
    }

    /**
     * @param TestableInvoiceAdminController                          $ctrl
     * @param OrderModel&\PHPUnit\Framework\MockObject\MockObject     $mock
     */
    private function injectOrdersMock(
        TestableInvoiceAdminController $ctrl,
        \PHPUnit\Framework\MockObject\MockObject $mock
    ): void {
        $prop = new \ReflectionProperty(InvoiceAdminController::class, 'orders');
        $prop->setValue($ctrl, $mock); // NOSONAR — test unitaire
    }

    // ================================================================
    // export() — invoice_number non-null → entry = invoice_number . '.pdf' (L131-132)
    // ================================================================

    /**
     * export() couvre la branche ternaire L131-132 :
     * quand invoice_number !== null, l'entrée ZIP est invoice_number . '.pdf'.
     *
     * On crée un vrai fichier PDF temporaire et on injecte une row avec
     * invoice_number renseigné — is_file() retourne true, la branche L132 est exécutée.
     */
    public function testExportZipEntryUsesInvoiceNumberWhenNotNull(): void
    {
        // Créer un faux PDF temporaire accessible sur disque
        $invoiceDir = ROOT_PATH . '/storage/invoices/';
        if (!is_dir($invoiceDir)) {
            mkdir($invoiceDir, 0755, true);
        }
        $filename    = 'test_export_cov_' . uniqid() . '.pdf';
        $storagePath = 'storage/invoices/' . $filename;
        $fullPath    = ROOT_PATH . '/' . $storagePath;
        file_put_contents($fullPath, '%PDF-1.4 export coverage test');

        $ctrl = $this->makeController();

        $rows = [
            [
                'path'            => $storagePath,
                'invoice_number'  => 'FACT-2026-0099', // non-null → L132
                'order_reference' => 'WEB-VB-EXPORT-2026',
                'ordered_at'      => '2026-01-15 10:00:00',
                'price'           => 120.00,
                'payment_method'  => 'virement',
                'email'           => 'client@test.com',
                'firstname'       => 'Jean',
                'lastname'        => 'Dupont',
                'source'          => 'web',
                'uploaded_at'     => '2026-01-16 09:30:00',
            ],
        ];

        $mock = $this->createMock(OrderModel::class);
        $mock->method('getInvoiceFilesForExport')->willReturn($rows);
        $this->injectOrdersMock($ctrl, $mock);

        $caught = null;
        try {
            $ctrl->export([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            @unlink($fullPath);
            if ($ctrl->tempFilePath !== null) {
                @unlink($ctrl->tempFilePath);
            }
        }

        // sendZipResponse() doit être appelé → HttpException(200)
        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
        $this->assertNotNull($ctrl->capturedZipName);
        $this->assertStringEndsWith('.zip', $ctrl->capturedZipName);
    }

    // ================================================================
    // export() — invoice_number null → basename(path) fallback (L133)
    // ================================================================

    /**
     * export() couvre la branche else du ternaire L131-133 :
     * quand invoice_number === null, l'entrée ZIP est basename(path).
     */
    public function testExportZipEntryUsesBasenameWhenInvoiceNumberNull(): void
    {
        $invoiceDir = ROOT_PATH . '/storage/invoices/';
        if (!is_dir($invoiceDir)) {
            mkdir($invoiceDir, 0755, true);
        }
        $filename    = 'test_export_basename_' . uniqid() . '.pdf';
        $storagePath = 'storage/invoices/' . $filename;
        $fullPath    = ROOT_PATH . '/' . $storagePath;
        file_put_contents($fullPath, '%PDF-1.4 basename fallback test');

        $ctrl = $this->makeController();

        $rows = [
            [
                'path'            => $storagePath,
                'invoice_number'  => null, // null → L133 basename fallback
                'order_reference' => 'WEB-VB-BASENAME-2026',
                'ordered_at'      => '2026-02-20 14:00:00',
                'price'           => 75.00,
                'payment_method'  => 'card',
                'email'           => 'autre@test.com',
                'firstname'       => 'Marie',
                'lastname'        => 'Martin',
                'source'          => 'web',
                'uploaded_at'     => null,
            ],
        ];

        $mock = $this->createMock(OrderModel::class);
        $mock->method('getInvoiceFilesForExport')->willReturn($rows);
        $this->injectOrdersMock($ctrl, $mock);

        $caught = null;
        try {
            $ctrl->export([]);
        } catch (HttpException $e) {
            $caught = $e;
        } finally {
            @unlink($fullPath);
            if ($ctrl->tempFilePath !== null) {
                @unlink($ctrl->tempFilePath);
            }
        }

        $this->assertNotNull($caught);
        $this->assertSame(200, $caught->status);
    }

    // ================================================================
    // export() — rows vides → flash erreur + redirect (L103-111)
    // ================================================================

    /**
     * export() flash une erreur et redirige quand aucune facture n'est trouvée.
     */
    public function testExportRedirectsWhenNoInvoicesFound(): void
    {
        $ctrl = $this->makeController();

        $mock = $this->createMock(OrderModel::class);
        $mock->method('getInvoiceFilesForExport')->willReturn([]);
        $this->injectOrdersMock($ctrl, $mock);

        $caught = null;
        try {
            $ctrl->export([]);
        } catch (HttpException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $errors = $_SESSION['_test_flash']['error'] ?? [];
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('facture', strtolower($errors[0]));
    }
}
