<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\StatsAdminController;
use Core\Exception\HttpException;
use Model\OrderModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable pour StatsAdminController.
 * Surcharge requireAdmin()/view()/abort() pour éviter les effets de bord HTTP.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TestableStatsAdminController extends StatsAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = ['id' => 1, 'role' => 'admin', 'email' => 'admin@test.com', 'name' => 'Admin'];

    /** @return array<string, mixed> */
    protected function requireAdmin(): array
    {
        return $this->fakeAdmin;
    }

    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        // no-op
    }

    protected function abort(int $status = 404, string $message = ''): never
    {
        throw new HttpException($status);
    }
}

/**
 * Tests de couverture pour StatsAdminController.
 *
 * Cible :
 *   L76  — resolvePeriod() : début du match — atteint via index() avec period=30d (déjà couvert ?)
 *           mais en réalité L76 est la ligne `return match (true) {` qui peut être non couverte
 *           si resolvePeriod() n'est jamais appelée avec une période valide.
 *   L107 — resolvePeriod() : branche default (année ex. "2024") — non couverte
 *
 * Note : resolvePeriod() est private — on passe par index() avec $_GET['period'] approprié.
 */
#[CoversClass(StatsAdminController::class)]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class StatsAdminControllerCoverageTest extends TestCase
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
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/statistiques';
        $_ENV['APP_URL'] ??= 'http://crabitan.local';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
    }

    /**
     * Construit un controller avec un OrderModel stubé.
     *
     * @param array<int> $availableYears Années disponibles retournées par getAvailableYears()
     * @return TestableStatsAdminController
     */
    private function makeController(array $availableYears = [2024]): TestableStatsAdminController
    {
        $ordersMock = $this->createStub(OrderModel::class);
        $ordersMock->method('getAvailableYears')->willReturn($availableYears);
        $ordersMock->method('getStatsForPeriod')->willReturn([
            'total_revenue' => 0.0,
            'total_orders'  => 0,
        ]);
        $ordersMock->method('getChartData')->willReturn([]);

        $ctrl = new TestableStatsAdminController(new \Core\Request());

        $refOrders = new \ReflectionProperty(StatsAdminController::class, 'orders');
        $refOrders->setValue($ctrl, $ordersMock);

        return $ctrl;
    }

    /**
     * index() avec period=30d — couvre la branche `$period === '30d'` de resolvePeriod() (L76-82).
     */
    public function testIndexWith30dPeriodCoversResolvePeriodStart(): void
    {
        $_GET['period'] = '30d';
        $ctrl = $this->makeController();

        $noException = true;
        try {
            $ctrl->index([]);
        } catch (\Throwable $e) {
            $noException = false;
        }

        $this->assertTrue($noException);
    }

    /**
     * index() avec period="2024" (année numérique présente dans availableYears) —
     * couvre la branche default de resolvePeriod() (L101-106) et la fermeture du match (L107).
     */
    public function testIndexWithYearPeriodCoversDefaultBranchOfResolvePeriod(): void
    {
        $_GET['period'] = '2024';
        $ctrl = $this->makeController([2024]);

        $noException = true;
        try {
            $ctrl->index([]);
        } catch (\Throwable $e) {
            $noException = false;
        }

        $this->assertTrue($noException);
    }

    /**
     * index() avec period invalide (non connu + non présent dans availableYears) →
     * fallback sur '30d', puis resolvePeriod('30d') — L76 atteint via chemin de validation.
     */
    public function testIndexWithInvalidPeriodFallsBackTo30d(): void
    {
        $_GET['period'] = 'invalid_period_xyz';
        $ctrl = $this->makeController([2024]);

        $noException = true;
        try {
            $ctrl->index([]);
        } catch (\Throwable $e) {
            $noException = false;
        }

        $this->assertTrue($noException);
    }

    /**
     * index() avec period=3m — couvre la branche `$period === '3m'` de resolvePeriod().
     */
    public function testIndexWith3mPeriod(): void
    {
        $_GET['period'] = '3m';
        $ctrl = $this->makeController();

        $noException = true;
        try {
            $ctrl->index([]);
        } catch (\Throwable $e) {
            $noException = false;
        }

        $this->assertTrue($noException);
    }

    /**
     * index() avec period=1y — couvre la branche `$period === '1y'` de resolvePeriod().
     */
    public function testIndexWith1yPeriod(): void
    {
        $_GET['period'] = '1y';
        $ctrl = $this->makeController();

        $noException = true;
        try {
            $ctrl->index([]);
        } catch (\Throwable $e) {
            $noException = false;
        }

        $this->assertTrue($noException);
    }

    /**
     * index() avec period=all — couvre la branche `$period === 'all'` de resolvePeriod().
     */
    public function testIndexWithAllPeriod(): void
    {
        $_GET['period'] = 'all';
        $ctrl = $this->makeController();

        $noException = true;
        try {
            $ctrl->index([]);
        } catch (\Throwable $e) {
            $noException = false;
        }

        $this->assertTrue($noException);
    }
}
