<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\CartModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests de couverture complémentaires pour CartModel.
 *
 * Couvre les branches de getAbandonedForEmail() / enrichCartItems() :
 *  - Line 270 : enrichedItems vide → continue (tous les items ont wine_id = 0)
 *  - Line 303 : wineIds vide → return [] (items avec wine_id ≤ 0)
 *  - Line 323 : wineId absent de wineMap → continue (wine inexistant en BDD)
 */
#[AllowMockObjectsWithoutExpectations]
class CartModelCoverageTest extends TestCase
{
    private Database $dbMock;
    private CartModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);
        $this->model = new CartModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    /**
     * enrichCartItems() avec wine_id = 0 → wineIds vide → return [] (line 303).
     * getAbandonedForEmail() reçoit un cart dont enrichedItems est vide → continue (line 270).
     */
    public function testGetAbandonedForEmailSkipsCartWhenEnrichedItemsEmpty(): void
    {
        // Cart dont le contenu a wine_id = 0 (invalide → wineIds vide)
        $cartRow = [
            'cart_id'      => 1,
            'user_id'      => 42,
            'email'        => 'test@example.com',
            'lang'         => 'fr',
            'firstname'    => 'Jean',
            'company_name' => '',
            'content'      => json_encode([['wine_id' => 0, 'qty' => 2]]),
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn([$cartRow]);

        $result = $this->model->getAbandonedForEmail();

        // Le cart est sauté car enrichedItems est vide
        $this->assertSame([], $result);
    }

    /**
     * enrichCartItems() avec wine_id valide mais wine introuvable en BDD → enriched vide (line 323).
     */
    public function testGetAbandonedForEmailSkipsItemWhenWineNotInMap(): void
    {
        $cartRow = [
            'cart_id'      => 2,
            'user_id'      => 43,
            'email'        => 'client@example.com',
            'lang'         => 'fr',
            'firstname'    => 'Marie',
            'company_name' => '',
            'content'      => json_encode([['wine_id' => 99, 'qty' => 1]]),
        ];

        // Premier fetchAll = carts, second fetchAll = wines (renvoie [] = wine 99 introuvable)
        $this->dbMock
            ->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls([$cartRow], []);

        $result = $this->model->getAbandonedForEmail();

        // enrichedItems est vide (wine 99 absent du wineMap) → cart sauté
        $this->assertSame([], $result);
    }

    /**
     * enrichCartItems() avec wine_id valide, wine présent en BDD → cart retourné enrichi.
     * Couvre aussi la branche where wineMap has the id (line 323 NOT taken).
     */
    public function testGetAbandonedForEmailReturnsEnrichedCart(): void
    {
        $cartRow = [
            'cart_id'      => 3,
            'user_id'      => 44,
            'email'        => 'buyer@example.com',
            'lang'         => 'en',
            'firstname'    => 'John',
            'company_name' => '',
            'content'      => json_encode([['wine_id' => 5, 'qty' => 3]]),
        ];

        $wineRow = [
            'id'         => 5,
            'label_name' => 'Crabitan Blanc',
            'vintage'    => 2022,
            'image_path' => 'wines/blanc.jpg',
            'price'      => '18.50',
        ];

        $this->dbMock
            ->expects($this->exactly(2))
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls([$cartRow], [$wineRow]);

        $result = $this->model->getAbandonedForEmail();

        $this->assertCount(1, $result);
        $this->assertSame('buyer@example.com', $result[0]['email']);
        $this->assertCount(1, $result[0]['items']);
        $this->assertSame('Crabitan Blanc', $result[0]['items'][0]['label_name']);
    }
}
