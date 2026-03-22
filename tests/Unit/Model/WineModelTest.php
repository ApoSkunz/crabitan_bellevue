<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\WineModel;
use PHPUnit\Framework\TestCase;

class WineModelTest extends TestCase
{
    private Database $dbMock;
    private WineModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new WineModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // getAll
    // ----------------------------------------------------------------

    public function testGetAllReturnsAvailableWines(): void
    {
        $rows = [
            ['id' => 1, 'label_name' => 'Sainte-Croix-du-Mont', 'wine_color' => 'sweet', 'vintage' => 2020],
            ['id' => 2, 'label_name' => 'Bordeaux Rouge', 'wine_color' => 'red', 'vintage' => 2021],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($rows);

        $result = $this->model->getAll();

        $this->assertCount(2, $result);
        $this->assertSame('Sainte-Croix-du-Mont', $result[0]['label_name']);
    }

    public function testGetAllWithColorFilterPassesParam(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('wine_color = ?'),
                $this->equalTo(['sweet'])
            )
            ->willReturn([]);

        $this->model->getAll('sweet');
    }

    public function testGetAllIgnoresInvalidColor(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->logicalNot($this->stringContains('wine_color = ?')),
                $this->equalTo([])
            )
            ->willReturn([]);

        $this->model->getAll('invalid_color');
    }

    public function testGetAllSortPriceAscBuildsCorrectOrder(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('price ASC'),
                $this->anything()
            )
            ->willReturn([]);

        $this->model->getAll(null, 'price_asc');
    }

    public function testGetAllSortVintageDescBuildsCorrectOrder(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('vintage DESC'),
                $this->anything()
            )
            ->willReturn([]);

        $this->model->getAll(null, 'vintage_desc');
    }

    // ----------------------------------------------------------------
    // getBySlug
    // ----------------------------------------------------------------

    public function testGetBySlugReturnsWineWhenFound(): void
    {
        $expected = ['id' => 1, 'slug' => 'sainte-croix-du-mont-2020', 'label_name' => 'Sainte-Croix-du-Mont'];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('WHERE slug = ?'), ['sainte-croix-du-mont-2020'])
            ->willReturn($expected);

        $result = $this->model->getBySlug('sainte-croix-du-mont-2020');

        $this->assertSame($expected, $result);
    }

    public function testGetBySlugReturnsNullWhenNotFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->model->getBySlug('does-not-exist');

        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // getAllByColor
    // ----------------------------------------------------------------

    public function testGetAllByColorGroupsWinesByColor(): void
    {
        $rows = [
            ['id' => 1, 'wine_color' => 'sweet', 'label_name' => 'Sainte-Croix-du-Mont'],
            ['id' => 2, 'wine_color' => 'sweet', 'label_name' => 'Sainte-Croix-du-Mont'],
            ['id' => 3, 'wine_color' => 'red',   'label_name' => 'Bordeaux Rouge'],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($rows);

        $result = $this->model->getAllByColor();

        $this->assertArrayHasKey('sweet', $result);
        $this->assertArrayHasKey('red', $result);
        $this->assertCount(2, $result['sweet']);
        $this->assertCount(1, $result['red']);
    }

    public function testGetAllByColorReturnsEmptyArrayWhenNoWines(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $result = $this->model->getAllByColor();

        $this->assertSame([], $result);
    }

    // ----------------------------------------------------------------
    // getLatest
    // ----------------------------------------------------------------

    public function testGetLatestReturnsRequestedCount(): void
    {
        $rows = [
            ['id' => 5, 'label_name' => 'Bordeaux Blanc'],
            ['id' => 4, 'label_name' => 'Bordeaux Rouge'],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('LIMIT ?'), [2])
            ->willReturn($rows);

        $result = $this->model->getLatest(2);

        $this->assertCount(2, $result);
    }

    // ----------------------------------------------------------------
    // countAll
    // ----------------------------------------------------------------

    public function testCountAllReturnsTotal(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('COUNT(*)'),
                $this->equalTo([])
            )
            ->willReturn(['total' => 7]);

        $result = $this->model->countAll();

        $this->assertSame(7, $result);
    }

    public function testCountAllWithColorPassesParam(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('wine_color = ?'),
                $this->equalTo(['red'])
            )
            ->willReturn(['total' => 3]);

        $result = $this->model->countAll('red');

        $this->assertSame(3, $result);
    }

    public function testCountAllReturnsZeroOnMissingRow(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->model->countAll();

        $this->assertSame(0, $result);
    }

    // ----------------------------------------------------------------
    // countAllByColor
    // ----------------------------------------------------------------

    public function testCountAllByColorNoFilters(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->logicalNot($this->stringContains('WHERE')),
                $this->equalTo([])
            )
            ->willReturn(['total' => 12]);

        $result = $this->model->countAllByColor();

        $this->assertSame(12, $result);
    }

    public function testCountAllByColorWithAvailableFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('available = 1'),
                $this->equalTo([])
            )
            ->willReturn(['total' => 5]);

        $result = $this->model->countAllByColor(null, 'available');

        $this->assertSame(5, $result);
    }

    public function testCountAllByColorWithOutFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('available = 0'),
                $this->equalTo([])
            )
            ->willReturn(['total' => 2]);

        $result = $this->model->countAllByColor(null, 'out');

        $this->assertSame(2, $result);
    }

    public function testCountAllByColorWithColorAndAvail(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->logicalAnd(
                    $this->stringContains('wine_color = ?'),
                    $this->stringContains('available = 1')
                ),
                $this->equalTo(['sweet'])
            )
            ->willReturn(['total' => 4]);

        $result = $this->model->countAllByColor('sweet', 'available');

        $this->assertSame(4, $result);
    }

    // ----------------------------------------------------------------
    // getColorFirstPages
    // ----------------------------------------------------------------

    public function testGetColorFirstPagesReturnsEmptyForZeroPerPage(): void
    {
        $result = $this->model->getColorFirstPages(null, 0);

        $this->assertSame([], $result);
    }

    public function testGetColorFirstPagesSingleColorOnPage1(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['wine_color' => 'white', 'cnt' => 5],
            ]);

        $result = $this->model->getColorFirstPages(null, 10);

        $this->assertSame(['white' => 1], $result);
    }

    public function testGetColorFirstPagesMultipleColorsAcrossPages(): void
    {
        // sweet: 10 wines, white: 10 wines, red: 5 wines — perPage = 10
        // sweet  starts at position 1  → page ceil(1/10)  = 1
        // white  starts at position 11 → page ceil(11/10) = 2
        // red    starts at position 21 → page ceil(21/10) = 3
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['wine_color' => 'sweet', 'cnt' => 10],
                ['wine_color' => 'white', 'cnt' => 10],
                ['wine_color' => 'red',   'cnt' => 5],
            ]);

        $result = $this->model->getColorFirstPages(null, 10);

        $this->assertSame(['sweet' => 1, 'white' => 2, 'red' => 3], $result);
    }

    public function testGetColorFirstPagesWithAvailableFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('available = 1'),
                $this->equalTo([])
            )
            ->willReturn([
                ['wine_color' => 'red', 'cnt' => 3],
            ]);

        $result = $this->model->getColorFirstPages('available', 25);

        $this->assertSame(['red' => 1], $result);
    }

    public function testGetColorFirstPagesWithOutFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('available = 0'),
                $this->equalTo([])
            )
            ->willReturn([]);

        $result = $this->model->getColorFirstPages('out', 25);

        $this->assertSame([], $result);
    }

    public function testGetColorFirstPagesAllOnSamePage(): void
    {
        // 3 wines per page, 1 wine each color — all fit on page 1
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['wine_color' => 'sweet', 'cnt' => 1],
                ['wine_color' => 'white', 'cnt' => 1],
                ['wine_color' => 'red',   'cnt' => 1],
            ]);

        $result = $this->model->getColorFirstPages(null, 3);

        $this->assertSame(['sweet' => 1, 'white' => 1, 'red' => 1], $result);
    }

    // ----------------------------------------------------------------
    // getAllByColor — avail filter
    // ----------------------------------------------------------------

    public function testGetAllByColorWithAvailableFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('available = 1'),
                $this->anything()
            )
            ->willReturn([]);

        $this->model->getAllByColor(null, 'default', 'available');
    }

    public function testGetAllByColorWithOutFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('available = 0'),
                $this->anything()
            )
            ->willReturn([]);

        $this->model->getAllByColor(null, 'default', 'out');
    }

    public function testGetAllByColorWithPagination(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('LIMIT ? OFFSET ?'),
                $this->equalTo([10, 20])
            )
            ->willReturn([]);

        $this->model->getAllByColor(null, 'default', null, 10, 20);
    }
}
