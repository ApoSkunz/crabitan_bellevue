<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Core\Database;
use Core\Request;
use Model\CartModel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour CartAdminController.
 */
#[AllowMockObjectsWithoutExpectations]
class CartAdminControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';

        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);

        $_SESSION = [];
        $_GET     = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/paniers';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_GET     = [];
    }

    private function makeController(): TestableCartAdminController
    {
        return new TestableCartAdminController(new Request());
    }

    /**
     * Injecte un mock CartModel via réflexion.
     *
     * @param TestableCartAdminController $ctrl
     * @param CartModel                   $model
     */
    private function injectModel(TestableCartAdminController $ctrl, CartModel $model): void
    {
        $ref = new \ReflectionProperty(\Controller\Admin\CartAdminController::class, 'carts');
        $ref->setValue($ctrl, $model);
    }

    // ----------------------------------------------------------------
    // index — vue et données
    // ----------------------------------------------------------------

    public function testIndexRendersCorrectView(): void
    {
        $model = $this->createStub(CartModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findAllPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame('admin/carts/index', $ctrl->lastView);
    }

    public function testIndexPassesTotalAndCartsToView(): void
    {
        $rows = [
            ['id' => 1, 'user_id' => 2, 'total_quantity' => 3, 'email' => 'user@example.com'],
        ];

        $model = $this->createStub(CartModel::class);
        $model->method('countAll')->willReturn(1);
        $model->method('findAllPaginated')->willReturn($rows);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(1, $ctrl->lastViewData['total']);
        $this->assertSame($rows, $ctrl->lastViewData['carts']);
    }

    // ----------------------------------------------------------------
    // index — sanitisation page
    // ----------------------------------------------------------------

    public function testIndexPageBelowOneIsClampedToOne(): void
    {
        $_GET['page'] = '0';

        $model = $this->createStub(CartModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findAllPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(1, $ctrl->lastViewData['page']);
    }

    public function testIndexNegativePageIsClampedToOne(): void
    {
        $_GET['page'] = '-3';

        $model = $this->createStub(CartModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findAllPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(1, $ctrl->lastViewData['page']);
    }

    // ----------------------------------------------------------------
    // index — sanitisation perPage
    // ----------------------------------------------------------------

    public function testIndexDefaultPerPageIs25(): void
    {
        $model = $this->createStub(CartModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findAllPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(25, $ctrl->lastViewData['perPage']);
    }

    public function testIndexInvalidPerPageFallsBackToDefault(): void
    {
        $_GET['per_page'] = '42';

        $model = $this->createStub(CartModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findAllPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(25, $ctrl->lastViewData['perPage']);
    }

    public function testIndexAllowedPerPage50(): void
    {
        $_GET['per_page'] = '50';

        $model = $this->createStub(CartModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findAllPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(50, $ctrl->lastViewData['perPage']);
    }

    public function testIndexAllowedPerPage100(): void
    {
        $_GET['per_page'] = '100';

        $model = $this->createStub(CartModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findAllPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(100, $ctrl->lastViewData['perPage']);
    }

    // ----------------------------------------------------------------
    // index — appels modèle
    // ----------------------------------------------------------------

    public function testIndexCallsFindAllPaginatedWithCorrectPageAndPerPage(): void
    {
        $_GET['page']     = '3';
        $_GET['per_page'] = '100';

        $model = $this->createMock(CartModel::class);
        $model->expects($this->once())
            ->method('findAllPaginated')
            ->with(3, 100)
            ->willReturn([]);
        $model->method('countAll')->willReturn(0);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);
    }

    public function testIndexAdminSectionIsCarts(): void
    {
        $model = $this->createStub(CartModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findAllPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame('carts', $ctrl->lastViewData['adminSection']);
    }
}
