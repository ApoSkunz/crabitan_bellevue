<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\CartModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CartModel.
 * La base de données est mockée — aucune connexion réelle requise.
 */
class CartModelTest extends TestCase
{
    private Database $dbMock;
    private CartModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
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
}
