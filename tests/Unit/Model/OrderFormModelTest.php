<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\OrderFormModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class OrderFormModelTest extends TestCase
{
    private Database $dbMock;
    private OrderFormModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new OrderFormModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // getAll
    // ----------------------------------------------------------------

    public function testGetAllReturnsRows(): void
    {
        $rows = [
            ['id' => 2, 'year' => 2025, 'label' => null,  'filename' => '2025_prices_abc.pdf'],
            ['id' => 1, 'year' => 2024, 'label' => 'V2',  'filename' => '2024_prices_V2_def.pdf'],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('ORDER BY year DESC'))
            ->willReturn($rows);

        $result = $this->model->getAll();

        $this->assertCount(2, $result);
        $this->assertSame(2025, $result[0]['year']);
    }

    // ----------------------------------------------------------------
    // countAll
    // ----------------------------------------------------------------

    public function testCountAllReturnsInt(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('COUNT(*)'))
            ->willReturn(['total' => 5]);

        $this->assertSame(5, $this->model->countAll());
    }

    public function testCountAllReturnsZeroOnFalse(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertSame(0, $this->model->countAll());
    }

    // ----------------------------------------------------------------
    // getPaginated
    // ----------------------------------------------------------------

    public function testGetPaginatedPassesCorrectOffsetAndLimit(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('LIMIT ? OFFSET ?'),
                $this->equalTo([10, 20])
            )
            ->willReturn([]);

        // page 3, perPage 10 → offset = (3-1)*10 = 20
        $this->model->getPaginated(3, 10);
    }

    public function testGetPaginatedPage1HasOffsetZero(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->anything(),
                $this->equalTo([25, 0])
            )
            ->willReturn([]);

        $this->model->getPaginated(1, 25);
    }

    // ----------------------------------------------------------------
    // findById
    // ----------------------------------------------------------------

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = ['id' => 3, 'year' => 2023, 'label' => null, 'filename' => 'x.pdf'];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('WHERE id = ?'), [3])
            ->willReturn($row);

        $result = $this->model->findById(3);
        $this->assertSame($row, $result);
    }

    public function testFindByIdReturnsFalseWhenNotFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertFalse($this->model->findById(999));
    }

    // ----------------------------------------------------------------
    // findByIdOrNull
    // ----------------------------------------------------------------

    public function testFindByIdOrNullReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'year' => 2024, 'label' => null, 'filename' => 'y.pdf'];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($row);

        $result = $this->model->findByIdOrNull(1);
        $this->assertSame($row, $result);
    }

    public function testFindByIdOrNullReturnsNullWhenNotFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertNull($this->model->findByIdOrNull(0));
    }

    // ----------------------------------------------------------------
    // getLatest
    // ----------------------------------------------------------------

    public function testGetLatestReturnsLatestRow(): void
    {
        $row = ['id' => 5, 'year' => 2026, 'label' => null, 'filename' => 'latest.pdf'];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('ORDER BY uploaded_at DESC, id DESC LIMIT 1'))
            ->willReturn($row);

        $this->assertSame($row, $this->model->getLatest());
    }

    public function testGetLatestReturnsNullWhenEmpty(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertNull($this->model->getLatest());
    }

    // ----------------------------------------------------------------
    // create
    // ----------------------------------------------------------------

    public function testCreateInsertsAndReturnsId(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('insert')
            ->with(
                $this->stringContains('INSERT INTO'),
                $this->equalTo([2025, null, '2025_prices_token.pdf'])
            )
            ->willReturn('7');

        $id = $this->model->create(2025, null, '2025_prices_token.pdf');
        $this->assertSame(7, $id);
    }

    public function testCreateWithLabelPassesLabel(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('insert')
            ->with(
                $this->anything(),
                $this->equalTo([2024, 'V2', '2024_prices_V2_abc.pdf'])
            )
            ->willReturn('3');

        $this->model->create(2024, 'V2', '2024_prices_V2_abc.pdf');
    }

    // ----------------------------------------------------------------
    // delete
    // ----------------------------------------------------------------

    public function testDeleteExecutesAndReturnsId(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('DELETE FROM'),
                $this->equalTo([4])
            );

        $result = $this->model->delete(4);
        $this->assertSame(4, $result);
    }
}
