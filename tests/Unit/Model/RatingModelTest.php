<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\RatingModel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour RatingModel.
 * La base de données est mockée — aucune connexion réelle requise.
 */
#[AllowMockObjectsWithoutExpectations]
class RatingModelTest extends TestCase
{
    private Database $dbMock;
    private RatingModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);
        $this->model = new RatingModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // upsert
    // ----------------------------------------------------------------

    /**
     * upsert appelle execute une fois avec les bons paramètres.
     */
    public function testUpsertCallsExecute(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $this->model->upsert(1, 2, 3, 4);
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // getAvgByWine
    // ----------------------------------------------------------------

    /**
     * getAvgByWine retourne null si moins de 2 avis.
     */
    public function testGetAvgByWineReturnsNullWhenLessThanTwoRatings(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['avg' => '4.5', 'cnt' => '1']);

        $result = $this->model->getAvgByWine(1);
        $this->assertNull($result);
    }

    /**
     * getAvgByWine retourne null si aucun avis.
     */
    public function testGetAvgByWineReturnsNullWhenNoRatings(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['avg' => null, 'cnt' => '0']);

        $result = $this->model->getAvgByWine(1);
        $this->assertNull($result);
    }

    /**
     * getAvgByWine retourne un float si >= 2 avis.
     */
    public function testGetAvgByWineReturnsFloatWhenEnoughRatings(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['avg' => '4.3333', 'cnt' => '3']);

        $result = $this->model->getAvgByWine(1);
        $this->assertIsFloat($result);
        $this->assertSame(4.33, $result);
    }

    // ----------------------------------------------------------------
    // countByWine
    // ----------------------------------------------------------------

    /**
     * countByWine retourne 0 quand aucun avis.
     */
    public function testCountByWineReturnsZeroWhenEmpty(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['cnt' => '0']);

        $this->assertSame(0, $this->model->countByWine(1));
    }

    /**
     * countByWine retourne le bon nombre.
     */
    public function testCountByWineReturnsCorrectCount(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['cnt' => '5']);

        $this->assertSame(5, $this->model->countByWine(1));
    }

    // ----------------------------------------------------------------
    // getByAccountAndWine
    // ----------------------------------------------------------------

    /**
     * getByAccountAndWine retourne null si aucune ligne.
     */
    public function testGetByAccountAndWineReturnsNullWhenNotFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertNull($this->model->getByAccountAndWine(1, 2));
    }

    /**
     * getByAccountAndWine retourne un array si trouvé.
     */
    public function testGetByAccountAndWineReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'account_id' => 1, 'wine_id' => 2, 'rating' => 4];
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($row);

        $result = $this->model->getByAccountAndWine(1, 2);
        $this->assertIsArray($result);
        $this->assertSame(4, $result['rating']);
    }

    // ----------------------------------------------------------------
    // findByToken
    // ----------------------------------------------------------------

    /**
     * findByToken retourne null si token inconnu.
     */
    public function testFindByTokenReturnsNullWhenUnknown(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertNull($this->model->findByToken('unknowntoken'));
    }

    /**
     * findByToken retourne null si token déjà utilisé (token_used_at non null → pas retourné par la query).
     */
    public function testFindByTokenReturnsNullWhenAlreadyUsed(): void
    {
        // La requête SQL filtre token_used_at IS NULL, donc fetchOne retourne false
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertNull($this->model->findByToken('usedtoken'));
    }

    /**
     * findByToken retourne un array si token valide et non utilisé.
     */
    public function testFindByTokenReturnsArrayWhenValid(): void
    {
        $row = ['id' => 1, 'token' => 'abc123', 'token_used_at' => null];
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($row);

        $result = $this->model->findByToken('abc123');
        $this->assertIsArray($result);
        $this->assertSame('abc123', $result['token']);
    }

    // ----------------------------------------------------------------
    // markTokenUsed
    // ----------------------------------------------------------------

    /**
     * markTokenUsed appelle execute une fois.
     */
    public function testMarkTokenUsedCallsExecute(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $this->model->markTokenUsed('sometoken');
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // generateEmailToken
    // ----------------------------------------------------------------

    /**
     * generateEmailToken retourne un token hexadécimal de 64 caractères.
     */
    public function testGenerateEmailTokenReturns64CharHexToken(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $token = $this->model->generateEmailToken(1, 2, 3);

        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    /**
     * generateEmailToken génère des tokens différents à chaque appel.
     */
    public function testGenerateEmailTokenIsUnique(): void
    {
        $this->dbMock
            ->expects($this->exactly(2))
            ->method('execute');

        $token1 = $this->model->generateEmailToken(1, 2, 3);
        $token2 = $this->model->generateEmailToken(1, 2, 3);

        $this->assertNotSame($token1, $token2);
    }

    // ----------------------------------------------------------------
    // hasOrdered
    // ----------------------------------------------------------------

    /**
     * hasOrdered retourne false si aucune commande trouvée.
     */
    public function testHasOrderedReturnsFalseWhenNotOrdered(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertFalse($this->model->hasOrdered(1, 2));
    }

    /**
     * hasOrdered retourne true si une commande est trouvée.
     */
    public function testHasOrderedReturnsTrueWhenOrdered(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['id' => 1]);

        $this->assertTrue($this->model->hasOrdered(1, 2));
    }

    // ----------------------------------------------------------------
    // getAvgBulk
    // ----------------------------------------------------------------

    /**
     * getAvgBulk retourne un tableau vide si wineIds est vide.
     */
    public function testGetAvgBulkReturnsEmptyArrayWhenNoIds(): void
    {
        $this->dbMock
            ->expects($this->never())
            ->method('fetchAll');

        $result = $this->model->getAvgBulk([]);
        $this->assertSame([], $result);
    }

    /**
     * getAvgBulk retourne null pour avg si < 2 avis, float sinon.
     */
    public function testGetAvgBulkReturnsCorrectStructure(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                ['wine_id' => '1', 'avg' => '4.5', 'cnt' => '3'],
                ['wine_id' => '2', 'avg' => '3.0', 'cnt' => '1'],
            ]);

        $result = $this->model->getAvgBulk([1, 2]);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertSame(4.5, $result[1]); // >= 2 avis → float
        $this->assertNull($result[2]);       // < 2 avis → null
    }

    // ----------------------------------------------------------------
    // getTopRated
    // ----------------------------------------------------------------

    /**
     * getTopRated retourne le résultat de fetchAll.
     */
    public function testGetTopRatedReturnsFetchAllResult(): void
    {
        $rows = [
            ['wine_id' => '5', 'avg_rating' => '4.8', 'rating_count' => '10'],
            ['wine_id' => '3', 'avg_rating' => '4.5', 'rating_count' => '7'],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($rows);

        $result = $this->model->getTopRated(5);
        $this->assertCount(2, $result);
        $this->assertSame('4.8', $result[0]['avg_rating']);
    }

    // ----------------------------------------------------------------
    // getFirstOrderId
    // ----------------------------------------------------------------

    /**
     * getFirstOrderId retourne un int si une commande est trouvée.
     */
    public function testGetFirstOrderIdReturnsIntWhenFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['id' => 5]);

        $result = $this->model->getFirstOrderId(1, 2);
        $this->assertSame(5, $result);
    }

    /**
     * getFirstOrderId retourne null si aucune commande.
     */
    public function testGetFirstOrderIdReturnsNullWhenNotFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertNull($this->model->getFirstOrderId(1, 2));
    }

    // ----------------------------------------------------------------
    // allWinesRated
    // ----------------------------------------------------------------

    /**
     * allWinesRated retourne true sans query si la liste est vide.
     */
    public function testAllWinesRatedReturnsTrueForEmptyList(): void
    {
        $this->dbMock
            ->expects($this->never())
            ->method('fetchOne');

        $this->assertTrue($this->model->allWinesRated(1, []));
    }

    /**
     * allWinesRated retourne true si tous les vins sont notés.
     */
    public function testAllWinesRatedReturnsTrueWhenAllRated(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['cnt' => '3']);

        $this->assertTrue($this->model->allWinesRated(1, [10, 20, 30]));
    }

    /**
     * allWinesRated retourne false si la liste est incomplète.
     */
    public function testAllWinesRatedReturnsFalseWhenNotAllRated(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['cnt' => '1']);

        $this->assertFalse($this->model->allWinesRated(1, [10, 20, 30]));
    }

    // ----------------------------------------------------------------
    // getOrderedWinesWithRating
    // ----------------------------------------------------------------

    /**
     * getOrderedWinesWithRating retourne [] si aucune commande.
     */
    public function testGetOrderedWinesWithRatingReturnsEmptyWhenNoOrders(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $result = $this->model->getOrderedWinesWithRating(1);
        $this->assertSame([], $result);
    }

    /**
     * getOrderedWinesWithRating fait 2 appels fetchAll et retourne les vins avec notes.
     */
    public function testGetOrderedWinesWithRatingReturnsMappedWines(): void
    {
        $orders = [
            ['content' => json_encode([['wine_id' => 3], ['wine_id' => 7]])],
        ];
        $wines = [
            [
                'wine_id' => 3, 'label_name' => 'Vin A', 'vintage' => 2020,
                'slug' => 'vin-a', 'image_path' => null, 'is_cuvee_speciale' => 0,
                'rating' => 4, 'updated_at' => null,
            ],
            [
                'wine_id' => 7, 'label_name' => 'Vin B', 'vintage' => 2021,
                'slug' => 'vin-b', 'image_path' => null, 'is_cuvee_speciale' => 0,
                'rating' => null, 'updated_at' => null,
            ],
        ];

        $this->dbMock
            ->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls($orders, $wines);

        $result = $this->model->getOrderedWinesWithRating(1);
        $this->assertCount(2, $result);
        $this->assertSame(3, $result[0]['wine_id']);
    }

    /**
     * getOrderedWinesWithRating retourne [] si le contenu JSON ne contient aucun wine_id valide.
     */
    public function testGetOrderedWinesWithRatingReturnsEmptyWhenNoValidWineIds(): void
    {
        $orders = [
            ['content' => json_encode([['foo' => 'bar']])],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($orders);

        $result = $this->model->getOrderedWinesWithRating(1);
        $this->assertSame([], $result);
    }

    // ----------------------------------------------------------------
    // generateOrderToken
    // ----------------------------------------------------------------

    /**
     * generateOrderToken retourne le token existant si déjà présent.
     */
    public function testGenerateOrderTokenReturnsExistingToken(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['token' => 'existingtoken123']);

        $this->dbMock
            ->expects($this->never())
            ->method('execute');

        $result = $this->model->generateOrderToken(1, 2);
        $this->assertSame('existingtoken123', $result);
    }

    /**
     * generateOrderToken génère et stocke un nouveau token si aucun n'existe.
     */
    public function testGenerateOrderTokenCreatesNewTokenWhenNoneExists(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $result = $this->model->generateOrderToken(1, 2);
        $this->assertIsString($result);
        $this->assertSame(64, strlen($result));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result);
    }

    // ----------------------------------------------------------------
    // resolveOrderTokenFull
    // ----------------------------------------------------------------

    /**
     * resolveOrderTokenFull retourne null si le token est inconnu.
     */
    public function testResolveOrderTokenFullReturnsNullWhenNotFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $this->assertNull($this->model->resolveOrderTokenFull('unknowntoken'));
    }

    /**
     * resolveOrderTokenFull retourne le tableau complet si le token existe.
     */
    public function testResolveOrderTokenFullReturnsArrayWhenFound(): void
    {
        $row = [
            'order_id'   => 7,
            'account_id' => 3,
            'expires_at' => '2099-12-31 23:59:59',
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($row);

        $result = $this->model->resolveOrderTokenFull('sometoken');
        $this->assertIsArray($result);
        $this->assertSame(7, $result['order_id']);
        $this->assertArrayHasKey('expires_at', $result);
    }
}
