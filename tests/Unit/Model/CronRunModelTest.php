<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\CronRunModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CronRunModel.
 * Couvre start(), finishSuccess(), finishError(), countAll(), getPage(), getSummary24h().
 */
#[AllowMockObjectsWithoutExpectations]
class CronRunModelTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    /**
     * Crée un mock strict de Database et un CronRunModel lié.
     *
     * @return array{0: \PHPUnit\Framework\MockObject\MockObject, 1: CronRunModel}
     */
    private function makeMockAndModel(): array
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        return [$mock, new CronRunModel()];
    }

    // ----------------------------------------------------------------
    // start()
    // ----------------------------------------------------------------

    /**
     * start() insère un run et purge les runs > 30 jours, retourne l'id.
     */
    public function testStartInsertsAndReturnsIntId(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('insert')
            ->with(
                $this->stringContains('INSERT INTO cron_runs'),
                $this->callback(function (array $params): bool {
                    $this->assertSame('purge-accounts', $params[':job']);
                    $this->assertSame('running', $params[':status']);
                    $this->assertSame(0, $params[':dry']);
                    return true;
                })
            )
            ->willReturn('42');

        $mock->expects($this->once())
            ->method('execute')
            ->with($this->stringContains('DELETE FROM cron_runs WHERE started_at <'));

        $id = $model->start('purge-accounts', false);
        $this->assertSame(42, $id);
    }

    /**
     * start() convertit dryRun=true en 1 dans le paramètre :dry.
     */
    public function testStartWithDryRunPassesOne(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('insert')
            ->with(
                $this->anything(),
                $this->callback(function (array $params): bool {
                    $this->assertSame(1, $params[':dry']);
                    return true;
                })
            )
            ->willReturn('7');
        $mock->expects($this->once())->method('execute')->willReturn(0);

        $this->assertSame(7, $model->start('purge-connections', true));
    }

    // ----------------------------------------------------------------
    // finishSuccess()
    // ----------------------------------------------------------------

    /**
     * finishSuccess() exécute UPDATE status=success, clamp count à 0 minimum et tronque log.
     */
    public function testFinishSuccessExecutesUpdateWithClampedParams(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $longLog = str_repeat('x', 10000);

        $mock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains("status = :status"),
                $this->callback(function (array $params) use ($longLog): bool {
                    $this->assertSame('success', $params[':status']);
                    // Negative count clamped to 0
                    $this->assertSame(0, $params[':count']);
                    $this->assertSame(55, $params[':id']);
                    // Log tronqué à 8000 caractères
                    $this->assertSame(8000, mb_strlen($params[':log']));
                    $this->assertSame(substr($longLog, 0, 8000), $params[':log']);
                    return true;
                })
            );

        $model->finishSuccess(55, -3, $longLog);
    }

    /**
     * finishSuccess() transmet un count positif tel quel.
     */
    public function testFinishSuccessKeepsPositiveCount(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('execute')
            ->with(
                $this->anything(),
                $this->callback(function (array $params): bool {
                    $this->assertSame(12, $params[':count']);
                    return true;
                })
            );

        $model->finishSuccess(1, 12, 'short log');
    }

    // ----------------------------------------------------------------
    // finishError()
    // ----------------------------------------------------------------

    /**
     * finishError() exécute UPDATE status=error et tronque errorMessage à 500.
     */
    public function testFinishErrorTruncatesErrorMessage(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $longErr = str_repeat('E', 800);
        $longLog = str_repeat('L', 10000);

        $mock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains("status = :status"),
                $this->callback(function (array $params) use ($longErr, $longLog): bool {
                    $this->assertSame('error', $params[':status']);
                    $this->assertSame(500, mb_strlen($params[':err']));
                    $this->assertSame(substr($longErr, 0, 500), $params[':err']);
                    $this->assertSame(8000, mb_strlen($params[':log']));
                    $this->assertSame(substr($longLog, 0, 8000), $params[':log']);
                    $this->assertSame(7, $params[':id']);
                    return true;
                })
            );

        $model->finishError(7, $longErr, $longLog);
    }

    /**
     * finishError() fonctionne avec logOutput par défaut (chaîne vide).
     */
    public function testFinishErrorWithDefaultLogOutput(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('execute')
            ->with(
                $this->anything(),
                $this->callback(function (array $params): bool {
                    $this->assertSame('', $params[':log']);
                    $this->assertSame('boom', $params[':err']);
                    return true;
                })
            );

        $model->finishError(1, 'boom');
    }

    // ----------------------------------------------------------------
    // countAll()
    // ----------------------------------------------------------------

    /**
     * countAll() retourne le total en int.
     */
    public function testCountAllReturnsInt(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('COUNT(*) AS cnt FROM cron_runs'))
            ->willReturn(['cnt' => '42']);

        $this->assertSame(42, $model->countAll());
    }

    /**
     * countAll() retourne 0 quand fetchOne renvoie false ou null.
     */
    public function testCountAllReturnsZeroWhenEmpty(): void
    {
        [$mock, $model] = $this->makeMockAndModel();
        $mock->expects($this->once())->method('fetchOne')->willReturn(false);

        $this->assertSame(0, $model->countAll());
    }

    // ----------------------------------------------------------------
    // getPage()
    // ----------------------------------------------------------------

    /**
     * getPage() clamp limit=1 minimum et offset=0 minimum, retourne fetchAll.
     */
    public function testGetPageClampsLimitAndOffset(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $rows = [['id' => 1, 'job_name' => 'purge-accounts']];

        $mock->expects($this->once())
            ->method('fetchAll')
            ->with($this->callback(function (string $sql): bool {
                // Limit et offset clampés positifs injectés dans la requête
                $this->assertStringContainsString('LIMIT 1', $sql);
                $this->assertStringContainsString('OFFSET 0', $sql);
                return true;
            }))
            ->willReturn($rows);

        $result = $model->getPage(-5, -10);
        $this->assertSame($rows, $result);
    }

    /**
     * getPage() passe limit et offset valides tels quels.
     */
    public function testGetPagePassesValidLimitOffset(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('fetchAll')
            ->with($this->callback(function (string $sql): bool {
                $this->assertStringContainsString('LIMIT 25', $sql);
                $this->assertStringContainsString('OFFSET 50', $sql);
                return true;
            }))
            ->willReturn([]);

        $this->assertSame([], $model->getPage(25, 50));
    }

    // ----------------------------------------------------------------
    // getSummary24h()
    // ----------------------------------------------------------------

    /**
     * getSummary24h() retourne les lignes agrégées de fetchAll.
     */
    public function testGetSummary24hReturnsFetchAllResult(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $rows = [
            ['job_name' => 'purge-accounts', 'runs' => '5', 'success' => '4', 'errors' => '1'],
        ];

        $mock->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains("DATE_SUB(NOW(), INTERVAL 24 HOUR)"))
            ->willReturn($rows);

        $this->assertSame($rows, $model->getSummary24h());
    }
}
