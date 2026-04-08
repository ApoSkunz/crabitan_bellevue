<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\RgpdExportLogModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour RgpdExportLogModel.
 *
 * Vérifie le comportement de log(), count(), getAll() et purgeExpired()
 * via un stub Database — sans BDD réelle.
 */
class RgpdExportLogModelTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    /**
     * @return array{0: \PHPUnit\Framework\MockObject\Stub, 1: RgpdExportLogModel}
     */
    private function makeStubAndModel(): array
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return [$stub, new RgpdExportLogModel()];
    }

    // ----------------------------------------------------------------
    // log()
    // ----------------------------------------------------------------

    /**
     * log() appelle db->insert() avec les bons paramètres.
     */
    public function testLogCallsInsert(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('insert')
            ->with(
                $this->stringContains('rgpd_export_logs'),
                $this->callback(static function (array $p): bool {
                    return $p[0] === 42            // user_id
                        && $p[1] === 'u@test.fr'   // email
                        && $p[2] === '1.2.3.4'     // ip
                        && $p[3] === 'export.zip'; // filename
                })
            );

        $this->instanceProp->setValue(null, $db);
        $model = new RgpdExportLogModel();
        $model->log('u@test.fr', '1.2.3.4', 'export.zip', 42);
    }

    /**
     * log() accepte user_id null (compte supprimé).
     */
    public function testLogAcceptsNullUserId(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('insert')
            ->with(
                $this->anything(),
                $this->callback(static fn(array $p): bool => $p[0] === null)
            );

        $this->instanceProp->setValue(null, $db);
        $model = new RgpdExportLogModel();
        $model->log('ghost@test.fr', '::1', 'export.json');
    }

    // ----------------------------------------------------------------
    // count()
    // ----------------------------------------------------------------

    /**
     * count() sans filtre retourne le total.
     */
    public function testCountReturnsTotal(): void
    {
        [$stub, $model] = $this->makeStubAndModel();
        $stub->method('fetchOne')->willReturn(['cnt' => '7']);

        $this->assertSame(7, $model->count());
    }

    /**
     * count() retourne 0 si fetchOne échoue.
     */
    public function testCountReturnZeroOnFailure(): void
    {
        [$stub, $model] = $this->makeStubAndModel();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertSame(0, $model->count());
    }

    // ----------------------------------------------------------------
    // getAll()
    // ----------------------------------------------------------------

    /**
     * getAll() retourne un tableau vide si fetchAll renvoie false.
     */
    public function testGetAllReturnEmptyArrayOnEmpty(): void
    {
        [$stub, $model] = $this->makeStubAndModel();
        $stub->method('fetchAll')->willReturn([]);

        $this->assertSame([], $model->getAll());
    }

    /**
     * getAll() retransmet les lignes renvoyées par fetchAll.
     */
    public function testGetAllReturnRows(): void
    {
        $rows = [
            ['id' => 1, 'email' => 'a@test.fr', 'ip' => '1.1.1.1', 'filename' => 'f1.zip'],
            ['id' => 2, 'email' => 'b@test.fr', 'ip' => '2.2.2.2', 'filename' => 'f2.zip'],
        ];

        [$stub, $model] = $this->makeStubAndModel();
        $stub->method('fetchAll')->willReturn($rows);

        $this->assertSame($rows, $model->getAll());
    }

    // ----------------------------------------------------------------
    // purgeExpired()
    // ----------------------------------------------------------------

    /**
     * purgeExpired() retourne le nombre de lignes supprimées.
     */
    public function testPurgeExpiredReturnsCount(): void
    {
        [$stub, $model] = $this->makeStubAndModel();
        $stub->method('execute')->willReturn(3);

        $this->assertSame(3, $model->purgeExpired());
    }

    /**
     * purgeExpired() retourne 0 si rien à supprimer.
     */
    public function testPurgeExpiredReturnsZeroWhenNothingToDelete(): void
    {
        [$stub, $model] = $this->makeStubAndModel();
        $stub->method('execute')->willReturn(0);

        $this->assertSame(0, $model->purgeExpired());
    }

    // ----------------------------------------------------------------
    // RETENTION_SECONDS
    // ----------------------------------------------------------------

    /**
     * La constante de rétention correspond bien à 3 ans.
     */
    public function testRetentionIs3Years(): void
    {
        $threeYearsInSeconds = 3 * 365 * 24 * 3600;
        $this->assertSame($threeYearsInSeconds, RgpdExportLogModel::RETENTION_SECONDS);
    }
}
