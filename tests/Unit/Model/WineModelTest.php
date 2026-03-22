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
}
