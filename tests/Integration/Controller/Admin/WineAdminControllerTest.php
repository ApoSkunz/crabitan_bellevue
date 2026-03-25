<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\WineAdminController;
use Core\Exception\HttpException;

class WineAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): WineAdminController
    {
        return new WineAdminController($this->makeRequest($method, '/admin/vins'));
    }

    private function insertWine(): int
    {
        return (int) self::$db->insert(
            "INSERT INTO wines
             (label_name, wine_color, format, vintage, price, quantity, available,
              area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment, image_path, slug)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                'Vin TI', 'red', 'bottle', 2022, 18.50, 60, 1,
                '0.50', 'Saint-Émilion', 'Merlot 80%', 20,
                json_encode(['fr' => 'Desc TI', 'en' => 'TI desc']),
                json_encode(['fr' => 'Argile', 'en' => 'Clay']),
                json_encode(['fr' => 'Guyot', 'en' => 'Guyot']),
                json_encode(['fr' => 'Manuel', 'en' => 'Manual']),
                json_encode(['fr' => 'Inox', 'en' => 'Stainless']),
                json_encode(['fr' => '12 mois', 'en' => '12 months']),
                json_encode(['fr' => '', 'en' => '']),
                json_encode(['fr' => '', 'en' => '']),
                'Wine_Vin_TI_2022.png', 'vin-ti-2022',
            ]
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersWineList(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
        $this->assertStringContainsString('Vins', $output);
    }

    public function testIndexWithColorFilterRendersView(): void
    {
        $_GET['color']     = 'red';
        $_GET['available'] = 'available';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-filters', $output);
    }

    // ----------------------------------------------------------------
    // create
    // ----------------------------------------------------------------

    public function testCreateRendersForm(): void
    {
        ob_start();
        $this->makeController()->create([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('Ajouter', $output);
    }

    // ----------------------------------------------------------------
    // store — CSRF invalide
    // ----------------------------------------------------------------

    public function testStoreRedirectsOnInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->store([]);
    }

    // ----------------------------------------------------------------
    // store — CSRF valide, champs vides → parseWineForm déclenche erreurs
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithValidationErrors(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['label_name'] = '';
        $_POST['wine_color'] = '';
        $_POST['vintage']    = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // store — CSRF valide, label_name rempli, couleur invalide → erreurs
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithInvalidColor(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['label_name'] = 'Château Test';
        $_POST['wine_color'] = 'violet'; // invalide
        $_POST['vintage']    = '2022';
        $_POST['price']      = '20.00';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // edit — vin introuvable
    // ----------------------------------------------------------------

    public function testEditAborts404WhenNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->edit(['id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // edit — vin existant
    // ----------------------------------------------------------------

    public function testEditRendersFormWithExistingWine(): void
    {
        $id = $this->insertWine();

        ob_start();
        $this->makeController()->edit(['id' => (string) $id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('Modifier', $output);
    }

    // ----------------------------------------------------------------
    // update — vin introuvable
    // ----------------------------------------------------------------

    public function testUpdateAborts404WhenNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController('POST')->update(['id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // update — CSRF invalide
    // ----------------------------------------------------------------

    public function testUpdateRedirectsOnInvalidCsrf(): void
    {
        $id = $this->insertWine();
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // update — CSRF valide, champs vides → re-rendu avec erreurs
    // ----------------------------------------------------------------

    public function testUpdateRendersFormWithValidationErrors(): void
    {
        $id = $this->insertWine();
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['label_name'] = '';
        $_POST['wine_color'] = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->update(['id' => (string) $id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // update — CSRF valide, données valides → redirect 302
    // ----------------------------------------------------------------

    public function testUpdateSuccessRedirects(): void
    {
        $id = $this->insertWine();
        $_POST['csrf_token']          = self::CSRF_TOKEN;
        $_POST['appellation']         = 'Bordeaux Rouge';
        $_POST['wine_color']          = 'red';
        $_POST['format']              = 'bottle';
        $_POST['vintage']             = '2022';
        $_POST['price']               = '18.50';
        $_POST['quantity']            = '60';
        $_POST['area']                = '0.50';
        $_POST['city']                = 'Saint-Émilion';
        $_POST['variety_of_vine']     = 'Merlot';
        $_POST['age_of_vineyard']     = '20';
        $_POST['certification_label'] = 'AOC';
        $_POST['available']           = '1';
        $_POST['is_cuvee_speciale']   = '0';
        foreach (['oenological_comment', 'soil', 'pruning', 'harvest', 'vinification', 'barrel_fermentation'] as $f) {
            $_POST["{$f}_fr"] = "Valeur FR {$f}";
            $_POST["{$f}_en"] = "EN value {$f}";
        }
        $_FILES = [];

        $this->expectException(\Core\Exception\HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }
}
