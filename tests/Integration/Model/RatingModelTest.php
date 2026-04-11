<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Model\RatingModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour RatingModel.
 * Couvre : upsert, getAvgByWine, countByWine, findByToken, markTokenUsed,
 *          generateEmailToken, hasOrdered.
 */
class RatingModelTest extends IntegrationTestCase
{
    private RatingModel $model;
    private int $accountId;
    private int $wineId;
    private int $orderId;

    /**
     * Crée les fixtures (compte, vin, commande) avant chaque test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new RatingModel();

        $accountModel = new AccountModel();
        $this->accountId = (int) $accountModel->create(
            'individual',
            'rating_test_' . bin2hex(random_bytes(4)) . '@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Rating',
            'User',
            ''
        );

        $this->wineId = (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, production_quantity, available,
              certification_label, area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment, is_cuvee_speciale,
              image_path, slug)
             VALUES (?, 'red', 'bottle', 2020, 19.90, 100, 1,
                     'AOC', 5.00, 'Bordeaux', 'Merlot', 20,
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     0, 'wine_rating_test.jpg', ?)",
            ['Rating Test Wine 2020', 'test-rating-wine-2020-' . bin2hex(random_bytes(4))]
        );

        // Créer un snapshot d'adresse de facturation requis par orders
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (firstname, lastname, street, city, zip, country)
             VALUES (?, ?, ?, ?, ?, ?)",
            ['Rating', 'User', '1 Rue Test', 'Paris', '75001', 'FR']
        );

        $this->orderId = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method,
              id_billing_snapshot, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $this->accountId,
                'TEST-REF-' . bin2hex(random_bytes(4)),
                json_encode([['wine_id' => $this->wineId, 'qty' => 1]]),
                19.90,
                'card',
                $snapshotId,
                'delivered',
            ]
        );
    }

    // ------------------------------------------------------------------
    // upsert
    // ------------------------------------------------------------------

    /**
     * upsert crée une ligne avec la note donnée.
     */
    public function testUpsertCreatesRating(): void
    {
        $this->model->upsert($this->accountId, $this->wineId, $this->orderId, 4);

        $row = $this->model->getByAccountAndWine($this->accountId, $this->wineId);
        $this->assertNotNull($row);
        $this->assertSame('4', (string) $row['rating']);
    }

    /**
     * upsert met à jour la note si (account_id, wine_id) existe déjà.
     */
    public function testUpsertUpdatesExistingRating(): void
    {
        $this->model->upsert($this->accountId, $this->wineId, $this->orderId, 3);
        $this->model->upsert($this->accountId, $this->wineId, $this->orderId, 5);

        $row = $this->model->getByAccountAndWine($this->accountId, $this->wineId);
        $this->assertNotNull($row);
        $this->assertSame('5', (string) $row['rating']);
    }

    // ------------------------------------------------------------------
    // getAvgByWine
    // ------------------------------------------------------------------

    /**
     * getAvgByWine retourne null si moins de 2 avis.
     */
    public function testGetAvgByWineReturnsNullWithOneRating(): void
    {
        $this->model->upsert($this->accountId, $this->wineId, $this->orderId, 4);

        $this->assertNull($this->model->getAvgByWine($this->wineId));
    }

    /**
     * getAvgByWine retourne un float si >= 2 avis.
     */
    public function testGetAvgByWineReturnsFloatWithTwoRatings(): void
    {
        // Second compte
        $accountModel = new AccountModel();
        $account2 = (int) $accountModel->create(
            'individual',
            'rating2_' . bin2hex(random_bytes(4)) . '@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Rating2',
            'User',
            ''
        );

        $snapshotId2 = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (firstname, lastname, street, city, zip, country)
             VALUES (?, ?, ?, ?, ?, ?)",
            ['Rating2', 'User', '2 Rue Test', 'Paris', '75001', 'FR']
        );

        $orderId2 = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method,
              id_billing_snapshot, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $account2,
                'TEST-REF2-' . bin2hex(random_bytes(4)),
                json_encode([['wine_id' => $this->wineId, 'qty' => 1]]),
                19.90,
                'card',
                $snapshotId2,
                'delivered',
            ]
        );

        $this->model->upsert($this->accountId, $this->wineId, $this->orderId, 4);
        $this->model->upsert($account2, $this->wineId, $orderId2, 5);

        $avg = $this->model->getAvgByWine($this->wineId);
        $this->assertNotNull($avg);
        $this->assertIsFloat($avg);
        $this->assertSame(4.5, $avg);
    }

    // ------------------------------------------------------------------
    // countByWine
    // ------------------------------------------------------------------

    /**
     * countByWine retourne 0 avant tout avis.
     */
    public function testCountByWineReturnsZeroWhenEmpty(): void
    {
        $this->assertSame(0, $this->model->countByWine($this->wineId));
    }

    /**
     * countByWine retourne 1 après insertion d'un avis.
     */
    public function testCountByWineReturnsCorrectCountAfterInsert(): void
    {
        $this->model->upsert($this->accountId, $this->wineId, $this->orderId, 3);

        $this->assertSame(1, $this->model->countByWine($this->wineId));
    }

    // ------------------------------------------------------------------
    // findByToken / markTokenUsed
    // ------------------------------------------------------------------

    /**
     * findByToken retourne null si token inconnu.
     */
    public function testFindByTokenReturnsNullForUnknownToken(): void
    {
        $this->assertNull($this->model->findByToken('unknowntoken000000000000000000000000000000000000000000000000000'));
    }

    /**
     * findByToken retourne un array pour un token valide non utilisé.
     */
    public function testFindByTokenReturnsRowForValidToken(): void
    {
        $token = $this->model->generateEmailToken($this->accountId, $this->wineId, $this->orderId);

        $row = $this->model->findByToken($token);
        $this->assertNotNull($row);
        $this->assertSame($token, $row['token']);
    }

    /**
     * findByToken retourne null après markTokenUsed.
     */
    public function testFindByTokenReturnsNullAfterMarkUsed(): void
    {
        $token = $this->model->generateEmailToken($this->accountId, $this->wineId, $this->orderId);
        $this->model->markTokenUsed($token);

        $this->assertNull($this->model->findByToken($token));
    }

    // ------------------------------------------------------------------
    // generateEmailToken
    // ------------------------------------------------------------------

    /**
     * generateEmailToken retourne un token hexadécimal de 64 caractères.
     */
    public function testGenerateEmailTokenReturns64CharHex(): void
    {
        $token = $this->model->generateEmailToken($this->accountId, $this->wineId, $this->orderId);

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    /**
     * generateEmailToken crée la ligne en BDD si elle n'existe pas.
     */
    public function testGenerateEmailTokenCreatesRow(): void
    {
        $token = $this->model->generateEmailToken($this->accountId, $this->wineId, $this->orderId);

        $row = $this->model->getByAccountAndWine($this->accountId, $this->wineId);
        $this->assertNotNull($row);
        $this->assertSame($token, $row['token']);
    }

    // ------------------------------------------------------------------
    // hasOrdered
    // ------------------------------------------------------------------

    /**
     * hasOrdered retourne true si commande delivered existe.
     */
    public function testHasOrderedReturnsTrueWhenDelivered(): void
    {
        $this->assertTrue($this->model->hasOrdered($this->accountId, $this->wineId));
    }

    /**
     * hasOrdered retourne false pour un vin non commandé.
     */
    public function testHasOrderedReturnsFalseForUnorderedWine(): void
    {
        $otherWineId = (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, production_quantity, available,
              certification_label, area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment, is_cuvee_speciale,
              image_path, slug)
             VALUES (?, 'white', 'bottle', 2021, 15.00, 50, 1,
                     'AOC', 3.00, 'Loire', 'Sauvignon', 10,
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     0, 'wine_other.jpg', ?)",
            ['Loire Blanc 2021', 'test-loire-blanc-2021-' . bin2hex(random_bytes(4))]
        );

        $this->assertFalse($this->model->hasOrdered($this->accountId, $otherWineId));
    }

    // ------------------------------------------------------------------
    // getAvgBulk
    // ------------------------------------------------------------------

    /**
     * getAvgBulk retourne un tableau vide si aucun wine_id fourni.
     */
    public function testGetAvgBulkReturnsEmptyForEmptyInput(): void
    {
        $result = $this->model->getAvgBulk([]);
        $this->assertSame([], $result);
    }

    /**
     * getAvgBulk retourne null pour un vin sans avis.
     */
    public function testGetAvgBulkReturnsNullForWineWithNoRatings(): void
    {
        $result = $this->model->getAvgBulk([$this->wineId]);
        $this->assertArrayHasKey($this->wineId, $result);
        $this->assertNull($result[$this->wineId]); // 0 avis → null
    }

    /**
     * getAvgBulk retourne la moyenne si >= 2 avis.
     */
    public function testGetAvgBulkReturnsMeanWithEnoughRatings(): void
    {
        // Créer un second compte pour avoir 2 avis
        $accountId2 = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter) VALUES (?, 'h', 'fr', 0)",
            ['bulk2_' . bin2hex(random_bytes(4)) . '@test.com']
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility) VALUES (?, 'B', 'B', 'M')",
            [$accountId2]
        );

        $this->model->upsert($this->accountId, $this->wineId, $this->orderId, 4);
        $this->model->upsert($accountId2, $this->wineId, $this->orderId, 2);

        $result = $this->model->getAvgBulk([$this->wineId]);
        $this->assertArrayHasKey($this->wineId, $result);
        $this->assertSame(3.0, $result[$this->wineId]);
    }

    // ------------------------------------------------------------------
    // generateOrderToken / resolveOrderToken
    // ------------------------------------------------------------------

    /**
     * generateOrderToken crée un token hexadécimal de 64 caractères.
     */
    public function testGenerateOrderTokenReturns64CharHex(): void
    {
        $token = $this->model->generateOrderToken($this->orderId, $this->accountId);
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    /**
     * Un second appel pour le même (order_id, account_id) retourne le même token.
     */
    public function testGenerateOrderTokenIsIdempotent(): void
    {
        $token1 = $this->model->generateOrderToken($this->orderId, $this->accountId);
        $token2 = $this->model->generateOrderToken($this->orderId, $this->accountId);
        $this->assertSame($token1, $token2);
    }

    /**
     * resolveOrderToken retourne null pour un token inconnu.
     */
    public function testResolveOrderTokenReturnsNullForUnknownToken(): void
    {
        $result = $this->model->resolveOrderToken(str_repeat('0', 64));
        $this->assertNull($result);
    }

    /**
     * resolveOrderToken retourne order_id et account_id pour un token valide.
     */
    public function testResolveOrderTokenReturnsIdsForValidToken(): void
    {
        $token = $this->model->generateOrderToken($this->orderId, $this->accountId);

        $result = $this->model->resolveOrderToken($token);
        $this->assertNotNull($result);
        $this->assertSame($this->orderId, (int) $result['order_id']);
        $this->assertSame($this->accountId, (int) $result['account_id']);
    }

    // ------------------------------------------------------------------
    // getTopRated
    // ------------------------------------------------------------------

    /**
     * getTopRated retourne un tableau vide si aucun vin n'a >= 2 avis.
     */
    public function testGetTopRatedReturnsEmptyWhenNoEnoughRatings(): void
    {
        $this->model->upsert($this->accountId, $this->wineId, $this->orderId, 5);

        $result = $this->model->getTopRated(10);
        // Un seul avis → pas retourné (min 2)
        $wineIds = array_column($result, 'wine_id');
        $this->assertNotContains($this->wineId, $wineIds);
    }
}
