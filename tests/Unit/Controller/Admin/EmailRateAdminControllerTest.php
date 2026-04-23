<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\EmailRateAdminController;
use Core\Database;
use Core\Request;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable qui by-pass requireSuperAdmin() et neutralise view().
 * On conserve la vraie logique de index() — seule la frontière HTTP est mockée.
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TestableEmailRateAdminController extends EmailRateAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = [
        'id'    => 1,
        'role'  => 'super_admin',
        'name'  => 'Admin',
        'email' => 'admin@test.local',
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
 * Tests unitaires pour EmailRateAdminController.
 *
 * Cible : 100% lignes (25 lignes) — couvre la pagination, la whitelist per_page,
 * le clamp de page et la transmission des données à la vue.
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class EmailRateAdminControllerTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    /**
     * Prépare l'environnement : autoload, stub Database, superglobales vierges.
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
        $_SERVER['REQUEST_URI']    = '/admin/emails';
    }

    /**
     * Nettoie les superglobales et le singleton Database.
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
     * countAll(), getSummary(), getPage().
     *
     * @param int                                $total Nombre total de logs retourné par countAll
     * @param array<int, array<string, mixed>>   $summary
     * @param array<int, array<string, mixed>>   $page
     */
    private function stubDb(int $total, array $summary, array $page): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(['cnt' => $total]);
        $db->method('fetchAll')->willReturnCallback(
            static function (string $sql) use ($summary, $page): array {
                if (str_contains($sql, 'GROUP BY from_address')) {
                    return $summary;
                }
                return $page;
            }
        );
        $this->dbInstanceProp->setValue(null, $db);
    }

    /**
     * Happy path — per_page par défaut (25), page 1, total 0.
     * Vérifie la structure complète des données passées à la vue.
     */
    public function testIndexWithDefaults(): void
    {
        $this->stubDb(0, [], []);

        $ctrl = new TestableEmailRateAdminController(new Request());
        $ctrl->index([]);

        $this->assertNotNull($ctrl->viewCall);
        $this->assertSame('admin/email-rate/index', $ctrl->viewCall['template']);

        $data = $ctrl->viewCall['data'];
        $this->assertSame('email_rate', $data['adminSection']);
        $this->assertSame('Monitoring emails', $data['pageTitle']);
        $this->assertSame(25, $data['perPage']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(1, $data['totalPages']); // total=0 → fallback 1
        $this->assertSame(0, $data['totalLogs']);
        $this->assertSame([], $data['summary']);
        $this->assertSame([], $data['logs']);
        $this->assertSame([25, 50, 100], $data['perPageOpts']);
        $this->assertSame('super_admin', $data['currentRole']);
        $this->assertSame(
            [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Monitoring emails']],
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

        $ctrl = new TestableEmailRateAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(25, $ctrl->viewCall['data']['perPage']);
    }

    /**
     * per_page=50 (whitelist valide) conservé tel quel.
     */
    public function testValidPerPageIsKept(): void
    {
        $this->stubDb(120, [], []);
        $_GET['per_page'] = '50';
        $_GET['page']     = '2';

        $ctrl = new TestableEmailRateAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(50, $ctrl->viewCall['data']['perPage']);
        $this->assertSame(2, $ctrl->viewCall['data']['page']);
        $this->assertSame(3, $ctrl->viewCall['data']['totalPages']); // ceil(120/50) = 3
    }

    /**
     * Page au-delà du total → clamp à totalPages.
     */
    public function testPageIsClampedToTotalPages(): void
    {
        $this->stubDb(30, [], []);
        $_GET['per_page'] = '25';
        $_GET['page']     = '99';

        $ctrl = new TestableEmailRateAdminController(new Request());
        $ctrl->index([]);

        // ceil(30/25) = 2 → page clampée à 2
        $this->assertSame(2, $ctrl->viewCall['data']['totalPages']);
        $this->assertSame(2, $ctrl->viewCall['data']['page']);
    }

    /**
     * Page <= 0 → clamp à 1 via max(1, …).
     */
    public function testPageZeroIsClampedToOne(): void
    {
        $this->stubDb(10, [], []);
        $_GET['page'] = '0';

        $ctrl = new TestableEmailRateAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame(1, $ctrl->viewCall['data']['page']);
    }

    /**
     * Données summary et logs transmises telles quelles à la vue.
     */
    public function testSummaryAndLogsPassedToView(): void
    {
        $summary = [
            ['from_address' => 'no-reply@test', 'total_sent' => 42],
        ];
        $logs = [
            ['id' => 1, 'to_address' => 'a@b.c'],
            ['id' => 2, 'to_address' => 'd@e.f'],
        ];
        $this->stubDb(2, $summary, $logs);

        $ctrl = new TestableEmailRateAdminController(new Request());
        $ctrl->index([]);

        $this->assertSame($summary, $ctrl->viewCall['data']['summary']);
        $this->assertSame($logs, $ctrl->viewCall['data']['logs']);
    }

    /**
     * currentRole absent quand requireSuperAdmin() renvoie sans clé 'role'.
     */
    public function testCurrentRoleFallsBackToEmpty(): void
    {
        $this->stubDb(0, [], []);

        $ctrl = new TestableEmailRateAdminController(new Request());
        $ctrl->fakeAdmin = ['id' => 1, 'name' => 'Admin']; // role absent
        $ctrl->index([]);

        $this->assertSame('', $ctrl->viewCall['data']['currentRole']);
    }
}
