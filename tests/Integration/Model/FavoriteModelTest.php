<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Model\FavoriteModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour FavoriteModel.
 * Couvre : getByUser, isLiked, toggle (add + remove), countForUser, getLikedIds.
 */
class FavoriteModelTest extends IntegrationTestCase
{
    private FavoriteModel $model;
    private int $userId;
    private int $wineId;

    /**
     * Crée un utilisateur et un vin de test avant chaque test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new FavoriteModel();

        $accountModel = new AccountModel();
        $this->userId = (int) $accountModel->create(
            'individual',
            'favorite_test@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Fav',
            'User',
            ''
        );

        $this->wineId = (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, quantity, available,
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
                     0, 'wine_test.jpg', ?)",
            ['Bordeaux Test Rouge 2020', 'test-bordeaux-rouge-2020-' . bin2hex(random_bytes(4))]
        );
    }

    // ------------------------------------------------------------------
    // isLiked
    // ------------------------------------------------------------------

    /**
     * isLiked retourne false quand le favori n'existe pas.
     */
    public function testIsLikedReturnsFalseWhenNotLiked(): void
    {
        $this->assertFalse($this->model->isLiked($this->userId, $this->wineId));
    }

    /**
     * isLiked retourne true après insertion d'un favori.
     */
    public function testIsLikedReturnsTrueAfterInsert(): void
    {
        self::$db->insert(
            "INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)",
            [$this->userId, $this->wineId]
        );

        $this->assertTrue($this->model->isLiked($this->userId, $this->wineId));
    }

    // ------------------------------------------------------------------
    // toggle
    // ------------------------------------------------------------------

    /**
     * toggle retourne true et insère le favori quand il n'existe pas encore.
     */
    public function testToggleAddsWhenNotLiked(): void
    {
        $result = $this->model->toggle($this->userId, $this->wineId);

        $this->assertTrue($result);
        $this->assertTrue($this->model->isLiked($this->userId, $this->wineId));
    }

    /**
     * toggle retourne false et supprime le favori quand il était déjà présent.
     */
    public function testToggleRemovesWhenAlreadyLiked(): void
    {
        self::$db->insert(
            "INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)",
            [$this->userId, $this->wineId]
        );

        $result = $this->model->toggle($this->userId, $this->wineId);

        $this->assertFalse($result);
        $this->assertFalse($this->model->isLiked($this->userId, $this->wineId));
    }

    /**
     * Deux toggles successifs rétablissent l'état initial (add → remove → add).
     */
    public function testToggleIsIdempotentOnDoubleCall(): void
    {
        $this->assertTrue($this->model->toggle($this->userId, $this->wineId));
        $this->assertFalse($this->model->toggle($this->userId, $this->wineId));
        $this->assertTrue($this->model->toggle($this->userId, $this->wineId));

        $this->assertTrue($this->model->isLiked($this->userId, $this->wineId));
    }

    // ------------------------------------------------------------------
    // countForUser
    // ------------------------------------------------------------------

    /**
     * countForUser retourne 0 quand l'utilisateur n'a aucun favori.
     */
    public function testCountForUserReturnsZeroWhenEmpty(): void
    {
        $this->assertSame(0, $this->model->countForUser($this->userId));
    }

    /**
     * countForUser retourne le bon nombre après insertion de favoris.
     */
    public function testCountForUserReturnsCorrectCount(): void
    {
        // Crée un second vin
        $wineId2 = (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, quantity, available,
              certification_label, area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment, is_cuvee_speciale,
              image_path, slug)
             VALUES (?, 'white', 'bottle', 2021, 14.50, 50, 1,
                     'AOC', 3.00, 'Bordeaux', 'Sauvignon', 15,
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     0, 'wine_test2.jpg', ?)",
            ['Bordeaux Blanc 2021', 'test-bordeaux-blanc-2021-' . bin2hex(random_bytes(4))]
        );

        self::$db->insert("INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)", [$this->userId, $this->wineId]);
        self::$db->insert("INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)", [$this->userId, $wineId2]);

        $this->assertSame(2, $this->model->countForUser($this->userId));
    }

    /**
     * countForUser isole correctement les données par utilisateur.
     */
    public function testCountForUserDoesNotLeakAcrossUsers(): void
    {
        $accountModel = new AccountModel();
        $otherUserId = (int) $accountModel->create(
            'individual',
            'other_fav@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Other',
            'User',
            ''
        );

        self::$db->insert("INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)", [$otherUserId, $this->wineId]);

        $this->assertSame(0, $this->model->countForUser($this->userId));
        $this->assertSame(1, $this->model->countForUser($otherUserId));
    }

    // ------------------------------------------------------------------
    // getLikedIds
    // ------------------------------------------------------------------

    /**
     * getLikedIds retourne un tableau vide quand l'utilisateur n'a aucun favori.
     */
    public function testGetLikedIdsReturnsEmptyArrayWhenNoFavorites(): void
    {
        $this->assertSame([], $this->model->getLikedIds($this->userId));
    }

    /**
     * getLikedIds retourne un tableau indexé par wine_id avec valeur true.
     */
    public function testGetLikedIdsReturnsIndexedArray(): void
    {
        self::$db->insert("INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)", [$this->userId, $this->wineId]);

        $ids = $this->model->getLikedIds($this->userId);

        $this->assertArrayHasKey($this->wineId, $ids);
        $this->assertTrue($ids[$this->wineId]);
    }

    /**
     * getLikedIds indexe correctement plusieurs wines.
     */
    public function testGetLikedIdsIndexesMultipleWines(): void
    {
        $wineId2 = (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, quantity, available,
              certification_label, area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment, is_cuvee_speciale,
              image_path, slug)
             VALUES (?, 'rosé', 'bottle', 2022, 12.00, 30, 1,
                     'IGP', 2.50, 'Provence', 'Grenache', 10,
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     '{\"fr\":\"...\",\"en\":\"...\"}',
                     0, 'wine_rose.jpg', ?)",
            ['Provence Rosé 2022', 'test-provence-rose-2022-' . bin2hex(random_bytes(4))]
        );

        self::$db->insert("INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)", [$this->userId, $this->wineId]);
        self::$db->insert("INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)", [$this->userId, $wineId2]);

        $ids = $this->model->getLikedIds($this->userId);

        $this->assertCount(2, $ids);
        $this->assertArrayHasKey($this->wineId, $ids);
        $this->assertArrayHasKey($wineId2, $ids);
    }

    // ------------------------------------------------------------------
    // getByUser
    // ------------------------------------------------------------------

    /**
     * getByUser retourne un tableau vide quand l'utilisateur n'a aucun favori.
     */
    public function testGetByUserReturnsEmptyArrayWhenNoFavorites(): void
    {
        $this->assertSame([], $this->model->getByUser($this->userId));
    }

    /**
     * getByUser retourne les champs attendus du JOIN avec wines.
     */
    public function testGetByUserReturnsExpectedFields(): void
    {
        self::$db->insert("INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)", [$this->userId, $this->wineId]);

        $results = $this->model->getByUser($this->userId);

        $this->assertCount(1, $results);
        $row = $results[0];
        $this->assertArrayHasKey('wine_id', $row);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('slug', $row);
        $this->assertArrayHasKey('color', $row);
        $this->assertArrayHasKey('vintage', $row);
        $this->assertArrayHasKey('price', $row);
        $this->assertArrayHasKey('image_path', $row);
        $this->assertSame($this->wineId, (int) $row['wine_id']);
    }

    /**
     * getByUser n'expose pas les données d'un autre utilisateur.
     */
    public function testGetByUserIsolatesDataPerUser(): void
    {
        $accountModel = new AccountModel();
        $otherUserId = (int) $accountModel->create(
            'individual',
            'other_getbyuser@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Other',
            'User',
            ''
        );

        self::$db->insert("INSERT INTO favorites (user_id, wine_id) VALUES (?, ?)", [$otherUserId, $this->wineId]);

        $this->assertSame([], $this->model->getByUser($this->userId));
        $this->assertCount(1, $this->model->getByUser($otherUserId));
    }
}
