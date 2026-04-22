<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\NewsletterSendQueueModel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour NewsletterSendQueueModel.
 */
#[AllowMockObjectsWithoutExpectations]
class NewsletterSendQueueModelTest extends TestCase
{
    private function makeModel(Database $db): NewsletterSendQueueModel
    {
        $model = new NewsletterSendQueueModel();
        $ref   = new \ReflectionProperty(NewsletterSendQueueModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);
        return $model;
    }

    public function testEnqueueExecutesInsert(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT INTO newsletter_send_queue'),
                $this->arrayHasKey(':email')
            );

        $this->makeModel($db)->enqueue(1, 42, 'test@example.com', 'Jean Dupont', 'tok123', 'fr');
    }

    public function testEnqueueAcceptsNullWineId(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT INTO newsletter_send_queue'),
                $this->callback(fn($p) => array_key_exists(':wine_id', $p) && $p[':wine_id'] === null)
            );

        $this->makeModel($db)->enqueue(5, null, 'a@b.com', 'Admin Sub', 'tok999', 'en');
    }

    public function testGetPendingReturnsFetchAll(): void
    {
        $expected = [
            ['id' => 1, 'newsletter_id' => 1, 'wine_id' => 42, 'recipient_email' => 'a@b.com',
             'recipient_name' => 'A', 'unsub_token' => 't', 'lang' => 'fr'],
        ];

        $db = $this->createStub(Database::class);
        $db->method('fetchAll')->willReturn($expected);

        $this->assertSame($expected, $this->makeModel($db)->getPending(10));
    }

    public function testGetPendingEmptyReturnsEmpty(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->makeModel($db)->getPending(30));
    }

    public function testMarkSentIncrementsAndDeletes(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (string $sql, array $params): int {
                static $call = 0;
                $call++;
                if ($call === 1) {
                    $this->assertStringContainsString('sent_count = sent_count + 1', $sql);
                    $this->assertSame([':nl_id' => 10], $params);
                } else {
                    $this->assertStringContainsString('DELETE FROM newsletter_send_queue', $sql);
                    $this->assertSame([':id' => 7], $params);
                }
                return 1;
            });

        $this->makeModel($db)->markSent(7, 10);
    }

    public function testMarkFailedIncrementsAndDeletes(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (string $sql, array $params): int {
                static $call = 0;
                $call++;
                if ($call === 1) {
                    $this->assertStringContainsString('failed_count = failed_count + 1', $sql);
                    $this->assertSame([':nl_id' => 10], $params);
                } else {
                    $this->assertStringContainsString('DELETE FROM newsletter_send_queue', $sql);
                    $this->assertSame([':id' => 3], $params);
                }
                return 1;
            });

        $this->makeModel($db)->markFailed(3, 10);
    }

    public function testCountPendingReturnsCnt(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(['cnt' => '5']);

        $this->assertSame(5, $this->makeModel($db)->countPending());
    }

    public function testCountPendingReturnZeroWhenFetchFails(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(false);

        $this->assertSame(0, $this->makeModel($db)->countPending());
    }

    // ----------------------------------------------------------------
    // getPendingSegmented
    // ----------------------------------------------------------------

    public function testGetPendingSegmentedReturnsFetchAll(): void
    {
        $expected = [
            ['id' => 5, 'newsletter_id' => 2, 'wine_id' => 10,
             'recipient_email' => 'seg@b.com', 'recipient_name' => 'Seg',
             'unsub_token' => 'u', 'lang' => 'fr'],
        ];

        $db = $this->createStub(Database::class);
        $db->method('fetchAll')->willReturn($expected);

        $this->assertSame($expected, $this->makeModel($db)->getPendingSegmented(5));
    }

    public function testGetPendingSegmentedReturnsEmptyWhenNoMatch(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->makeModel($db)->getPendingSegmented(20));
    }
}
