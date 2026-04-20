<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\GoogleLinkTokenModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour GoogleLinkTokenModel — méthodes isConsumed() et findByHash().
 * La BDD est mockée — aucune connexion réelle requise.
 */
class GoogleLinkTokenModelTest extends TestCase
{
    private \ReflectionProperty $instanceProp;
    private Database $dbMock;
    private GoogleLinkTokenModel $model;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);
        $this->model = new GoogleLinkTokenModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // isConsumed()
    // ----------------------------------------------------------------

    public function testIsConsumedReturnsTrueWhenConsumedAtSet(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['consumed_at' => '2026-04-20 10:00:00']);

        $this->assertTrue($this->model->isConsumed('any-token'));
    }

    public function testIsConsumedReturnsFalseWhenConsumedAtNull(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['consumed_at' => null]);

        $this->assertFalse($this->model->isConsumed('any-token'));
    }

    public function testIsConsumedReturnsNullWhenTokenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->isConsumed('unknown-token'));
    }

    // ----------------------------------------------------------------
    // findByHash()
    // ----------------------------------------------------------------

    public function testFindByHashReturnsRecordWhenTokenExists(): void
    {
        $record = ['id' => 1, 'account_id' => 42, 'google_id' => 'g-abc', 'consumed_at' => null];
        $this->dbMock->method('fetchOne')->willReturn($record);

        $result = $this->model->findByHash('any-token');

        $this->assertSame($record, $result);
    }

    public function testFindByHashReturnsFalseWhenTokenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertFalse($this->model->findByHash('unknown-token'));
    }
}
