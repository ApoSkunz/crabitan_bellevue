<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\PricingRuleModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour PricingRuleModel.
 */
class PricingRuleModelTest extends TestCase
{
    private Database $dbMock;
    private PricingRuleModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new PricingRuleModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // findForQuantity
    // ----------------------------------------------------------------

    public function testFindForQuantityReturnsMatchingRule(): void
    {
        $row = ['delivery_price' => '15.00', 'price_type' => 'fixed'];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('min_quantity <='),
                $this->equalTo(['bottle', 24, 24])
            )
            ->willReturn($row);

        $result = $this->model->findForQuantity(24);
        $this->assertSame($row, $result);
    }

    public function testFindForQuantityReturnsNullWhenNoRow(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->model->findForQuantity(5);
        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // findNextTierFor
    // ----------------------------------------------------------------

    public function testFindNextTierForReturnsNextRule(): void
    {
        $nextRow = [
            'id'             => 2,
            'min_quantity'   => 24,
            'delivery_price' => '15.00',
            'price_type'     => 'fixed',
            'label'          => '{"fr":"2 caisses","en":"2 cases"}',
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('min_quantity >'),
                $this->equalTo(['bottle', 12])
            )
            ->willReturn($nextRow);

        $result = $this->model->findNextTierFor(12);
        $this->assertSame($nextRow, $result);
    }

    public function testFindNextTierForReturnsNullWhenAtTopTier(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->model->findNextTierFor(500);
        $this->assertNull($result);
    }

    public function testFindNextTierForPassesFormatParameter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->anything(),
                $this->equalTo(['bib', 6])
            )
            ->willReturn(false);

        $this->model->findNextTierFor(6, 'bib');
    }

    // ----------------------------------------------------------------
    // computeDeliveryDiscount
    // ----------------------------------------------------------------

    public function testComputeDeliveryDiscountFixedRate(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(['delivery_price' => '15.00', 'price_type' => 'fixed']);

        $discount = $this->model->computeDeliveryDiscount(24);
        $this->assertEqualsWithDelta(15.0, $discount, 0.001);
    }

    public function testComputeDeliveryDiscountPerBottle(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(['delivery_price' => '1.30', 'price_type' => 'per_bottle']);

        $discount = $this->model->computeDeliveryDiscount(48);
        $this->assertEqualsWithDelta(62.4, $discount, 0.001);
    }

    public function testComputeDeliveryDiscountReturnsZeroForZeroQty(): void
    {
        $discount = $this->model->computeDeliveryDiscount(0);
        $this->assertSame(0.0, $discount);
    }

    // ----------------------------------------------------------------
    // findAllActive
    // ----------------------------------------------------------------

    public function testFindAllActiveReturnsSortedRules(): void
    {
        $rows = [
            ['id' => 1, 'min_quantity' => 1,  'max_quantity' => 23,  'delivery_price' => '0.00',  'price_type' => 'fixed',      'label' => '{"fr":"Moins de 2 caisses","en":"Less than 2 cases"}'],
            ['id' => 2, 'min_quantity' => 24, 'max_quantity' => 35,  'delivery_price' => '15.00', 'price_type' => 'fixed',      'label' => '{"fr":"2 caisses","en":"2 cases"}'],
            ['id' => 3, 'min_quantity' => 36, 'max_quantity' => null, 'delivery_price' => '1.30',  'price_type' => 'per_bottle', 'label' => '{"fr":"3+ caisses","en":"3+ cases"}'],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('ORDER BY min_quantity ASC'),
                $this->equalTo(['bottle'])
            )
            ->willReturn($rows);

        $result = $this->model->findAllActive();
        $this->assertCount(3, $result);
        $this->assertSame(1, $result[0]['min_quantity']);
        $this->assertSame(24, $result[1]['min_quantity']);
    }

    public function testFindAllActivePassesFormatParam(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->anything(),
                $this->equalTo(['bib'])
            )
            ->willReturn([]);

        $result = $this->model->findAllActive('bib');
        $this->assertIsArray($result);
    }
}
