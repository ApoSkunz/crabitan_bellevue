<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use Controller\Admin\AuditLogAdminController;
use Core\Database;
use Core\Request;
use Model\AdminLogModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Sous-classe testable qui contourne les dépendances infrastructure :
 * - requireSuperAdmin() retourne un faux admin sans appel JWT
 * - view() est neutralisé (no-op)
 * - doExit() lève une exception sentinelle au lieu de exit (couvre lines 106-108)
 * - logModel est remplaçable via propriété publique
 */
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class TestableAuditLogAdminController extends AuditLogAdminController
{
    /** @var array<string, mixed> */
    public array $fakeAdmin = [
        'id'    => 1,
        'role'  => 'super_admin',
        'name'  => 'Super Admin',
        'email' => 'superadmin@test.com',
    ];

    /** @var array{template: string, data: array<string, mixed>}|null */
    public ?array $viewCall = null;

    public bool $exitCalled = false;

    /** @return array<string, mixed> */
    protected function requireSuperAdmin(): array
    {
        return $this->fakeAdmin;
    }

    /**
     * @param string               $template
     * @param array<string, mixed> $data
     * @param int                  $httpStatus
     */
    protected function view(string $template, array $data = [], int $httpStatus = 200): void
    {
        $this->viewCall = ['template' => $template, 'data' => $data];
    }

    /**
     * Override doExit() pour éviter que exit tue le processus PHPUnit.
     * Marque exitCalled = true et lève une exception sentinelle.
     * Ceci couvre les lines 106-108 de AuditLogAdminController.
     *
     * @return never
     */
    protected function doExit(): never
    {
        $this->exitCalled = true;
        throw new \RuntimeException('__exit_called__');
    }

    /**
     * Injecte un AdminLogModel stub à la place de celui créé dans le constructeur.
     *
     * @param AdminLogModel $model
     * @return void
     */
    public function setLogModel(AdminLogModel $model): void
    {
        $ref = new \ReflectionProperty(AuditLogAdminController::class, 'logModel');
        $ref->setValue($this, $model);
    }
}

/**
 * Tests unitaires pour AuditLogAdminController.
 *
 * Cible principale : doExit() (lines 106-108) via export(),
 * ainsi que index() et extractFilters().
 */
#[AllowMockObjectsWithoutExpectations]
// phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
class AuditLogAdminControllerTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

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
        $_SERVER['REQUEST_URI']    = '/admin/audit-logs';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
    }

    /**
     * Crée un stub AdminLogModel avec des retours configurables.
     *
     * @param int                          $count
     * @param array<int,array<string,mixed>> $rows
     * @param string                         $csv
     * @return AdminLogModel
     */
    private function makeLogModelStub(int $count = 0, array $rows = [], string $csv = ''): AdminLogModel
    {
        $stub = $this->createStub(AdminLogModel::class);
        $stub->method('count')->willReturn($count);
        $stub->method('fetchFiltered')->willReturn($rows);
        $stub->method('exportCsv')->willReturn($csv);
        return $stub;
    }

    // ----------------------------------------------------------------
    // doExit() — lines 106-108 : exit volontaire après export CSV
    // ----------------------------------------------------------------

    /**
     * Couvre les lines 106-108 : doExit() est appelé depuis export().
     *
     * La sous-classe override doExit() pour lever une exception sentinelle
     * au lieu d'appeler exit (qui tuerait le processus PHPUnit).
     *
     * @return void
     */
    public function testExportCallsDoExit(): void
    {
        $csvContent = "\xEF\xBB\xBFID;Date;Admin\n1;2026-01-01;admin\n";
        $ctrl       = new TestableAuditLogAdminController(new Request());
        $ctrl->setLogModel($this->makeLogModelStub(0, [], $csvContent));

        $caught = null;
        try {
            ob_start();
            $ctrl->export([]);
        } catch (\RuntimeException $e) {
            ob_end_clean();
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame('__exit_called__', $caught->getMessage());
        $this->assertTrue($ctrl->exitCalled, 'doExit() should have been called');
    }

    /**
     * Couvre export() : les headers CSV sont posés et le CSV est échoé avant doExit().
     *
     * @return void
     */
    public function testExportEchesCsvBeforeExit(): void
    {
        $csvContent = "\xEF\xBB\xBFID;Date\n1;2026-01-01\n";
        $ctrl       = new TestableAuditLogAdminController(new Request());
        $ctrl->setLogModel($this->makeLogModelStub(0, [], $csvContent));

        $output = '';
        try {
            ob_start();
            $ctrl->export([]);
        } catch (\RuntimeException $e) {
            $output = (string) ob_get_clean();
        }

        $this->assertSame($csvContent, $output);
    }

    // ----------------------------------------------------------------
    // index() — chemin nominal
    // ----------------------------------------------------------------

    /**
     * index() charge les logs et passe les données à la vue.
     *
     * @return void
     */
    public function testIndexRendersView(): void
    {
        $rows = [
            ['id' => 1, 'action' => 'login', 'created_at' => '2026-01-01 10:00:00'],
        ];
        $ctrl = new TestableAuditLogAdminController(new Request());
        $ctrl->setLogModel($this->makeLogModelStub(1, $rows));

        $ctrl->index([]);

        $this->assertNotNull($ctrl->viewCall);
        $this->assertSame('admin/logs/index', $ctrl->viewCall['template']);
        $data = $ctrl->viewCall['data'];
        $this->assertSame('logs', $data['adminSection']);
        $this->assertSame('Journal d\'audit', $data['pageTitle']);
        $this->assertCount(1, $data['logs']);
        $this->assertSame(1, $data['total']);
    }

    /**
     * per_page hors whitelist [25, 50, 100] → clamp à 50.
     *
     * @return void
     */
    public function testIndexInvalidPerPageIsClamped(): void
    {
        $_GET['per_page'] = '999';
        $ctrl = new TestableAuditLogAdminController(new Request());
        $ctrl->setLogModel($this->makeLogModelStub());

        $ctrl->index([]);

        $this->assertSame(50, $ctrl->viewCall['data']['perPage']);
    }

    /**
     * per_page=25 (whitelist) est conservé.
     *
     * @return void
     */
    public function testIndexValidPerPageKept(): void
    {
        $_GET['per_page'] = '25';
        $ctrl = new TestableAuditLogAdminController(new Request());
        $ctrl->setLogModel($this->makeLogModelStub());

        $ctrl->index([]);

        $this->assertSame(25, $ctrl->viewCall['data']['perPage']);
    }

    /**
     * Filtre admin_id transmis au modèle.
     *
     * @return void
     */
    public function testIndexWithAdminIdFilter(): void
    {
        $_GET['admin_id'] = '3';
        $stub = $this->createStub(AdminLogModel::class);
        $stub->method('count')->willReturnCallback(function (array $filters) {
            $this->assertSame(3, $filters['admin_id'] ?? 0);
            return 0;
        });
        $stub->method('fetchFiltered')->willReturn([]);

        $ctrl = new TestableAuditLogAdminController(new Request());
        $ctrl->setLogModel($stub);
        $ctrl->index([]);

        $this->assertSame(0, $ctrl->viewCall['data']['total']);
    }
}
