<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\EmailRateLogModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour EmailRateLogModel.
 * Couvre log(), getSummary(), countAll(), getPage(), getRecentLogs().
 */
#[AllowMockObjectsWithoutExpectations]
class EmailRateLogModelTest extends TestCase
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
     * Crée un mock strict de Database et un EmailRateLogModel.
     *
     * @return array{0: \PHPUnit\Framework\MockObject\MockObject, 1: EmailRateLogModel}
     */
    private function makeMockAndModel(): array
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        return [$mock, new EmailRateLogModel()];
    }

    // ----------------------------------------------------------------
    // log()
    // ----------------------------------------------------------------

    /**
     * log() exécute INSERT puis DELETE de purge, tronque subject/type/error.
     */
    public function testLogInsertsRowAndPurgesOld(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $longSubject = str_repeat('s', 600);
        $longType    = str_repeat('t', 60);
        $longError   = str_repeat('e', 600);

        $calls = [];
        $mock->method('execute')->willReturnCallback(
            function (string $sql, array $params = []) use (&$calls): int {
                $calls[] = ['sql' => $sql, 'params' => $params];
                return 0;
            }
        );

        $model->log('from@a.com', 'to@b.com', $longSubject, false, $longError, $longType);

        $this->assertCount(2, $calls);
        $this->assertStringContainsString('INSERT INTO email_rate_log', $calls[0]['sql']);
        $this->assertSame('from@a.com', $calls[0]['params'][':from']);
        $this->assertSame('to@b.com', $calls[0]['params'][':to']);
        $this->assertSame(500, mb_strlen($calls[0]['params'][':subject']));
        $this->assertSame(40, mb_strlen($calls[0]['params'][':type']));
        $this->assertSame(0, $calls[0]['params'][':success']);
        $this->assertSame(500, mb_strlen($calls[0]['params'][':error']));

        $this->assertStringContainsString('DELETE FROM email_rate_log WHERE sent_at <', $calls[1]['sql']);
    }

    /**
     * log() avec success=true passe 1 et errorMessage=null transforme en null dans :error.
     */
    public function testLogWithSuccessTrueAndNullError(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $capturedParams = null;
        $mock->method('execute')->willReturnCallback(
            function (string $sql, array $params = []) use (&$capturedParams): int {
                if (str_contains($sql, 'INSERT INTO')) {
                    $capturedParams = $params;
                }
                return 0;
            }
        );

        $model->log('from@a.com', 'to@b.com', 'Hello', true, null);

        $this->assertSame(1, $capturedParams[':success']);
        $this->assertNull($capturedParams[':error']);
        $this->assertSame('other', $capturedParams[':type']);
    }

    // ----------------------------------------------------------------
    // getSummary()
    // ----------------------------------------------------------------

    /**
     * getSummary() retourne l'agrégat fetchAll.
     */
    public function testGetSummaryReturnsFetchAllResult(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $rows = [
            ['from_address' => 'no-reply@a.com', 'total_sent' => '42', 'success_count' => '40'],
        ];

        $mock->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains("DATE_SUB(NOW(), INTERVAL 25 HOUR)"))
            ->willReturn($rows);

        $this->assertSame($rows, $model->getSummary());
    }

    // ----------------------------------------------------------------
    // countAll()
    // ----------------------------------------------------------------

    /**
     * countAll() retourne le total casté en int.
     */
    public function testCountAllReturnsInt(): void
    {
        [$mock, $model] = $this->makeMockAndModel();
        $mock->method('fetchOne')->willReturn(['cnt' => '128']);

        $this->assertSame(128, $model->countAll());
    }

    /**
     * countAll() retourne 0 quand fetchOne renvoie false.
     */
    public function testCountAllReturnsZeroWhenEmpty(): void
    {
        [$mock, $model] = $this->makeMockAndModel();
        $mock->method('fetchOne')->willReturn(false);

        $this->assertSame(0, $model->countAll());
    }

    // ----------------------------------------------------------------
    // getPage()
    // ----------------------------------------------------------------

    /**
     * getPage() clamp limit et offset et les injecte dans la requête.
     */
    public function testGetPageClampsLimitAndOffset(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('fetchAll')
            ->with($this->callback(function (string $sql): bool {
                $this->assertStringContainsString('LIMIT 1', $sql);
                $this->assertStringContainsString('OFFSET 0', $sql);
                return true;
            }))
            ->willReturn([]);

        $this->assertSame([], $model->getPage(-99, -1));
    }

    /**
     * getPage() retourne les lignes de fetchAll.
     */
    public function testGetPageReturnsRows(): void
    {
        [$mock, $model] = $this->makeMockAndModel();
        $rows = [['id' => 1, 'to_address' => 'user@a.com']];

        $mock->method('fetchAll')->willReturn($rows);

        $this->assertSame($rows, $model->getPage(50, 10));
    }

    // ----------------------------------------------------------------
    // getRecentLogs()
    // ----------------------------------------------------------------

    /**
     * getRecentLogs() délègue à getPage(limit, 0) avec la valeur par défaut 100.
     */
    public function testGetRecentLogsDelegatesToGetPageWithDefault(): void
    {
        [$mock, $model] = $this->makeMockAndModel();
        $rows = [['id' => 9]];

        $mock->expects($this->once())
            ->method('fetchAll')
            ->with($this->callback(function (string $sql): bool {
                $this->assertStringContainsString('LIMIT 100', $sql);
                $this->assertStringContainsString('OFFSET 0', $sql);
                return true;
            }))
            ->willReturn($rows);

        $this->assertSame($rows, $model->getRecentLogs());
    }
}
