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

    // ----------------------------------------------------------------
    // getAbandonedForEmail
    // ----------------------------------------------------------------

    /**
     * Aucun panier abandonné → tableau vide.
     */
    public function testGetAbandonedForEmailReturnsEmptyWhenNoCarts(): void
    {
        $result = $this->model->getAbandonedForEmail(72, 96);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Un panier dont reminder_sent_at est déjà renseigné ne doit pas être retourné.
     */
    public function testGetAbandonedForEmailSkipsCartsWithReminderSent(): void
    {
        $past = date('Y-m-d H:i:s', strtotime('-80 hours'));

        self::$db->execute(
            "INSERT INTO carts (user_id, content, total_quantity, reminder_sent_at, updated_at)
             VALUES (?, ?, 1, NOW(), ?)",
            [$this->userId, json_encode([['wine_id' => 999, 'qty' => 1]]), $past]
        );

        $result = $this->model->getAbandonedForEmail(72, 96);
        $this->assertEmpty($result);
    }

    /**
     * Un panier hors de la fenêtre horaire ne doit pas être retourné.
     */
    public function testGetAbandonedForEmailSkipsCartsOutsideWindow(): void
    {
        // Mis à jour il y a seulement 1 heure — hors de la fenêtre 72-96h
        $recent = date('Y-m-d H:i:s', strtotime('-1 hour'));

        self::$db->execute(
            "INSERT INTO carts (user_id, content, total_quantity, reminder_sent_at, updated_at)
             VALUES (?, ?, 1, NULL, ?)",
            [$this->userId, json_encode([['wine_id' => 999, 'qty' => 1]]), $recent]
        );

        $result = $this->model->getAbandonedForEmail(72, 96);
        $this->assertEmpty($result);
    }

    /**
     * Un panier avec contenu JSON vide ne doit pas être retourné.
     */
    public function testGetAbandonedForEmailSkipsCartsWithEmptyContent(): void
    {
        $past = date('Y-m-d H:i:s', strtotime('-80 hours'));

        self::$db->execute(
            "INSERT INTO carts (user_id, content, total_quantity, reminder_sent_at, updated_at)
             VALUES (?, ?, 1, NULL, ?)",
            [$this->userId, '[]', $past]
        );

        $result = $this->model->getAbandonedForEmail(72, 96);
        $this->assertEmpty($result);
    }

    // ----------------------------------------------------------------
    // purgeWineFromAllCarts
    // ----------------------------------------------------------------

    /**
     * Un vin cible est retiré du panier.
     */
    public function testPurgeWineFromAllCartsRemovesTargetWine(): void
    {
        $items = [
            ['wine_id' => 10, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg'],
            ['wine_id' => 20, 'qty' => 1, 'name' => 'Sauternes', 'image' => 'img2.jpg'],
        ];
        $this->model->save($this->userId, $items);

        $this->model->purgeWineFromAllCarts(10);

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);

        $content = $this->model->getContent($row);
        $this->assertCount(1, $content);
        $this->assertSame(20, (int) $content[0]['wine_id']);
    }

    // ----------------------------------------------------------------
    // removeWineFromAllCarts (alias de purgeWineFromAllCarts)
    // ----------------------------------------------------------------

    public function testRemoveWineFromAllCartsRemovesWineFromSingleCart(): void
    {
        $items = [
            ['wine_id' => 10, 'qty' => 2, 'name' => 'Sauternes', 'image' => 'img.jpg'],
            ['wine_id' => 20, 'qty' => 1, 'name' => 'Bordeaux', 'image' => 'img2.jpg'],
        ];
        $this->model->save($this->userId, $items);

        $this->model->removeWineFromAllCarts(10);

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);

        $content = $this->model->getContent($row);
        $this->assertCount(1, $content);
        $this->assertSame(20, (int) $content[0]['wine_id']);
    }

    /**
     * Un vin absent du panier ne modifie pas le contenu.
     */
    public function testPurgeWineFromAllCartsDoesNotModifyUnrelatedWines(): void
    {
        $items = [['wine_id' => 5, 'qty' => 3, 'name' => 'Rosé', 'image' => 'img.jpg']];
        $this->model->save($this->userId, $items);

        $this->model->purgeWineFromAllCarts(99); // vin 99 absent

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(3, (int) $row['total_quantity']);
    }

    public function testRemoveWineFromAllCartsDoesNothingIfWineAbsent(): void
    {
        $items = [
            ['wine_id' => 10, 'qty' => 3, 'name' => 'Sauternes', 'image' => 'img.jpg'],
        ];
        $this->model->save($this->userId, $items);

        // Vin 99 absent du panier → aucun changement
        $this->model->removeWineFromAllCarts(99);

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(3, (int) $row['total_quantity']);
    }

    /**
     * Panier sans aucun vin après purge → total_quantity remis à 0.
     */
    public function testPurgeWineFromAllCartsEmptiesCartIfOnlyWine(): void
    {
        $items = [['wine_id' => 7, 'qty' => 2, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->save($this->userId, $items);

        $this->model->purgeWineFromAllCarts(7);

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(0, (int) $row['total_quantity']);
    }

    public function testRemoveWineFromAllCartsEmptiesCartWhenOnlyOneItem(): void
    {
        $items = [
            ['wine_id' => 10, 'qty' => 5, 'name' => 'Sauternes', 'image' => 'img.jpg'],
        ];
        $this->model->save($this->userId, $items);

        $this->model->removeWineFromAllCarts(10);

        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);

        $content = $this->model->getContent($row);
        $this->assertCount(0, $content);
        $this->assertSame(0, (int) $row['total_quantity']);
    }

    // ----------------------------------------------------------------
    // removeUnavailableItems
    // ----------------------------------------------------------------

    /**
     * Pas de panier → retourne tableau vide.
     */
    public function testRemoveUnavailableItemsReturnsEmptyWhenNoCart(): void
    {
        $removed = $this->model->removeUnavailableItems($this->userId, fn(int $id): ?array => null);
        $this->assertSame([], $removed);
    }

    /**
     * Vin introuvable (wineData retourne null) → article retiré.
     */
    public function testRemoveUnavailableItemsRemovesWhenWineIsNull(): void
    {
        $items = [['wine_id' => 99, 'qty' => 1, 'name' => 'Vin inconnu', 'image' => '']];
        $this->model->save($this->userId, $items);

        $removed = $this->model->removeUnavailableItems($this->userId, fn(int $id): ?array => null);

        $this->assertCount(1, $removed);
        $this->assertSame(99, $removed[0]['wine_id']);
        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(0, (int) $row['total_quantity']);
    }

    /**
     * Vin non disponible (available = 0) → article retiré.
     */
    public function testRemoveUnavailableItemsRemovesWhenWineUnavailable(): void
    {
        $items = [['wine_id' => 5, 'qty' => 2, 'name' => 'Vin indispo', 'image' => '']];
        $this->model->save($this->userId, $items);

        $removed = $this->model->removeUnavailableItems(
            $this->userId,
            fn(int $id): ?array => ['available' => 0, 'stock_quantity' => 10]
        );

        $this->assertCount(1, $removed);
        $this->assertSame(5, $removed[0]['wine_id']);
    }

    /**
     * Quantité dépasse le stock → plafonnée, article conservé.
     */
    public function testRemoveUnavailableItemsCapsQtyAtStock(): void
    {
        $items = [['wine_id' => 3, 'qty' => 8, 'name' => 'Vin dispo', 'image' => '']];
        $this->model->save($this->userId, $items);

        $removed = $this->model->removeUnavailableItems(
            $this->userId,
            fn(int $id): ?array => ['available' => 1, 'stock_quantity' => 3]
        );

        $this->assertSame([], $removed); // aucun article retiré
        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);
        $content = $this->model->getContent($row);
        $this->assertSame(3, $content[0]['qty']); // plafonné à 3
    }

    /**
     * Article disponible avec qty dans le stock → conservé sans modification.
     */
    public function testRemoveUnavailableItemsKeepsAvailableItems(): void
    {
        $items = [['wine_id' => 2, 'qty' => 2, 'name' => 'Vin ok', 'image' => '']];
        $this->model->save($this->userId, $items);

        $removed = $this->model->removeUnavailableItems(
            $this->userId,
            fn(int $id): ?array => ['available' => 1, 'stock_quantity' => 10]
        );

        $this->assertSame([], $removed);
        $row = $this->model->findByUserId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(2, (int) $row['total_quantity']);
    }

    // ----------------------------------------------------------------
    // getAbandoned
    // ----------------------------------------------------------------

    /**
     * Aucun panier abandonné dans la fenêtre → tableau vide.
     */
    public function testGetAbandonedReturnsEmptyWhenNoCarts(): void
    {
        $result = $this->model->getAbandoned(72, 96);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Panier récent (hors fenêtre) → non retourné.
     */
    public function testGetAbandonedExcludesRecentCarts(): void
    {
        $recent = date('Y-m-d H:i:s', strtotime('-1 hour'));
        self::$db->execute(
            "INSERT INTO carts (user_id, content, total_quantity, reminder_sent_at, updated_at)
             VALUES (?, ?, 1, NULL, ?)",
            [$this->userId, json_encode([['wine_id' => 1, 'qty' => 1]]), $recent]
        );

        $result = $this->model->getAbandoned(72, 96);
        $this->assertEmpty($result);
    }

    /**
     * Panier avec reminder_sent_at renseigné → non retourné.
     */
    public function testGetAbandonedExcludesCartsWithReminderSent(): void
    {
        $past = date('Y-m-d H:i:s', strtotime('-80 hours'));
        self::$db->execute(
            "INSERT INTO carts (user_id, content, total_quantity, reminder_sent_at, updated_at)
             VALUES (?, ?, 1, NOW(), ?)",
            [$this->userId, json_encode([['wine_id' => 1, 'qty' => 1]]), $past]
        );

        $result = $this->model->getAbandoned(72, 96);
        $this->assertEmpty($result);
    }

    // ----------------------------------------------------------------
    // markReminderSent
    // ----------------------------------------------------------------

    // ----------------------------------------------------------------
    // getAbandonedForEmail — happy path avec vin enrichi
    // ----------------------------------------------------------------

    /**
     * Retourne les données enrichies quand wine existe et cart dans la fenêtre.
     */
    public function testGetAbandonedForEmailReturnsEnrichedDataWhenWineExists(): void
    {
        // Insérer un vin minimal
        $nc = '{"fr":"nc","en":"nc"}';
        $wineId = (int) self::$db->insert(
            "INSERT INTO wines (label_name, wine_color, format, vintage, price,
              production_quantity, stock_quantity, available, certification_label,
              area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment, image_path, slug, is_cuvee_speciale)
             VALUES ('Vin Test TI', 'red', 'bottle', 2021, 12.00,
              1000, 50, 1, 'AOC',
              5.00, 'Test City', 'Merlot', 20,
              ?, ?, ?, ?, ?, ?, ?, ?,
              'test.jpg', 'vin-test-ti', 0)",
            [$nc, $nc, $nc, $nc, $nc, $nc, $nc, $nc]
        );

        $past = date('Y-m-d H:i:s', strtotime('-80 hours'));
        self::$db->execute(
            "INSERT INTO carts (user_id, content, total_quantity, reminder_sent_at, updated_at)
             VALUES (?, ?, 1, NULL, ?)",
            [$this->userId, json_encode([['wine_id' => $wineId, 'qty' => 1]]), $past]
        );

        $result = $this->model->getAbandonedForEmail(72, 96);

        $this->assertNotEmpty($result);
        $this->assertSame($this->userId, (int) $result[0]['user_id']);
        $this->assertNotEmpty($result[0]['items']);
        $this->assertSame($wineId, $result[0]['items'][0]['wine_id']);
    }

    /**
     * markReminderSent renseigne reminder_sent_at pour le panier.
     */
    public function testMarkReminderSentUpdatesColumn(): void
    {
        $items = [['wine_id' => 1, 'qty' => 1, 'name' => 'Bordeaux', 'image' => 'img.jpg']];
        $this->model->save($this->userId, $items);

        $row = self::$db->fetchOne(
            "SELECT id FROM carts WHERE user_id = ?",
            [$this->userId]
        );
        $this->assertIsArray($row);
        $cartId = (int) $row['id'];

        $this->model->markReminderSent($cartId);

        $updated = self::$db->fetchOne(
            "SELECT reminder_sent_at FROM carts WHERE id = ?",
            [$cartId]
        );
        $this->assertIsArray($updated);
        $this->assertNotNull($updated['reminder_sent_at']);
    }
}
