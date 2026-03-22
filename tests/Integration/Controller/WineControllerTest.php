<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\WineController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour WineController.
 */
class WineControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
    }

    private function makeController(string $uri = '/fr/vins'): WineController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        return new WineController(new Request());
    }

    private function insertWine(string $slug = 'test-wine-2020', string $color = 'sweet'): void
    {
        self::$db->insert(
            "INSERT INTO wines
                (label_name, wine_color, format, vintage, price, quantity, available,
                 area, city, variety_of_vine, age_of_vineyard,
                 oenological_comment, soil, pruning, harvest, vinification,
                 barrel_fermentation, award, extra_comment, image_path, slug)
             VALUES (?, ?, 'bottle', 2020, 22.00, 120, 1,
                     7.50, 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
                     '{\"fr\":\"Description test\",\"en\":\"Test description\"}',
                     '{\"fr\":\"Argile\",\"en\":\"Clay\"}',
                     '{\"fr\":\"Guyot mixte\",\"en\":\"Mixed Guyot\"}',
                     '{\"fr\":\"Manuelles\",\"en\":\"Manual\"}',
                     '{\"fr\":\"Cuves inox\",\"en\":\"Stainless steel\"}',
                     '{\"fr\":\"36 mois\",\"en\":\"36 months\"}',
                     '{\"fr\":\"\",\"en\":\"\"}',
                     '{\"fr\":\"\",\"en\":\"\"}',
                     'Wine_Test_2020.png', ?)",
            ['Test Wine', $color, $slug]
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('wines', $output);
    }

    public function testIndexRendersWineGrid(): void
    {
        $this->insertWine('test-sweet-2020', 'sweet');

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wines-grid', $output);
        $this->assertStringContainsString('wine-card', $output);
    }

    public function testIndexRendersEmptyStateWhenNoWines(): void
    {
        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        // Pas d'erreur, la vue se charge
        $this->assertStringContainsString('wines-catalog', $output);
    }

    public function testIndexFiltersWinesByColor(): void
    {
        $this->insertWine('test-red-2020', 'red');
        $this->insertWine('test-sweet-2020', 'sweet');

        $_GET['color'] = 'sweet';

        ob_start();
        $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('wine-card', $output);
    }

    // ----------------------------------------------------------------
    // collection
    // ----------------------------------------------------------------

    public function testCollectionRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('collection', $output);
    }

    public function testCollectionRendersGroupedWines(): void
    {
        $this->insertWine('test-sweet-col', 'sweet');
        $this->insertWine('test-red-col', 'red');

        ob_start();
        $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('collection-group', $output);
        $this->assertStringContainsString('wine-card', $output);
    }

    // ----------------------------------------------------------------
    // show
    // ----------------------------------------------------------------

    public function testShowRendersWineDetail(): void
    {
        $this->insertWine('sainte-croix-du-mont-2020');

        ob_start();
        $this->makeController('/fr/vins/sainte-croix-du-mont-2020')
            ->show(['lang' => 'fr', 'slug' => 'sainte-croix-du-mont-2020']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('wine-detail', $output);
        $this->assertStringContainsString('Test Wine', $output);
    }

    public function testShowWithInvalidSlugAborts404(): void
    {
        $this->expectException(HttpException::class);

        $this->makeController('/fr/vins/inexistant')
            ->show(['lang' => 'fr', 'slug' => 'inexistant']);
    }
}
