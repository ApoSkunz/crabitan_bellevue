<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\ReviewModel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour ReviewModel.
 */
#[AllowMockObjectsWithoutExpectations]
class ReviewModelTest extends TestCase
{
    /**
     * Injecte un mock Database dans ReviewModel via réflexion.
     *
     * @param Database $db Instance mock ou stub
     * @return ReviewModel
     */
    private function makeModel(Database $db): ReviewModel
    {
        $model = new ReviewModel();
        $ref   = new \ReflectionProperty(ReviewModel::class, 'db');
        $ref->setAccessible(true);
        $ref->setValue($model, $db);
        return $model;
    }

    // ----------------------------------------------------------------
    // findByAccountId
    // ----------------------------------------------------------------

    public function testFindByAccountIdReturnsRowWhenFound(): void
    {
        $expected = [
            'id'           => 1,
            'account_id'   => 3,
            'order_id'     => 1,
            'rating'       => 5,
            'comment'      => 'Excellent !',
            'display_name' => 'Sophie D.',
            'location'     => 'Paris',
            'created_at'   => '2026-04-01 10:00:00',
        ];

        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn($expected);

        $this->assertSame($expected, $this->makeModel($db)->findByAccountId(3));
    }

    public function testFindByAccountIdReturnsNullWhenNotFound(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(false);

        $this->assertNull($this->makeModel($db)->findByAccountId(99));
    }

    // ----------------------------------------------------------------
    // create
    // ----------------------------------------------------------------

    public function testCreateExecutesInsertAndReturnsId(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT INTO customer_reviews'),
                $this->callback(fn($p) => $p[0] === 3 && $p[2] === 5)
            );

        $db->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('LAST_INSERT_ID()'), [])
            ->willReturn(['id' => 7]);

        $result = $this->makeModel($db)->create(3, 1, 5, 'Super !', 'Sophie D.', 'Paris');

        $this->assertSame(7, $result);
    }

    public function testCreateAcceptsNullOrderId(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT INTO customer_reviews'),
                $this->callback(fn($p) => $p[1] === null)
            );

        $db->method('fetchOne')->willReturn(['id' => 8]);

        $this->makeModel($db)->create(4, null, 4, 'Bien !', 'Marc L.', 'Lyon');
    }

    // ----------------------------------------------------------------
    // getRandom
    // ----------------------------------------------------------------

    public function testGetRandomReturnsFetchAllResult(): void
    {
        $row1 = ['id' => 1, 'rating' => 5, 'comment' => 'Top !',  'display_name' => 'Sophie D.', 'location' => 'Paris',
                 'created_at' => '2026-04-01 10:00:00'];
        $row2 = ['id' => 2, 'rating' => 4, 'comment' => 'Bien !', 'display_name' => 'Marc L.',   'location' => 'Lyon',
                 'created_at' => '2026-04-02 10:00:00'];
        $row3 = ['id' => 3, 'rating' => 5, 'comment' => 'Great!', 'display_name' => 'James S.',  'location' => 'London',
                 'created_at' => '2026-04-03 10:00:00'];
        $expected = [$row1, $row2, $row3];

        $db = $this->createStub(Database::class);
        $db->method('fetchAll')->willReturn($expected);

        $result = $this->makeModel($db)->getRandom(3, 4);

        $this->assertCount(3, $result);
        $this->assertSame($expected, $result);
    }

    public function testGetRandomReturnsEmptyArrayWhenNoReviews(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->makeModel($db)->getRandom(3, 4));
    }

    public function testGetRandomPassesMinRatingToQuery(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('rating >= ?'),
                $this->callback(fn($p) => $p[0] === 5 && $p[1] === 2)
            )
            ->willReturn([]);

        $this->makeModel($db)->getRandom(2, 5);
    }

    // ----------------------------------------------------------------
    // getStats
    // ----------------------------------------------------------------

    /**
     * getStats retourne null si aucun avis (cnt = 0).
     */
    public function testGetStatsReturnsNullWhenNoReviews(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(['avg' => null, 'cnt' => '0']);

        $this->assertNull($this->makeModel($db)->getStats());
    }

    /**
     * getStats retourne null si fetchOne retourne false.
     */
    public function testGetStatsReturnsNullWhenFetchOneFalse(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(false);

        $this->assertNull($this->makeModel($db)->getStats());
    }

    /**
     * getStats retourne ['avg' => float, 'count' => int] quand des avis existent.
     */
    public function testGetStatsReturnsArrayWhenReviewsExist(): void
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn(['avg' => '4.5', 'cnt' => '3']);

        $result = $this->makeModel($db)->getStats();

        $this->assertIsArray($result);
        $this->assertSame(4.5, $result['avg']);
        $this->assertSame(3, $result['count']);
    }

    // ----------------------------------------------------------------
    // upsert
    // ----------------------------------------------------------------

    /**
     * upsert appelle execute avec les bons paramètres.
     */
    public function testUpsertCallsExecute(): void
    {
        $db = $this->createMock(Database::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT INTO'),
                $this->callback(fn($p) => $p[0] === 1 && $p[1] === 5)
            );

        $this->makeModel($db)->upsert(1, 5, 'Super vin', 'Sophie D.', 'Paris');
    }
}
