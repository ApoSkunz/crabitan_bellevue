<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\AdminLogModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour AdminLogModel.
 * La BDD est mockée — aucune connexion réelle requise.
 */
#[AllowMockObjectsWithoutExpectations]
class AdminLogModelTest extends TestCase
{
    private Database $dbMock;
    private AdminLogModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new AdminLogModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // exportCsv() — nominal (fopen ok)
    // ----------------------------------------------------------------

    /**
     * exportCsv() retourne un CSV UTF-8 BOM avec les données filtrées.
     *
     * @return void
     */
    public function testExportCsvReturnsUtf8BomCsvWithRows(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([
            [
                'id'          => 1,
                'created_at'  => '2024-01-01 10:00:00',
                'admin_id'    => 2,
                'admin_name'  => 'Admin User',
                'admin_email' => 'admin@example.com',
                'action'      => 'order.status_updated',
                'target_type' => 'order',
                'target_id'   => 99,
                'details'     => '{"before":"pending"}',
                'ip_address'  => '127.0.0.1',
            ],
        ]);

        $csv = $this->model->exportCsv([]);

        // UTF-8 BOM présent
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        // Données présentes
        $this->assertStringContainsString('order.status_updated', $csv);
        $this->assertStringContainsString('admin@example.com', $csv);
    }

    // ----------------------------------------------------------------
    // exportCsv() — sans données
    // ----------------------------------------------------------------

    /**
     * exportCsv() retourne un CSV avec uniquement l'en-tête si aucune ligne.
     *
     * @return void
     */
    public function testExportCsvReturnsHeaderOnlyWhenNoRows(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $csv = $this->model->exportCsv([]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ID', $csv);
        $this->assertStringContainsString('Action', $csv);
    }

    // ----------------------------------------------------------------
    // exportCsv() — fopen retourne false (ligne 128)
    // ----------------------------------------------------------------

    /**
     * exportCsv() retourne '' quand fopen('php://memory') échoue (ligne 128).
     *
     * PHP résout les appels non qualifiés en cherchant d'abord dans le namespace courant.
     * En définissant Model\fopen avant l'appel, on force le retour false.
     * Le test s'exécute dans un processus isolé pour éviter les conflits de redéfinition.
     */
    #[RunInSeparateProcess]
    public function testExportCsvReturnEmptyStringWhenFopenFails(): void
    {
        // Redéfinir fopen dans le namespace Model pour simuler l'échec
        eval('
namespace Model;
function fopen(string $filename, string $mode, bool $use_include_path = false, $context = null): mixed
{
    return false;
}
');

        $this->dbMock->method('fetchAll')->willReturn([]);

        $result = $this->model->exportCsv([]);

        $this->assertSame('', $result);
    }

    // ----------------------------------------------------------------
    // count() — nominal
    // ----------------------------------------------------------------

    /**
     * count() retourne le nombre d'entrées filtrées.
     *
     * @return void
     */
    public function testCountReturnsInt(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['n' => 42]);

        $result = $this->model->count([]);

        $this->assertSame(42, $result);
    }

    /**
     * count() retourne 0 quand fetchOne retourne false.
     *
     * @return void
     */
    public function testCountReturnsZeroWhenFetchOneReturnsFalse(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $result = $this->model->count([]);

        $this->assertSame(0, $result);
    }

    // ----------------------------------------------------------------
    // fetchFiltered() — nominal
    // ----------------------------------------------------------------

    /**
     * fetchFiltered() retourne le tableau de lignes fourni par la BDD.
     *
     * @return void
     */
    public function testFetchFilteredReturnsRows(): void
    {
        $rows = [
            ['id' => 1, 'action' => 'login', 'admin_email' => 'a@b.com', 'admin_name' => 'Admin'],
        ];
        $this->dbMock->method('fetchAll')->willReturn($rows);

        $result = $this->model->fetchFiltered([], 10, 0);

        $this->assertSame($rows, $result);
    }
}
