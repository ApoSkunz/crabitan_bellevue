<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\WineModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour WineModel::decrementStock().
 * La BDD est mockée — aucune connexion réelle.
 */
#[AllowMockObjectsWithoutExpectations]
class WineModelStockTest extends TestCase
{
    private Database $dbMock;
    private WineModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new WineModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // decrementStock()
    // ----------------------------------------------------------------

    /**
     * decrementStock() exécute un UPDATE avec GREATEST(0, stock_quantity - qty).
     */
    public function testDecrementStockExecutesUpdate(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('GREATEST(0, stock_quantity -'),
                [5, 42]
            );

        $this->model->decrementStock(42, 5);
    }

    /**
     * decrementStock() passe bien le bon wineId et qty dans les paramètres.
     */
    public function testDecrementStockPassesCorrectParams(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->anything(),
                $this->equalTo([1, 10])
            );

        $this->model->decrementStock(10, 1);
    }

    /**
     * Décrement de 0 n'exécute quand même la requête (robustesse — le modèle ne filtre pas).
     */
    public function testDecrementStockWithZeroQtyStillExecutes(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute');

        $this->model->decrementStock(1, 0);
    }
}
