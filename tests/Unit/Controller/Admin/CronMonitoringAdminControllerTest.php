<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\CronMonitoringAdminController;
use Core\Database;
use Core\Request;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable qui by-pass requireSuperAdmin() et neutralise view().
 * On conserve la vraie logique de index() — seule la frontière HTTP est mockée.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TestableCronMonitoringAdminController extends CronMonitoringAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = [
        'id'   => 1,
        'role' => 'super_admin',
        'name' => 'Admin',
    ];

    /** @var array{template: string, data: array<string, mixed>}|null */
    public ?array $viewCall = null;

    /** @return array<string, mixed> */
    protected function requireSuperAdmin(): array
    {
        return $this->fakeAdmin;
    }

    /**
     * Capture les données passées à la vue sans effectuer de rendu.
     *
     * @param string               $template
     * @param array<string, mixed> $data
     * @param int                  $httpStatus
     */
    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        $this->viewCall = ['template' => $template, 'data' => $data];
    }
}

/**
 * Tests unitaires pour CronMonitoringAdminController.
 *
 * Cible : 100% lignes (23 lignes) — couvre whitelist per_page, clamps page,
 * transmission summary/runs à la vue.
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class CronMonitoringAdminControllerTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    /**
     * Prépare environnement test + stub Database par défaut.
     */
    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';

        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setValue(null, $this->createStub(Database::class));

        $_SESSION = ['csrf' => 'valid'];
        $_POST    = [];
        $_GET     = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/crons';
    }

    /**
     * Nettoie superglobales et singleton Database.
     */
    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
    }

    /**
     * Installe un mock Database avec les réponses souhaitées pour
     * countAll(), getSummary24h(), getPage().
     *
     * @param int                              $total
     * @param array<int, array<string, mixed>> $summary
     * @param array<int, array<string, mixed>> $runs
     */
    private function stubDb(int $total, array $summary, array $runs): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(['cnt' => $total]);
        $db->method('fetchAll')->willReturnCallback(
            static function (string $sql) use ($summary, $runs): array {
                if (str_contains($sql, 'GROUP BY job_name')) {
                    return $summary;
                }
                return $runs;
            }
        );
        $this->dbInstanceProp->setValue(null, $db);
    }

    /**
     * Happy path — valeurs par défaut (per_page=25, page=1, total=0).
     */
    public function testIndexWithDefaults(): void
    {
        $this->stubDb(0, [], []);

        $ctrl = new TestableCronMonitoringAdminController(new Request());
        $ctrl->index([]);

        $this->assertNotNull($ctrl->viewCall);
        $this->assertSame('admin/cron-monitoring/index', $ctrl->viewCall['template']);

        $data = $ctrl->viewCall['data'];
        $this->assertSame('cron_monitoring', $data['adminSection']);
        $this->assertSame('Monitoring crons', $data['pageTitle']);
        $this->assertSame(25, $data['perPage']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(1, $data['totalPages']);
        $this->assertSame(0, $data['totalRuns']);
        $this->assertSame([], $data['summary']);
        $this->assertSame([], $data['runs']);
        $this->assertSame([25, 50, 100], $data['perPageOpts']);
        $this->assertSame(
            [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Monitoring crons']],
            $data['breadcrumbs']
        );
    }

    /**
     * per_page hors whitelist → clamp à 25.
     */
    public function testInvalidPerPageIsClamped(): void
    {
        $this->stubDb(10, [], []);
        $_GET['per_page'] = '999';

        $ctrl = new TestableCronMonitoringAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(25, $ctrl->viewCall['data']['perPage']);
    }

    /**
     * per_page=100 conservé (whitelist), page 2, calcule totalPages correctement.
     */
    public function testValidPerPageAndPagination(): void
    {
        $this->stubDb(250, [], []);
        $_GET['per_page'] = '100';
        $_GET['page']     = '2';

        $ctrl = new TestableCronMonitoringAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(100, $ctrl->viewCall['data']['perPage']);
        $this->assertSame(2, $ctrl->viewCall['data']['page']);
        $this->assertSame(3, $ctrl->viewCall['data']['totalPages']); // ceil(250/100) = 3
    }

    /**
     * Page demandée au-delà de totalPages → clampée à totalPages.
     */
    public function testPageIsClampedToTotalPages(): void
    {
        $this->stubDb(30, [], []);
        $_GET['per_page'] = '25';
        $_GET['page']     = '99';

        $ctrl = new TestableCronMonitoringAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(2, $ctrl->viewCall['data']['totalPages']);
        $this->assertSame(2, $ctrl->viewCall['data']['page']);
    }

    /**
     * Page négative/zéro → clampée à 1.
     */
    public function testPageZeroIsClampedToOne(): void
    {
        $this->stubDb(10, [], []);
        $_GET['page'] = '-5';

        $ctrl = new TestableCronMonitoringAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(1, $ctrl->viewCall['data']['page']);
    }

    /**
     * Summary et runs remontés de la BDD sont transmis tels quels à la vue.
     */
    public function testSummaryAndRunsPassedToView(): void
    {
        $summary = [
            ['job_name' => 'purge_accounts', 'runs' => 5, 'success' => 5, 'errors' => 0],
        ];
        $runs = [
            ['id' => 1, 'job_name' => 'purge_accounts', 'status' => 'success'],
            ['id' => 2, 'job_name' => 'send_newsletter', 'status' => 'error'],
        ];
        $this->stubDb(2, $summary, $runs);

        $ctrl = new TestableCronMonitoringAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame($summary, $ctrl->viewCall['data']['summary']);
        $this->assertSame($runs, $ctrl->viewCall['data']['runs']);
    }
}
