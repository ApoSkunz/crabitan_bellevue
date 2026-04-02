<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\NewsletterSubscriptionModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour NewsletterSubscriptionModel.
 * La BDD est mockée — aucune connexion réelle.
 */
#[AllowMockObjectsWithoutExpectations]
class NewsletterSubscriptionModelTest extends TestCase
{
    private Database $dbMock;
    private NewsletterSubscriptionModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new NewsletterSubscriptionModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // findPendingByTokenHash
    // ----------------------------------------------------------------

    public function testFindPendingByTokenHashReturnsMappedRow(): void
    {
        $row = [
            'id'                          => 1,
            'email'                       => 'test@example.com',
            'lang'                        => 'fr',
            'newsletter_token_hash'       => 'abc123',
            'newsletter_token_expires_at' => '2099-12-31 23:59:59',
            'newsletter_confirmed'        => 0,
        ];
        $this->dbMock->method('fetchOne')->willReturn($row);

        $result = $this->model->findPendingByTokenHash('abc123');

        $this->assertSame($row, $result);
    }

    public function testFindPendingByTokenHashReturnsNullOnFalse(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $result = $this->model->findPendingByTokenHash('unknown');

        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // confirmByTokenHash
    // ----------------------------------------------------------------

    public function testConfirmByTokenHashCallsExecuteWithCorrectParams(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('newsletter_confirmed'),
                ['192.168.1.1', 'abc123']
            );

        $this->model->confirmByTokenHash('abc123', '192.168.1.1');
    }

    // ----------------------------------------------------------------
    // upsertPending
    // ----------------------------------------------------------------

    public function testUpsertPendingCallsExecute(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT INTO'),
                ['test@example.com', 'hash123', '2099-01-01 00:00:00', '127.0.0.1', 'fr']
            );

        $this->model->upsertPending(
            'test@example.com',
            'hash123',
            '2099-01-01 00:00:00',
            '127.0.0.1',
            'fr'
        );
    }

    // ----------------------------------------------------------------
    // findByEmail
    // ----------------------------------------------------------------

    public function testFindByEmailReturnsNullOnFalse(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $result = $this->model->findByEmail('nobody@example.com');

        $this->assertNull($result);
    }

    public function testFindByEmailReturnsMappedRow(): void
    {
        $row = [
            'id'                   => 5,
            'email'                => 'sub@example.com',
            'lang'                 => 'fr',
            'newsletter_confirmed' => 0,
            'attempts_24h'         => 1,
            'last_attempt_at'      => '2026-04-01 10:00:00',
        ];
        $this->dbMock->method('fetchOne')->willReturn($row);

        $result = $this->model->findByEmail('sub@example.com');

        $this->assertSame($row, $result);
    }

    // ----------------------------------------------------------------
    // countRecentAttempts
    // ----------------------------------------------------------------

    public function testCountRecentAttemptsReturnsZeroWhenNoRow(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertSame(0, $this->model->countRecentAttempts('nobody@example.com'));
    }

    public function testCountRecentAttemptsReturnsCurrentCount(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'attempts_24h'    => 2,
            'last_attempt_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $this->assertSame(2, $this->model->countRecentAttempts('test@example.com'));
    }

    public function testCountRecentAttemptsResetsCounterIfOlderThan24h(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'attempts_24h'    => 3,
            'last_attempt_at' => date('Y-m-d H:i:s', strtotime('-25 hours')),
        ]);
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with($this->stringContains('attempts_24h = 0'));

        $count = $this->model->countRecentAttempts('old@example.com');

        $this->assertSame(0, $count);
    }

    // ----------------------------------------------------------------
    // getAllConfirmed
    // ----------------------------------------------------------------

    public function testGetAllConfirmedCallsFetchAll(): void
    {
        $rows = [
            ['id' => 1, 'email' => 'a@example.com', 'lang' => 'fr'],
            ['id' => 2, 'email' => 'b@example.com', 'lang' => 'en'],
        ];
        $this->dbMock->method('fetchAll')->willReturn($rows);

        $result = $this->model->getAllConfirmed();

        $this->assertSame($rows, $result);
    }

    // ----------------------------------------------------------------
    // purgeExpiredPending
    // ----------------------------------------------------------------

    public function testPurgeExpiredPendingReturnsDeletedCount(): void
    {
        $this->dbMock->method('execute')->willReturn(3);

        $count = $this->model->purgeExpiredPending();

        $this->assertSame(3, $count);
    }
}
