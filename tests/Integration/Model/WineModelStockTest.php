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
              oenological_comment_fr, oenological_comment_en,
              soil_fr, soil_en, pruning_fr, pruning_en,
              harvest_fr, harvest_en, vinification_fr, vinification_en,
              barrel_fermentation_fr, barrel_fermentation_en,
              award_fr, award_en, extra_comment_fr, extra_comment_en,
              is_cuvee_speciale, image_path, slug)
             VALUES ('Bordeaux Rouge', 'red', 'bottle', 2022, 15.00, 500, ?,
                     1, 'AOC', 5.00, 'Bordeaux', 'Merlot', 20,
                     'test', 'test', 'test', 'test', 'test', 'test',
                     'test', 'test', 'test', 'test', 'test', 'test',
                     '', '', '', '',
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

    // ----------------------------------------------------------------
    // setLowStockNotifiedAt()
    // ----------------------------------------------------------------

    /**
     * setLowStockNotifiedAt() met à jour la colonne low_stock_notified_at.
     */
    public function testSetLowStockNotifiedAtStoresTimestamp(): void
    {
        $wineId = $this->insertWine(100);

        // Avant : NULL
        $before = self::$db->fetchOne('SELECT low_stock_notified_at FROM wines WHERE id = ?', [$wineId]);
        $this->assertNull($before['low_stock_notified_at']);

        $this->model->setLowStockNotifiedAt($wineId);

        $after = self::$db->fetchOne('SELECT low_stock_notified_at FROM wines WHERE id = ?', [$wineId]);
        $this->assertNotNull($after['low_stock_notified_at']);
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
            'oenological_comment_fr' => 'test',
            'oenological_comment_en' => 'test',
            'soil_fr'                => 'test',
            'soil_en'                => 'test',
            'pruning_fr'             => 'test',
            'pruning_en'             => 'test',
            'harvest_fr'             => 'test',
            'harvest_en'             => 'test',
            'vinification_fr'        => 'test',
            'vinification_en'        => 'test',
            'barrel_fermentation_fr' => 'test',
            'barrel_fermentation_en' => 'test',
            'award_fr'               => '',
            'award_en'               => '',
            'extra_comment_fr'       => '',
            'extra_comment_en'       => '',
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
