<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Database;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\ConcreteModel;

require_once __DIR__ . '/../Stubs/ConcreteModel.php';

class ModelTest extends TestCase
{
    private Database $dbMock;
    private ConcreteModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new ConcreteModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    public function testFindByIdReturnsFetchedRow(): void
    {
        $expected = ['id' => 1, 'name' => 'Bordeaux'];
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT * FROM items WHERE id = ?', [1])
            ->willReturn($expected);

        $result = $this->model->findById(1);

        $this->assertSame($expected, $result);
    }

    public function testFindByIdReturnsFalseWhenNotFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertFalse($this->model->findById(99));
    }

    public function testFindAllWithoutWhere(): void
    {
        $rows = [['id' => 1], ['id' => 2]];
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with('SELECT * FROM items', [])
            ->willReturn($rows);

        $this->assertSame($rows, $this->model->findAll());
    }

    public function testFindAllWithWhere(): void
    {
        $rows = [['id' => 1]];
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with('SELECT * FROM items WHERE active = ?', [1])
            ->willReturn($rows);

        $this->assertSame($rows, $this->model->findAll('active = ?', [1]));
    }

    public function testDeleteExecutesQuery(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->with('DELETE FROM items WHERE id = ?', [5])
            ->willReturn(1);

        $affected = $this->model->delete(5);

        $this->assertSame(1, $affected);
    }
}
