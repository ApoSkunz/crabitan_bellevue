<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\OrderFormAdminController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\OrderFormModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Sous-classe testable de OrderFormAdminController.
 * Bypass requireAdmin / verifyCsrf / view / flash / abort.
 */
class TestableOrderFormAdminController extends OrderFormAdminController
{
    public bool $csrfValid = true;

    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 1, 'role' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com'];

    public function __construct(Request $request)
    {
        // NOSONAR — S3011 : bypass intentionnel en test unitaire
        $reflRequest = new ReflectionProperty(\Core\Controller::class, 'request');
        $reflRequest->setValue($this, $request);

        // NOSONAR — S3011 : bypass intentionnel en test unitaire
        $reflStorage = new ReflectionProperty(OrderFormAdminController::class, 'storageDir');
        $reflStorage->setValue($this, ROOT_PATH . '/storage/order_forms');
    }

    /** @return array<string, mixed> */
    protected function requireAdmin(): array
    {
        return $this->fakeAdmin;
    }

    protected function verifyCsrf(): bool
    {
        return $this->csrfValid;
    }

    protected function view(string $template, array $data = [], int $status = 200): void
    {
        /* noop en test unitaire */
    }

    protected function flash(string $key, string $message): void
    {
        $_SESSION['admin_flash'][$key] = $message;
    }

    protected function abort(int $status = 404, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }
}

/**
 * Tests de couverture pour OrderFormAdminController.
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class OrderFormAdminControllerCoverageTest extends TestCase
{
    private ReflectionProperty $dbInstanceProp;
    private ReflectionProperty $formsProp;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';

        $dbMock = $this->createStub(Database::class);
        $this->dbInstanceProp = new ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $dbMock); // NOSONAR — test unitaire

        $this->formsProp = new ReflectionProperty(OrderFormAdminController::class, 'forms');

        $_SESSION = ['csrf' => 'tok'];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    private function makeController(): TestableOrderFormAdminController
    {
        return new TestableOrderFormAdminController(new Request());
    }

    // ----------------------------------------------------------------
    // download() → happy path : envoie le PDF et retourne
    // ----------------------------------------------------------------

    /**
     * download() envoie le contenu PDF et retourne normalement.
     */
    public function testDownloadSendsPdf(): void
    {
        $storageDir = ROOT_PATH . '/storage/order_forms';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $tmpName = 'cov_admin_dl_' . uniqid() . '.pdf';
        $tmpPath = $storageDir . '/' . $tmpName;
        file_put_contents($tmpPath, '%PDF-1.4 test coverage');

        $ctrl = $this->makeController();

        $formsMock = $this->createStub(OrderFormModel::class);
        $formsMock->method('findByIdOrNull')->willReturn([
            'id'       => 99,
            'year'     => 2025,
            'label'    => 'CoverageTest',
            'filename' => $tmpName,
        ]);
        $this->formsProp->setValue($ctrl, $formsMock);

        ob_start();
        try {
            $ctrl->download(['id' => '99']);
            $output = ob_get_clean();
        } finally {
            @unlink($tmpPath);
        }

        $this->assertStringContainsString('%PDF', $output ?? '');
    }

    // ----------------------------------------------------------------
    // download() → abort(404) quand le bon de commande est introuvable
    // ----------------------------------------------------------------

    /**
     * download() lève HttpException 404 quand le bon de commande est introuvable.
     */
    public function testDownloadAborts404WhenFormNotFound(): void
    {
        $ctrl = $this->makeController();

        $formsMock = $this->createStub(OrderFormModel::class);
        $formsMock->method('findByIdOrNull')->willReturn(null);
        $this->formsProp->setValue($ctrl, $formsMock);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $ctrl->download(['id' => '1']);
    }
}
