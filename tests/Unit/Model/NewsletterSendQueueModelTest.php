<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\NewsletterSendQueueModel;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour NewsletterSendQueueModel.
 */
class NewsletterSendQueueModelTest extends TestCase
{
    private function mockDb(array $methods = []): Database
    {
        return $this->createStub(Database::class);
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

        $model = new NewsletterSendQueueModel();
        $ref   = new \ReflectionProperty(NewsletterSendQueueModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);

        $model->enqueue(1, 42, 'test@example.com', 'Jean Dupont', 'tok123', 'fr');
    }

    public function testGetPendingReturnsFetchAll(): void
    {
        $expected = [
            ['id' => 1, 'newsletter_id' => 1, 'wine_id' => 42, 'recipient_email' => 'a@b.com',
             'recipient_name' => 'A', 'unsub_token' => 't', 'lang' => 'fr'],
        ];

        $db = $this->createStub(Database::class);
        $db->method('fetchAll')->willReturn($expected);

        $model = new NewsletterSendQueueModel();
        $ref   = new \ReflectionProperty(NewsletterSendQueueModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);

        $result = $model->getPending(10);
        $this->assertSame($expected, $result);
    }

    public function testGetPendingEmptyReturnsEmpty(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchAll')->willReturn([]);

        $model = new NewsletterSendQueueModel();
        $ref   = new \ReflectionProperty(NewsletterSendQueueModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);

        $this->assertSame([], $model->getPending(30));
    }

    public function testMarkSentExecutesUpdate(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains("status = 'sent'"),
                [':id' => 7]
            );

        $model = new NewsletterSendQueueModel();
        $ref   = new \ReflectionProperty(NewsletterSendQueueModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);

        $model->markSent(7);
    }

    public function testMarkFailedExecutesUpdate(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains("status = 'failed'"),
                [':id' => 3]
            );

        $model = new NewsletterSendQueueModel();
        $ref   = new \ReflectionProperty(NewsletterSendQueueModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);

        $model->markFailed(3);
    }

    public function testCountPendingReturnsCnt(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(['cnt' => '5']);

        $model = new NewsletterSendQueueModel();
        $ref   = new \ReflectionProperty(NewsletterSendQueueModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);

        $this->assertSame(5, $model->countPending());
    }

    public function testCountPendingReturnZeroWhenFetchFails(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(false);

        $model = new NewsletterSendQueueModel();
        $ref   = new \ReflectionProperty(NewsletterSendQueueModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);

        $this->assertSame(0, $model->countPending());
    }

    public function testRefreshCampaignStatsExecutesUpdate(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('UPDATE newsletters'),
                $this->arrayHasKey(':id1')
            );

        $model = new NewsletterSendQueueModel();
        $ref   = new \ReflectionProperty(NewsletterSendQueueModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);

        $model->refreshCampaignStats(1);
    }
}
