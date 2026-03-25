<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\PricingModel;
use PHPUnit\Framework\TestCase;

class PricingModelTest extends TestCase
{
    private Database $dbMock;
    private PricingModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new PricingModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // getAll
    // ----------------------------------------------------------------

    public function testGetAllFetchesAllRules(): void
    {
        $rows = [
            ['id' => 1, 'format' => 'bottle', 'min_quantity' => 1, 'delivery_price' => 7.50],
            ['id' => 2, 'format' => 'bottle', 'min_quantity' => 6, 'delivery_price' => 5.00],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with($this->stringContains('ORDER BY format ASC, min_quantity ASC'))
            ->willReturn($rows);

        $result = $this->model->getAll();
        $this->assertCount(2, $result);
    }

    // ----------------------------------------------------------------
    // update
    // ----------------------------------------------------------------

    public function testUpdateExecutesCorrectQuery(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('UPDATE'),
                $this->callback(function (array $params): bool {
                    // 7 params + id = 7 total: delivery, withdrawal, label(json), active, min, max, id
                    $this->assertCount(7, $params);
                    $this->assertSame(1, $params[6]); // id
                    $this->assertSame(7.5, $params[0]); // deliveryPrice
                    $this->assertSame(0.0, $params[1]); // withdrawalPrice
                    return true;
                })
            );

        $this->model->update(1, 7.5, 0.0, 'Livraison', 'Delivery', true, 1, null);
    }

    public function testUpdateEncodesLabelAsJson(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->anything(),
                $this->callback(function (array $params): bool {
                    $label = json_decode($params[2], true);
                    $this->assertSame('Livraison', $label['fr']);
                    $this->assertSame('Delivery', $label['en']);
                    return true;
                })
            );

        $this->model->update(1, 5.0, 0.0, 'Livraison', 'Delivery', true, 1, 5);
    }

    public function testUpdateInactivePassesZero(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->anything(),
                $this->callback(function (array $params): bool {
                    $this->assertSame(0, $params[3]); // active = false → 0
                    return true;
                })
            );

        $this->model->update(2, 0.0, 0.0, 'Gratuit', 'Free', false, 0, null);
    }
}
