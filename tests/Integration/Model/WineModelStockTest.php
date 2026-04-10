<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\WineModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour WineModel::decrementStock() et champ stock_quantity.
 *
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class WineModelStockTest extends IntegrationTestCase
{
    private WineModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new WineModel();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un vin minimal avec stock_quantity et retourne son ID.
     *
     * @param int $stockQty Stock initial
     * @return int
     */
    private function insertWine(int $stockQty = 10): int
    {
        return (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, production_quantity, stock_quantity,
              available, certification_label, area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment,
              is_cuvee_speciale, image_path, slug)
             VALUES ('Bordeaux Rouge', 'red', 'bottle', 2022, 15.00, 500, ?,
                     1, 'AOC', 5.00, 'Bordeaux', 'Merlot', 20,
                     '{\"fr\":\"test\",\"en\":\"test\"}',
                     '{\"fr\":\"test\",\"en\":\"test\"}',
                     '{\"fr\":\"test\",\"en\":\"test\"}',
                     '{\"fr\":\"test\",\"en\":\"test\"}',
                     '{\"fr\":\"test\",\"en\":\"test\"}',
                     '{\"fr\":\"test\",\"en\":\"test\"}',
                     '{\"fr\":\"\",\"en\":\"\"}',
                     '{\"fr\":\"\",\"en\":\"\"}',
                     0, 'test_stock_img.jpg', ?)",
            [$stockQty, 'bordeaux-rouge-2022-stock-' . uniqid()]
        );
    }

    // ----------------------------------------------------------------
    // decrementStock()
    // ----------------------------------------------------------------

    /**
     * decrementStock() réduit le stock du montant demandé.
     */
    public function testDecrementStockReducesQuantity(): void
    {
        $wineId = $this->insertWine(10);

        $this->model->decrementStock($wineId, 3);

        $row = self::$db->fetchOne('SELECT stock_quantity FROM wines WHERE id = ?', [$wineId]);
        $this->assertSame(7, (int) $row['stock_quantity']);
    }

    /**
     * decrementStock() ne passe pas en négatif — plancher à 0 (GREATEST).
     */
    public function testDecrementStockClampsAtZero(): void
    {
        $wineId = $this->insertWine(5);

        $this->model->decrementStock($wineId, 99);

        $row = self::$db->fetchOne('SELECT stock_quantity FROM wines WHERE id = ?', [$wineId]);
        $this->assertSame(0, (int) $row['stock_quantity']);
    }

    /**
     * decrementStock() avec qty = 0 laisse le stock inchangé.
     */
    public function testDecrementStockWithZeroQtyDoesNotChange(): void
    {
        $wineId = $this->insertWine(8);

        $this->model->decrementStock($wineId, 0);

        $row = self::$db->fetchOne('SELECT stock_quantity FROM wines WHERE id = ?', [$wineId]);
        $this->assertSame(8, (int) $row['stock_quantity']);
    }

    // ----------------------------------------------------------------
    // getById() inclut stock_quantity
    // ----------------------------------------------------------------

    /**
     * getById() retourne bien stock_quantity dans les données du vin.
     */
    public function testGetByIdIncludesStockQuantity(): void
    {
        $wineId = $this->insertWine(25);

        $wine = $this->model->getById($wineId);

        $this->assertNotNull($wine);
        $this->assertArrayHasKey('stock_quantity', $wine);
        $this->assertSame(25, (int) $wine['stock_quantity']);
    }

    // ----------------------------------------------------------------
    // create() + update() persistance stock_quantity
    // ----------------------------------------------------------------

    /**
     * create() stocke bien stock_quantity.
     */
    public function testCreateStoresStockQuantity(): void
    {
        $data = [
            'label_name'          => 'Bordeaux Rouge',
            'wine_color'          => 'red',
            'format'              => 'bottle',
            'vintage'             => 2021,
            'price'               => 18.00,
            'production_quantity' => 600,
            'stock_quantity'      => 42,
            'available'           => 1,
            'certification_label' => 'AOC',
            'area'                => 4.5,
            'city'                => 'Bordeaux',
            'variety_of_vine'     => 'Cabernet Sauvignon',
            'age_of_vineyard'     => 30,
            'oenological_comment' => '{"fr":"test","en":"test"}',
            'soil'                => '{"fr":"test","en":"test"}',
            'pruning'             => '{"fr":"test","en":"test"}',
            'harvest'             => '{"fr":"test","en":"test"}',
            'vinification'        => '{"fr":"test","en":"test"}',
            'barrel_fermentation' => '{"fr":"test","en":"test"}',
            'award'               => '{"fr":"","en":""}',
            'extra_comment'       => '{"fr":"","en":""}',
            'is_cuvee_speciale'   => 0,
            'image_path'          => 'test_create_stock.jpg',
            'slug'                => 'bordeaux-rouge-2021-ti-' . uniqid(),
            'alcohol_degree'      => null,
            'aging_potential'     => null,
        ];

        $id = $this->model->create($data);

        $row = self::$db->fetchOne('SELECT stock_quantity FROM wines WHERE id = ?', [$id]);
        $this->assertSame(42, (int) $row['stock_quantity']);
    }
}
