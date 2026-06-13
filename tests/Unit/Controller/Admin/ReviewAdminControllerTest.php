<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Core\Database;
use Core\Request;
use Model\ReviewModel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour ReviewAdminController.
 */
#[AllowMockObjectsWithoutExpectations]
class ReviewAdminControllerTest extends TestCase
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
        $_SERVER['REQUEST_URI']    = '/admin/avis';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_SESSION = [];
        $_GET     = [];
    }

    private function makeController(): TestableReviewAdminController
    {
        return new TestableReviewAdminController(new Request());
    }

    /**
     * Injecte un mock ReviewModel via réflexion.
     *
     * @param TestableReviewAdminController $ctrl
     * @param ReviewModel                   $model
     */
    private function injectModel(TestableReviewAdminController $ctrl, ReviewModel $model): void
    {
        $ref = new \ReflectionProperty(\Controller\Admin\ReviewAdminController::class, 'reviews');
        $ref->setValue($ctrl, $model);
    }

    // ----------------------------------------------------------------
    // index — vue et données
    // ----------------------------------------------------------------

    public function testIndexRendersCorrectView(): void
    {
        $model = $this->createStub(ReviewModel::class);
        $model->method('countAll')->willReturn(3);
        $model->method('findPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame('admin/reviews/index', $ctrl->lastView);
    }

    public function testIndexPassesTotalAndReviewsToView(): void
    {
        $rows = [
            ['id' => 1, 'rating' => 5, 'display_name' => 'Sophie D.', 'email' => 'sophie@example.com'],
        ];

        $model = $this->createStub(ReviewModel::class);
        $model->method('countAll')->willReturn(1);
        $model->method('findPaginated')->willReturn($rows);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(1, $ctrl->lastViewData['total']);
        $this->assertSame($rows, $ctrl->lastViewData['reviews']);
    }

    // ----------------------------------------------------------------
    // index — sanitisation page
    // ----------------------------------------------------------------

    public function testIndexPageBelowOneIsClampedToOne(): void
    {
        $_GET['page'] = '0';

        $model = $this->createStub(ReviewModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(1, $ctrl->lastViewData['page']);
    }

    public function testIndexNegativePageIsClampedToOne(): void
    {
        $_GET['page'] = '-5';

        $model = $this->createStub(ReviewModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findPaginated')->willReturn([]);

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
        $model = $this->createStub(ReviewModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(25, $ctrl->lastViewData['perPage']);
    }

    public function testIndexInvalidPerPageFallsBackToDefault(): void
    {
        $_GET['per_page'] = '999';

        $model = $this->createStub(ReviewModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(25, $ctrl->lastViewData['perPage']);
    }

    public function testIndexAllowedPerPage50(): void
    {
        $_GET['per_page'] = '50';

        $model = $this->createStub(ReviewModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(50, $ctrl->lastViewData['perPage']);
    }

    public function testIndexAllowedPerPage100(): void
    {
        $_GET['per_page'] = '100';

        $model = $this->createStub(ReviewModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame(100, $ctrl->lastViewData['perPage']);
    }

    // ----------------------------------------------------------------
    // index — appels modèle
    // ----------------------------------------------------------------

    public function testIndexCallsFindPaginatedWithCorrectPageAndPerPage(): void
    {
        $_GET['page']     = '2';
        $_GET['per_page'] = '50';

        $model = $this->createMock(ReviewModel::class);
        $model->expects($this->once())
            ->method('findPaginated')
            ->with(2, 50)
            ->willReturn([]);
        $model->method('countAll')->willReturn(0);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);
    }

    public function testIndexAdminSectionIsReviews(): void
    {
        $model = $this->createStub(ReviewModel::class);
        $model->method('countAll')->willReturn(0);
        $model->method('findPaginated')->willReturn([]);

        $ctrl = $this->makeController();
        $this->injectModel($ctrl, $model);
        $ctrl->index([]);

        $this->assertSame('reviews', $ctrl->lastViewData['adminSection']);
    }
}
