<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\FeatureFlagsModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeatureFlagsModel::class)]
final class FeatureFlagsModelTest extends TestCase
{
    private Database $db;
    private FeatureFlagsModel $model;

    protected function setUp(): void
    {
        $this->db    = $this->createStub(Database::class);
        $this->model = new FeatureFlagsModel();

        $ref = new \ReflectionProperty(FeatureFlagsModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($this->model, $this->db);
    }

    private function injectDb(Database $db): void
    {
        $ref = new \ReflectionProperty(FeatureFlagsModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($this->model, $db);
    }

    public function testGetAllReturnsMappedRows(): void
    {
        $rows = [
            ['key' => 'foo', 'value' => 'true', 'type' => 'bool', 'label' => 'Foo', 'description' => null, 'updated_at' => null, 'updated_by' => null],
            ['key' => 'bar', 'value' => '42',   'type' => 'int',  'label' => 'Bar', 'description' => null, 'updated_at' => null, 'updated_by' => null],
        ];
        $this->db->method('fetchAll')->willReturn($rows);

        $result = $this->model->getAll();

        $this->assertCount(2, $result);
        $this->assertSame('foo', $result[0]['key']);
        $this->assertSame('bar', $result[1]['key']);
    }

    public function testGetAllReturnsEmptyArrayWhenFetchAllReturnsEmpty(): void
    {
        $this->db->method('fetchAll')->willReturn([]);

        $result = $this->model->getAll();

        $this->assertSame([], $result);
    }

    public function testFindByKeyReturnsRowWhenFound(): void
    {
        $row = ['key' => 'foo', 'value' => 'true', 'type' => 'bool', 'label' => 'Foo', 'description' => null, 'updated_at' => null, 'updated_by' => null];
        $this->db->method('fetchOne')->willReturn($row);

        $result = $this->model->findByKey('foo');

        $this->assertSame($row, $result);
    }

    public function testFindByKeyReturnsNullWhenNotFound(): void
    {
        $this->db->method('fetchOne')->willReturn(false);

        $result = $this->model->findByKey('unknown');

        $this->assertNull($result);
    }

    public function testUpdateValueCallsQuery(): void
    {
        $db   = $this->createMock(Database::class);
        $stmt = $this->createStub(\PDOStatement::class);
        $db->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('UPDATE'),
                $this->equalTo(['false', 42, 'foo'])
            )
            ->willReturn($stmt);
        $this->injectDb($db);

        $this->model->updateValue('foo', 'false', 42);
    }
}
