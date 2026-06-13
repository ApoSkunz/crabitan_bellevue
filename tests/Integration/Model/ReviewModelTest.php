<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\ReviewModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour ReviewModel.
 * Chaque test s'exécute dans une transaction rollbackée.
 */
class ReviewModelTest extends IntegrationTestCase
{
    private ReviewModel $model;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        self::$db->execute("DELETE FROM customer_reviews", []);
        $this->model = new ReviewModel();

        $this->userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter)
             VALUES ('review-ti@example.com', 'h', 'fr', 0)"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Review', 'TI', 'M')",
            [$this->userId]
        );
    }

    // ----------------------------------------------------------------
    // countAll
    // ----------------------------------------------------------------

    public function testCountAllReturnsZeroWhenTableEmpty(): void
    {
        $this->assertSame(0, $this->model->countAll());
    }

    public function testCountAllReturnsCorrectCountAfterInsert(): void
    {
        self::$db->execute(
            "INSERT INTO customer_reviews (account_id, rating, comment, display_name, location)
             VALUES (?, 5, 'Top !', 'Sophie D.', 'Paris')",
            [$this->userId]
        );

        $this->assertSame(1, $this->model->countAll());
    }

    public function testCountAllIncreasesWithEachInsert(): void
    {
        // Deuxième compte pour second avis (UNIQUE KEY uq_account)
        $userId2 = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter)
             VALUES ('review-ti-2@example.com', 'h', 'fr', 0)"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Review', 'TI2', 'M')",
            [$userId2]
        );

        self::$db->execute(
            "INSERT INTO customer_reviews (account_id, rating, comment, display_name, location)
             VALUES (?, 4, 'Bien !', 'Marc L.', 'Lyon')",
            [$this->userId]
        );
        self::$db->execute(
            "INSERT INTO customer_reviews (account_id, rating, comment, display_name, location)
             VALUES (?, 5, 'Excellent !', 'Sophie D.', 'Paris')",
            [$userId2]
        );

        $this->assertSame(2, $this->model->countAll());
    }

    // ----------------------------------------------------------------
    // findPaginated
    // ----------------------------------------------------------------

    public function testFindPaginatedReturnsEmptyWhenNoReviews(): void
    {
        $result = $this->model->findPaginated(1, 25);
        $this->assertEmpty($result);
    }

    public function testFindPaginatedReturnsInsertedRow(): void
    {
        self::$db->execute(
            "INSERT INTO customer_reviews (account_id, rating, comment, display_name, location)
             VALUES (?, 5, 'Super !', 'Sophie D.', 'Paris')",
            [$this->userId]
        );

        $result = $this->model->findPaginated(1, 25);

        $this->assertCount(1, $result);
        $this->assertSame($this->userId, (int) $result[0]['account_id']);
        $this->assertSame(5, (int) $result[0]['rating']);
        $this->assertArrayHasKey('email', $result[0]);
    }

    public function testFindPaginatedJoinsAccountEmail(): void
    {
        self::$db->execute(
            "INSERT INTO customer_reviews (account_id, rating, comment, display_name, location)
             VALUES (?, 4, 'Bon vin', 'Marc L.', 'Lyon')",
            [$this->userId]
        );

        $result = $this->model->findPaginated(1, 25);

        $this->assertSame('review-ti@example.com', $result[0]['email']);
    }

    public function testFindPaginatedPage2ReturnsSecondBatch(): void
    {
        // Deuxième compte
        $userId2 = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter)
             VALUES ('review-ti-page2@example.com', 'h', 'fr', 0)"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Page', 'Two', 'M')",
            [$userId2]
        );

        // Avis 1 (userId) — plus ancien
        self::$db->execute(
            "INSERT INTO customer_reviews (account_id, rating, comment, display_name, location, created_at)
             VALUES (?, 3, 'Moyen', 'Marc L.', 'Lyon', '2026-01-01 10:00:00')",
            [$this->userId]
        );
        // Avis 2 (userId2) — plus récent → sera en premier (ORDER BY created_at DESC)
        self::$db->execute(
            "INSERT INTO customer_reviews (account_id, rating, comment, display_name, location, created_at)
             VALUES (?, 5, 'Top !', 'Sophie D.', 'Paris', '2026-06-01 10:00:00')",
            [$userId2]
        );

        // Page 1, 1 par page → avis récent (userId2)
        $page1 = $this->model->findPaginated(1, 1);
        $this->assertCount(1, $page1);
        $this->assertSame($userId2, (int) $page1[0]['account_id']);

        // Page 2, 1 par page → avis ancien (userId)
        $page2 = $this->model->findPaginated(2, 1);
        $this->assertCount(1, $page2);
        $this->assertSame($this->userId, (int) $page2[0]['account_id']);
    }

    public function testFindPaginatedRespectsPerPageLimit(): void
    {
        // Trois comptes pour trois avis
        $ids = [$this->userId];
        foreach (['a', 'b'] as $letter) {
            $uid = (int) self::$db->insert(
                "INSERT INTO accounts (email, password, lang, newsletter)
                 VALUES ('review-ti-{$letter}@example.com', 'h', 'fr', 0)"
            );
            self::$db->insert(
                "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
                 VALUES (?, 'X', 'X', 'M')",
                [$uid]
            );
            $ids[] = $uid;
        }

        foreach ($ids as $uid) {
            self::$db->execute(
                "INSERT INTO customer_reviews (account_id, rating, comment, display_name, location)
                 VALUES (?, 5, 'OK', 'Test', 'Ville')",
                [$uid]
            );
        }

        $result = $this->model->findPaginated(1, 2); // perPage=2, 3 en BDD
        $this->assertCount(2, $result);
    }

    // ----------------------------------------------------------------
    // create + findByAccountId
    // ----------------------------------------------------------------

    public function testCreateInsertsRowAndReturnsId(): void
    {
        $id = $this->model->create($this->userId, null, 5, 'Superbe !', 'Sophie D.', 'Paris');

        $this->assertGreaterThan(0, $id);

        $row = $this->model->findByAccountId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(5, (int) $row['rating']);
    }

    public function testFindByAccountIdReturnsNullWhenNoReview(): void
    {
        $this->assertNull($this->model->findByAccountId($this->userId));
    }

    // ----------------------------------------------------------------
    // update
    // ----------------------------------------------------------------

    public function testUpdateChangesRatingAndComment(): void
    {
        $this->model->create($this->userId, null, 3, 'Moyen', 'Marc L.', 'Lyon');
        $this->model->update($this->userId, 5, 'Excellent !', 'Paris');

        $row = $this->model->findByAccountId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(5, (int) $row['rating']);
        $this->assertSame('Excellent !', $row['comment']);
        $this->assertSame('Paris', $row['location']);
    }

    // ----------------------------------------------------------------
    // upsert
    // ----------------------------------------------------------------

    public function testUpsertInsertsWhenNoExistingReview(): void
    {
        $this->model->upsert($this->userId, 4, 'Bien !', 'Marc L.', 'Lyon');

        $row = $this->model->findByAccountId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(4, (int) $row['rating']);
    }

    public function testUpsertUpdatesWhenReviewAlreadyExists(): void
    {
        $this->model->upsert($this->userId, 3, 'Moyen', 'Marc L.', 'Lyon');
        $this->model->upsert($this->userId, 5, 'Top !', 'Marc L.', 'Lyon');

        $row = $this->model->findByAccountId($this->userId);
        $this->assertIsArray($row);
        $this->assertSame(5, (int) $row['rating']);
        $this->assertSame('Top !', $row['comment']);
    }

    // ----------------------------------------------------------------
    // getStats
    // ----------------------------------------------------------------

    public function testGetStatsReturnsNullWhenNoReviews(): void
    {
        $this->assertNull($this->model->getStats());
    }

    public function testGetStatsReturnsAverageAndCount(): void
    {
        $uid2 = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter)
             VALUES ('review-ti-stats@example.com', 'h', 'fr', 0)"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Stats', 'TI', 'M')",
            [$uid2]
        );

        $this->model->upsert($this->userId, 4, 'Bien', 'Marc L.', 'Lyon');
        $this->model->upsert($uid2, 5, 'Top', 'Sophie D.', 'Paris');

        $stats = $this->model->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('avg', $stats);
        $this->assertArrayHasKey('count', $stats);
        $this->assertSame(2, $stats['count']);
        $this->assertSame(4.5, $stats['avg']);
    }
}
