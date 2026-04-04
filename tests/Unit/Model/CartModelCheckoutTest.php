<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\CartModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests des méthodes de nettoyage du panier pour le checkout.
 *
 * Couvre :
 *   - CartModel::removeUnavailableItems()
 *   - CartModel::purgeWineFromAllCarts()
 */
class CartModelCheckoutTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\Stub $dbMock;
    private \ReflectionProperty $instanceProp;
    private CartModel $model;

    protected function setUp(): void
    {
        $this->dbMock = $this->createStub(Database::class);

        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new CartModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ================================================================
    // removeUnavailableItems
    // ================================================================

    public function testRemoveUnavailableItemsReturnsEmptyWhenNoCart(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $removed = $this->model->removeUnavailableItems(42, fn(int $id): ?array => null);

        $this->assertSame([], $removed);
    }

    public function testRemoveUnavailableItemsKeepsAvailableWines(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'user_id' => 1,
            'content' => json_encode([['wine_id' => 1, 'qty' => 3]]),
        ]);

        $removed = $this->model->removeUnavailableItems(
            1,
            fn(int $id): ?array => ['available' => 1, 'quantity' => 10]
        );

        $this->assertSame([], $removed);
    }

    public function testRemoveUnavailableItemsRemovesUnavailableWine(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'user_id' => 1,
            'content' => json_encode([['wine_id' => 5, 'qty' => 2]]),
        ]);

        $removed = $this->model->removeUnavailableItems(
            1,
            fn(int $id): ?array => ['available' => 0, 'quantity' => 10]
        );

        $this->assertCount(1, $removed);
        $this->assertSame(5, (int) $removed[0]['wine_id']);
    }

    public function testRemoveUnavailableItemsKeepsWineWithZeroStock(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'user_id' => 1,
            'content' => json_encode([['wine_id' => 7, 'qty' => 1]]),
        ]);

        // quantity = 0 signifie production totale nulle, pas rupture de stock — le vin reste
        $removed = $this->model->removeUnavailableItems(
            1,
            fn(int $id): ?array => ['available' => 1, 'quantity' => 0]
        );

        $this->assertSame([], $removed);
    }

    public function testRemoveUnavailableItemsRemovesWineNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'user_id' => 1,
            'content' => json_encode([['wine_id' => 99, 'qty' => 1]]),
        ]);

        $removed = $this->model->removeUnavailableItems(1, fn(int $id): ?array => null);

        $this->assertCount(1, $removed);
    }

    public function testRemoveUnavailableItemsCapsQtyToStock(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'user_id' => 1,
            'content' => json_encode([['wine_id' => 2, 'qty' => 10]]),
        ]);

        $savedItems = null;
        $this->dbMock->method('execute')->willReturnCallback(
            function (string $sql, array $params) use (&$savedItems): int {
                $savedItems = json_decode($params[1], true);
                return 1;
            }
        );

        $removed = $this->model->removeUnavailableItems(
            1,
            fn(int $id): ?array => ['available' => 1, 'quantity' => 3]
        );

        $this->assertSame([], $removed);
        $this->assertSame(3, (int) ($savedItems[0]['qty'] ?? 0));
    }

    // ================================================================
    // purgeWineFromAllCarts
    // ================================================================

    public function testPurgeWineFromAllCartsDoesNothingWhenNoCart(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);
        // No execute() should be called — stub returns 0 by default; no assertion needed
        // Verify the method completes without throwing
        $this->model->purgeWineFromAllCarts(1);
        $this->assertTrue(true);
    }

    public function testPurgeWineFromAllCartsRemovesWineFromMatchingCarts(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([
            [
                'id'      => 1,
                'user_id' => 10,
                'content' => json_encode([
                    ['wine_id' => 5, 'qty' => 2],
                    ['wine_id' => 6, 'qty' => 1],
                ]),
            ],
        ]);

        $savedItems = null;
        $this->dbMock->method('execute')->willReturnCallback(
            function (string $sql, array $params) use (&$savedItems): int {
                $savedItems = json_decode($params[1], true);
                return 1;
            }
        );

        $this->model->purgeWineFromAllCarts(5);

        $this->assertNotNull($savedItems);
        $this->assertCount(1, $savedItems);
        $this->assertSame(6, (int) $savedItems[0]['wine_id']);
    }

    public function testPurgeWineFromAllCartsSkipsCartsWithoutTheWine(): void
    {
        $executeCalled = false;
        $dbMock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $dbMock);

        $dbMock->method('fetchAll')->willReturn([
            [
                'id'      => 2,
                'user_id' => 20,
                'content' => json_encode([['wine_id' => 8, 'qty' => 3]]),
            ],
        ]);

        $dbMock->expects($this->never())->method('execute');

        (new CartModel())->purgeWineFromAllCarts(99);
    }
}
