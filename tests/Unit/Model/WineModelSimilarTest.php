<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\WineModel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour WineModel::getSimilar() et WineModel::getForCartCrossSell().
 */
#[AllowMockObjectsWithoutExpectations]
class WineModelSimilarTest extends TestCase
{
    private Database $dbMock;
    private WineModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);
        $this->model = new WineModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // getSimilar
    // ----------------------------------------------------------------

    public function testGetSimilarReturnsEmptyArrayWhenNoWines(): void
    {
        // getSimilar fait 2 requêtes quand la 1re (même couleur) retourne moins que $limit
        $this->dbMock
            ->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturn([]);

        $result = $this->model->getSimilar(1, 'red', 15.0, 3);

        $this->assertSame([], $result);
    }

    public function testGetSimilarReturnsSameColorResultsWhenEnough(): void
    {
        $rows = [
            ['id' => 2, 'label_name' => 'A', 'wine_color' => 'red', 'price' => 14.0],
            ['id' => 3, 'label_name' => 'B', 'wine_color' => 'red', 'price' => 16.0],
            ['id' => 4, 'label_name' => 'C', 'wine_color' => 'red', 'price' => 13.0],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($rows);

        $result = $this->model->getSimilar(1, 'red', 15.0, 3);

        $this->assertCount(3, $result);
        $this->assertSame('A', $result[0]['label_name']);
    }

    public function testGetSimilarCompleteWithOtherColorsWhenInsufficient(): void
    {
        $sameColor     = [['id' => 2, 'label_name' => 'A', 'wine_color' => 'red', 'price' => 14.0]];
        $otherColorIds = [['id' => 5]];
        $otherColorRows = [['id' => 5, 'label_name' => 'X', 'wine_color' => 'white', 'price' => 12.0]];

        $this->dbMock
            ->expects($this->exactly(3))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls($sameColor, $otherColorIds, $otherColorRows);

        $result = $this->model->getSimilar(1, 'red', 15.0, 3);

        $this->assertCount(2, $result);
        $this->assertSame('A', $result[0]['label_name']);
        $this->assertSame('X', $result[1]['label_name']);
    }

    public function testGetSimilarSecondQueryExcludesFoundIds(): void
    {
        $sameColor = [['id' => 2, 'label_name' => 'A', 'wine_color' => 'red', 'price' => 14.0]];

        $this->dbMock
            ->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnCallback(function (string $sql, array $params) use ($sameColor): array {
                if (str_contains($sql, 'wine_color != ?')) {
                    // Les IDs exclus doivent contenir l'id original (1) et le trouvé (2)
                    $this->assertContains(1, $params);
                    $this->assertContains(2, $params);
                    return [];
                }
                return $sameColor;
            });

        $this->model->getSimilar(1, 'red', 15.0, 3);
    }

    // ----------------------------------------------------------------
    // getForCartCrossSell
    // ----------------------------------------------------------------

    public function testGetForCartCrossSellExcludesGivenIds(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('NOT IN'),
                $this->callback(fn(array $p): bool => in_array(1, $p, true) && in_array(2, $p, true))
            )
            ->willReturn([]);

        $result = $this->model->getForCartCrossSell([1, 2], 4);

        $this->assertSame([], $result);
    }

    public function testGetForCartCrossSellWithEmptyExcludeIds(): void
    {
        $rows = [['id' => 3, 'label_name' => 'Vin X', 'wine_color' => 'white', 'price' => 12.0]];

        $this->dbMock
            ->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [['id' => 3]],
                $rows
            );

        $result = $this->model->getForCartCrossSell([], 4);

        $this->assertCount(1, $result);
        $this->assertSame('Vin X', $result[0]['label_name']);
    }

    public function testGetForCartCrossSellReturnsUpToLimit(): void
    {
        $wineIds = array_map(fn(int $i): array => ['id' => $i], range(10, 13));
        $wines   = array_map(
            fn(int $i): array => ['id' => $i, 'label_name' => "Vin {$i}", 'wine_color' => 'red'],
            range(10, 13)
        );

        $this->dbMock
            ->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls($wineIds, $wines);

        $result = $this->model->getForCartCrossSell([1, 2], 4);

        $this->assertCount(4, $result);
    }
}
