<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\CartModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour CartModel.
 * Chaque test s'exécute dans une transaction rollbackée.
 */
class CartModelTest extends IntegrationTestCase
{
    private CartModel $model;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new CartModel();

        // Créer un compte de test pour les FK
        $this->userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter)
             VALUES ('cart-test@example.com', 'h', 'fr', 0)"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Cart', 'Test', 'M')",
            [$this->userId]
        );
    }

    // ----------------------------------------------------------------
    // findByUserId
    // ----------------------------------------------------------------

    public function testFindByUserIdReturnsFalseWhenNoCart(): void
    {
        $result = $this->model->findByUserId($this->userId);
        $this->assertFalse($result);
    }

    public function testFindByUserIdReturnsCartAfterCreation(): void
    {
        $items = [['wine_id' => 1, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->save($this->userId, $items);

        $result = $this->model->findByUserId($this->userId);
        $this->assertIsArray($result);
        $this->assertSame($this->userId, (int) $result['user_id']);
    }

    // ----------------------------------------------------------------
    // save (INSERT / upsert)
    // ----------------------------------------------------------------

    public function testSaveCreatesNewCart(): void
    {
        $items = [['wine_id' => 1, 'qty' => 3, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->save($this->userId, $items);

        $row = self::$db->fetchOne(
            "SELECT total_quantity, content FROM carts WHERE user_id = ?",
            [$this->userId]
        );
        $this->assertIsArray($row);
        $this->assertSame(3, (int) $row['total_quantity']);
    }

    public function testSaveUpdatesExistingCartContent(): void
    {
        // Première insertion
        $items = [['wine_id' => 1, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->save($this->userId, $items);

        // Mise à jour : nouvel item
        $updatedItems = [
            ['wine_id' => 1, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg'],
            ['wine_id' => 2, 'qty' => 1, 'name' => 'Sauternes', 'image' => 'img2.jpg'],
        ];
        $this->model->save($this->userId, $updatedItems);

        $row = self::$db->fetchOne(
            "SELECT total_quantity FROM carts WHERE user_id = ?",
            [$this->userId]
        );
        $this->assertIsArray($row);
        $this->assertSame(3, (int) $row['total_quantity']);
    }

    // ----------------------------------------------------------------
    // clear
    // ----------------------------------------------------------------

    public function testClearEmptiesCartContent(): void
    {
        $items = [['wine_id' => 1, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->save($this->userId, $items);

        $this->model->clear($this->userId);

        $row = self::$db->fetchOne(
            "SELECT total_quantity, content FROM carts WHERE user_id = ?",
            [$this->userId]
        );
        $this->assertIsArray($row);
        $this->assertSame(0, (int) $row['total_quantity']);

        $content = json_decode($row['content'], true);
        $this->assertIsArray($content);
        $this->assertEmpty($content);
    }

    // ----------------------------------------------------------------
    // getContent
    // ----------------------------------------------------------------

    public function testGetContentReturnsDecodedItems(): void
    {
        $items = [
            ['wine_id' => 1, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg'],
            ['wine_id' => 3, 'qty' => 1, 'name' => 'Rosé', 'image' => 'img3.jpg'],
        ];
        $this->model->save($this->userId, $items);

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);

        $decoded = $this->model->getContent($row);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertSame(1, $decoded[0]['wine_id']);
        $this->assertSame(2, $decoded[0]['qty']);
    }

    // ----------------------------------------------------------------
    // mergeLocalCart — fusion additive avec plafonnement au stock
    // ----------------------------------------------------------------

    public function testMergeLocalCartCreatesCartFromLocalItemsWhenNoneExists(): void
    {
        $localItems = [
            ['wine_id' => 1, 'qty' => 3, 'name' => 'Bordeaux', 'image' => 'img.jpg'],
        ];

        $this->model->mergeLocalCart($this->userId, $localItems, fn(int $id): int => 10);

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(3, (int) $row['total_quantity']);
    }

    public function testMergeLocalCartAddsQuantitiesToExistingCart(): void
    {
        // Panier BDD existant : 2 × vin 1
        $existingItems = [['wine_id' => 1, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->save($this->userId, $existingItems);

        // Cookie local : 3 × vin 1
        $localItems = [['wine_id' => 1, 'qty' => 3, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->mergeLocalCart($this->userId, $localItems, fn(int $id): int => 10);

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);
        // 2 + 3 = 5, stock = 10 → pas de plafonnement
        $this->assertSame(5, (int) $row['total_quantity']);
    }

    public function testMergeLocalCartCapsQuantityAtStock(): void
    {
        // Panier BDD : 8 × vin 1
        $existingItems = [['wine_id' => 1, 'qty' => 8, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->save($this->userId, $existingItems);

        // Cookie local : 5 × vin 1 → 8 + 5 = 13, stock = 10 → plafonné à 10
        $localItems = [['wine_id' => 1, 'qty' => 5, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->mergeLocalCart($this->userId, $localItems, fn(int $id): int => 10);

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(10, (int) $row['total_quantity']);

        $content = $this->model->getContent($row);
        $this->assertCount(1, $content);
        $this->assertSame(10, $content[0]['qty']);
    }

    public function testMergeLocalCartAddsNewItemsFromLocal(): void
    {
        // Panier BDD : vin 1 uniquement
        $existingItems = [['wine_id' => 1, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->save($this->userId, $existingItems);

        // Cookie local : vin 2 (nouvel item)
        $localItems = [['wine_id' => 2, 'qty' => 1, 'name' => 'Sauternes', 'image' => 'img2.jpg']];
        $this->model->mergeLocalCart($this->userId, $localItems, fn(int $id): int => 10);

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(3, (int) $row['total_quantity']); // 2 + 1

        $content = $this->model->getContent($row);
        $this->assertCount(2, $content);
    }
}
