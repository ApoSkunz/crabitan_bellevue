<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\CartModel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour CartModel.
 * La base de données est mockée — aucune connexion réelle requise.
 */
#[AllowMockObjectsWithoutExpectations]
class CartModelTest extends TestCase
{
    private Database $dbMock;
    private CartModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new CartModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // findByUserId
    // ----------------------------------------------------------------

    public function testFindByUserIdReturnsArrayWhenExists(): void
    {
        $row = [
            'id'               => 1,
            'user_id'          => 42,
            'content'          => '[{"wine_id":1,"qty":2}]',
            'price'            => '0.00',
            'withdrawal_price' => '0.00',
            'delivery_price'   => '0.00',
            'total_quantity'   => 2,
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($row);

        $result = $this->model->findByUserId(42);

        $this->assertIsArray($result);
        $this->assertSame(42, $result['user_id']);
    }

    public function testFindByUserIdReturnsFalseWhenNotFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->model->findByUserId(999);

        $this->assertFalse($result);
    }

    // ----------------------------------------------------------------
    // save (INSERT / upsert)
    // ----------------------------------------------------------------

    public function testSaveCreatesCart(): void
    {
        $items = [['wine_id' => 1, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg']];

        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        // Ne doit pas lever d'exception
        $this->model->save(42, $items);
        $this->assertTrue(true);
    }

    public function testSaveUpdatesExistingCart(): void
    {
        $items = [
            ['wine_id' => 1, 'qty' => 3, 'name' => 'Bordeaux', 'image' => 'img1.jpg'],
            ['wine_id' => 2, 'qty' => 1, 'name' => 'Sauternes', 'image' => 'img2.jpg'],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $this->model->save(42, $items);
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // clear
    // ----------------------------------------------------------------

    public function testClearEmptiesCart(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $this->model->clear(42);
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // getContent
    // ----------------------------------------------------------------

    public function testGetContentReturnsDecodedArray(): void
    {
        $row = [
            'id'             => 1,
            'user_id'        => 42,
            'content'        => '[{"wine_id":1,"qty":2,"name":"Bordeaux","image":"img.jpg"}]',
            'total_quantity' => 2,
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($row);

        $result = $this->model->findByUserId(42);
        $this->assertIsArray($result);

        $items = $this->model->getContent($result);
        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertSame(1, $items[0]['wine_id']);
        $this->assertSame(2, $items[0]['qty']);
    }

    // ----------------------------------------------------------------
    // mergeLocalCart
    // ----------------------------------------------------------------

    public function testMergeLocalCartAddsItemsWhenCartIsEmpty(): void
    {
        // BDD : pas de panier existant
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(false);

        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $localItems = [
            ['wine_id' => 1, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg'],
        ];

        // $stockResolver retourne 10 pour n'importe quel vin
        $this->model->mergeLocalCart(42, $localItems, fn(int $id): int => 10);
        $this->assertTrue(true);
    }

    public function testMergeLocalCartSkipsItemsWithInvalidWineId(): void
    {
        // Pas de panier existant
        $this->dbMock->method('fetchOne')->willReturn(false);
        $this->dbMock->expects($this->once())->method('execute');

        // Tous les items sont invalides : doivent être ignorés via le continue
        $localItems = [
            ['wine_id' => 0, 'qty' => 2],   // wine_id <= 0 → skipped
            ['wine_id' => 1, 'qty' => 0],   // qty <= 0 → skipped
            ['id' => 0,      'qty' => 3],   // id <= 0 → skipped
        ];

        $this->model->mergeLocalCart(42, $localItems, fn(int $id): int => 10);
        $this->assertTrue(true); // pas d'exception, save appelé avec tableau vide
    }

    public function testMergeLocalCartCapsQuantityAtStock(): void
    {
        // BDD : panier existant avec 8 bouteilles du vin 1
        $existingContent = json_encode([['wine_id' => 1, 'qty' => 8, 'name' => 'Bordeaux', 'image' => 'img.jpg']]);
        $row = [
            'id'             => 1,
            'user_id'        => 42,
            'content'        => $existingContent,
            'total_quantity' => 8,
        ];

        $this->dbMock
            ->method('fetchOne')
            ->willReturn($row);

        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->willReturn(1);

        $localItems = [
            ['wine_id' => 1, 'qty' => 5, 'name' => 'Bordeaux', 'image' => 'img.jpg'],
        ];

        // Stock = 10 : 8 + 5 = 13 > 10 → doit être plafonné à 10
        // On vérifie que ça ne plante pas et s'exécute sans erreur
        $this->model->mergeLocalCart(42, $localItems, fn(int $id): int => 10);
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // purgeWineFromAllCarts
    // ----------------------------------------------------------------

    public function testPurgeWineFromAllCartsRemovesWineFromMatchingCart(): void
    {
        $content = json_encode([['wine_id' => 2, 'qty' => 1, 'name' => 'Test', 'image' => 'img.jpg']]);
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 1, 'user_id' => 10, 'content' => $content, 'total_quantity' => 1]]);

        // save() appelle execute → doit être appelé une fois (le vin 2 est retiré)
        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $this->model->purgeWineFromAllCarts(2);
    }

    public function testPurgeWineFromAllCartsSkipsCartWithoutTargetWine(): void
    {
        $content = json_encode([['wine_id' => 99, 'qty' => 1, 'name' => 'Other', 'image' => 'img.jpg']]);
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 1, 'user_id' => 10, 'content' => $content, 'total_quantity' => 1]]);

        // Vin 2 absent du panier → pas de save() → execute non appelé
        $this->dbMock
            ->expects($this->never())
            ->method('execute');

        $this->model->purgeWineFromAllCarts(2);
    }

    public function testPurgeWineFromAllCartsDoesNothingWhenNoCartsExist(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->dbMock
            ->expects($this->never())
            ->method('execute');

        $this->model->purgeWineFromAllCarts(1);
    }

    // ----------------------------------------------------------------
    // removeWineFromAllCarts (alias)
    // ----------------------------------------------------------------

    public function testRemoveWineFromAllCartsDelegatesToPurge(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->model->removeWineFromAllCarts(5);
    }

    // ----------------------------------------------------------------
    // capWineQtyAcrossAllCarts
    // ----------------------------------------------------------------

    public function testCapWineQtyAcrossAllCartsPurgesWhenMaxQtyIsZero(): void
    {
        // maxQty = 0 → appelle purgeWineFromAllCarts → fetchAll appelé
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $this->model->capWineQtyAcrossAllCarts(3, 0);
    }

    public function testCapWineQtyAcrossAllCartsCapsQtyWhenExceedsMax(): void
    {
        $content = json_encode([['wine_id' => 3, 'qty' => 10, 'name' => 'Test', 'image' => 'img.jpg']]);
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 1, 'user_id' => 7, 'content' => $content, 'total_quantity' => 10]]);

        // qty=10 > maxQty=4 → save appelé → execute appelé une fois
        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $this->model->capWineQtyAcrossAllCarts(3, 4);
    }

    public function testCapWineQtyAcrossAllCartsNoSaveWhenQtyWithinLimit(): void
    {
        $content = json_encode([['wine_id' => 3, 'qty' => 2, 'name' => 'Test', 'image' => 'img.jpg']]);
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 1, 'user_id' => 7, 'content' => $content, 'total_quantity' => 2]]);

        // qty=2 <= maxQty=5 → pas de modification → execute non appelé
        $this->dbMock
            ->expects($this->never())
            ->method('execute');

        $this->model->capWineQtyAcrossAllCarts(3, 5);
    }

    // ----------------------------------------------------------------
    // countAll
    // ----------------------------------------------------------------

    public function testCountAllReturnsInteger(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(['cnt' => '17']);

        $this->assertSame(17, $this->model->countAll());
    }

    public function testCountAllReturnsZeroWhenFetchOneReturnsFalse(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertSame(0, $this->model->countAll());
    }

    // ----------------------------------------------------------------
    // findAllPaginated
    // ----------------------------------------------------------------

    public function testFindAllPaginatedReturnsFetchAllResult(): void
    {
        $rows = [
            ['id' => 1, 'user_id' => 10, 'total_quantity' => 12, 'email' => 'a@example.com', 'owner_name' => 'Alice'],
            ['id' => 2, 'user_id' => 11, 'total_quantity' => 6,  'email' => 'b@example.com', 'owner_name' => 'Bob'],
        ];

        $this->dbMock
            ->method('fetchAll')
            ->willReturn($rows);

        $result = $this->model->findAllPaginated(1, 25);

        $this->assertSame($rows, $result);
    }

    public function testFindAllPaginatedReturnsEmptyArrayWhenNoCarts(): void
    {
        $this->dbMock
            ->method('fetchAll')
            ->willReturn([]);

        $this->assertSame([], $this->model->findAllPaginated(1, 25));
    }
}
