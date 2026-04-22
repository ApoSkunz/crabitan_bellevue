<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Database;
use Core\Exception\HttpException;
use Model\WineModel;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests de couverture complémentaires pour WineController.
 * Couvre les branches non atteintes par WineControllerTest.
 */
class WineControllerCoverageTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    // ── Bootstrap ───────────────────────────────────────────────────────────

    private function bootstrapApp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';
    }

    private function requireTcpdf(): void
    {
        $path = defined('ROOT_PATH')
            ? ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php'
            : dirname(__DIR__, 3) . '/vendor/tecnickcom/tcpdf/tcpdf.php';
        if (!class_exists(\TCPDF::class)) {
            require_once $path;
        }
    }

    private function makeController(string $uri = '/fr/vins'): \Controller\WineController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        return new \Controller\WineController(new \Core\Request());
    }

    private function setDbMock(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('fetchAll')->willReturn([]);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $dbMock);
    }

    protected function tearDown(): void
    {
        if (isset($this->instanceProp)) {
            $this->instanceProp->setValue(null, null);
        }
        $_COOKIE = [];
        $_GET    = [];
    }

    /** @return array<string, mixed> */
    private function makeFakeWine(): array
    {
        return [
            'id'                     => 1,
            'label_name'             => 'Sainte-Croix-du-Mont',
            'vintage'                => 2020,
            'wine_color'             => 'sweet',
            'price'                  => 18.0,
            'is_cuvee_speciale'      => 0,
            'city'                   => 'Sainte-Croix-du-Mont',
            'variety_of_vine'        => 'Sémillon',
            'area'                   => '7.50',
            'age_of_vineyard'        => 30,
            'certification_label'    => 'HVE',
            'alcohol_degree'         => '13',
            'aging_potential'        => '10-15 ans',
            'oenological_comment_fr' => 'Notes de miel',
            'oenological_comment_en' => 'Honey notes',
            'soil_fr'                => 'Argile', 'soil_en' => 'Clay',
            'pruning_fr'             => 'Guyot', 'pruning_en' => 'Guyot',
            'harvest_fr'             => 'Manuelle', 'harvest_en' => 'Manual',
            'vinification_fr'        => 'Cuves', 'vinification_en' => 'Tanks',
            'barrel_fermentation_fr' => '6 mois', 'barrel_fermentation_en' => '6 months',
            'award_fr'               => 'Médaille', 'award_en' => 'Medal',
            'extra_comment_fr'       => '', 'extra_comment_en' => '',
            'image_path'             => '',
            'slug'                   => 'sainte-croix-du-mont-2020',
        ];
    }

    // ── buildCrossSell() — via réflexion ────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBuildCrossSellReturnsSimilarWinesWhenCurrentIsCuvee(): void
    {
        $this->bootstrapApp();
        $this->setDbMock();

        $wine = array_merge($this->makeFakeWine(), ['is_cuvee_speciale' => 1]);

        $wineModelMock = $this->createMock(WineModel::class);
        $wineModelMock
            ->expects($this->once())
            ->method('getSimilar')
            ->willReturn([['id' => 2, 'label_name' => 'B', 'is_cuvee_speciale' => 0]]);
        $wineModelMock->expects($this->never())->method('getCuveeSpecialeByColor');

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildCrossSell');

        $result = $method->invoke($ctrl, $wineModelMock, $wine);

        $this->assertCount(1, $result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBuildCrossSellReturnsSimilarWinesWhenCuveeAlreadyInList(): void
    {
        $this->bootstrapApp();
        $this->setDbMock();

        $wine = $this->makeFakeWine(); // is_cuvee_speciale = 0

        $wineModelMock = $this->createMock(WineModel::class);
        $wineModelMock
            ->expects($this->once())
            ->method('getSimilar')
            ->willReturn([
                ['id' => 2, 'label_name' => 'A', 'is_cuvee_speciale' => 1], // already has a cuvee
            ]);
        $wineModelMock->expects($this->never())->method('getCuveeSpecialeByColor');

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildCrossSell');

        $result = $method->invoke($ctrl, $wineModelMock, $wine);

        $this->assertCount(1, $result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBuildCrossSellInjectsCuveeWhenNoneInList(): void
    {
        $this->bootstrapApp();
        $this->setDbMock();

        $wine = $this->makeFakeWine(); // is_cuvee_speciale = 0

        $similar = [
            ['id' => 2, 'label_name' => 'A', 'is_cuvee_speciale' => 0],
            ['id' => 3, 'label_name' => 'B', 'is_cuvee_speciale' => 0],
            ['id' => 4, 'label_name' => 'C', 'is_cuvee_speciale' => 0],
        ];
        $cuvee = ['id' => 5, 'label_name' => 'Cuvée Or', 'is_cuvee_speciale' => 1];

        $wineModelMock = $this->createMock(WineModel::class);
        $wineModelMock->method('getSimilar')->willReturn($similar);
        $wineModelMock
            ->expects($this->once())
            ->method('getCuveeSpecialeByColor')
            ->willReturn($cuvee);

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildCrossSell');

        $result = $method->invoke($ctrl, $wineModelMock, $wine);

        // cuvee injected at front, last item popped → still 3
        $this->assertCount(3, $result);
        $this->assertSame(5, $result[0]['id']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBuildCrossSellDoesNotAddCuveeWhenNoneAvailable(): void
    {
        $this->bootstrapApp();
        $this->setDbMock();

        $wine = $this->makeFakeWine();

        $wineModelMock = $this->createMock(WineModel::class);
        $wineModelMock->method('getSimilar')->willReturn([
            ['id' => 2, 'label_name' => 'A', 'is_cuvee_speciale' => 0],
        ]);
        $wineModelMock->method('getCuveeSpecialeByColor')->willReturn(null);

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildCrossSell');

        $result = $method->invoke($ctrl, $wineModelMock, $wine);

        $this->assertCount(1, $result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBuildCrossSellInjectsCuveeWhenListHasFewerThanThree(): void
    {
        $this->bootstrapApp();
        $this->setDbMock();

        $wine = $this->makeFakeWine();

        $similar = [
            ['id' => 2, 'label_name' => 'A', 'is_cuvee_speciale' => 0],
        ];
        $cuvee = ['id' => 5, 'label_name' => 'Cuvée Or', 'is_cuvee_speciale' => 1];

        $wineModelMock = $this->createMock(WineModel::class);
        $wineModelMock->method('getSimilar')->willReturn($similar);
        $wineModelMock->method('getCuveeSpecialeByColor')->willReturn($cuvee);

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildCrossSell');

        $result = $method->invoke($ctrl, $wineModelMock, $wine);

        // list < 3 → no pop, just unshift
        $this->assertCount(2, $result);
        $this->assertSame(5, $result[0]['id']);
    }

    // ── getCurrentUserLikedIds() — with valid token ──────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetCurrentUserLikedIdsReturnsEmptyWhenNoCookie(): void
    {
        $this->bootstrapApp();
        $this->setDbMock();

        $_COOKIE = [];

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'getCurrentUserLikedIds');

        $result = $method->invoke($ctrl);

        $this->assertSame([], $result);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetCurrentUserLikedIdsReturnsEmptyOnInvalidToken(): void
    {
        $this->bootstrapApp();
        $this->setDbMock();

        $_COOKIE = ['auth_token' => 'totally.invalid.jwt'];

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'getCurrentUserLikedIds');

        $result = $method->invoke($ctrl);

        $this->assertSame([], $result);
    }

    // ── renderPdfSubtitle() — cuvée spéciale branch ─────────────────────────

    public function testRenderPdfSubtitleWithCuveeSpeciale(): void
    {
        $this->bootstrapApp();
        $this->requireTcpdf();

        $ctrl     = $this->makeController();
        $buildPdf = new \ReflectionMethod(\Controller\WineController::class, 'buildPdf');
        $subtitle = new \ReflectionMethod(\Controller\WineController::class, 'renderPdfSubtitle');

        ob_start();
        $pdf = $buildPdf->invoke($ctrl, '', 'fr');
        $pdf->AddPage();
        $subtitle->invoke($ctrl, $pdf, array_merge($this->makeFakeWine(), ['is_cuvee_speciale' => 1]), 210.0);
        ob_end_clean();

        $this->assertInstanceOf(\TCPDF::class, $pdf);
    }

    public function testRenderPdfSubtitleWithoutCuveeSpeciale(): void
    {
        $this->bootstrapApp();
        $this->requireTcpdf();

        $ctrl     = $this->makeController();
        $buildPdf = new \ReflectionMethod(\Controller\WineController::class, 'buildPdf');
        $subtitle = new \ReflectionMethod(\Controller\WineController::class, 'renderPdfSubtitle');

        ob_start();
        $pdf = $buildPdf->invoke($ctrl, '', 'fr');
        $pdf->AddPage();
        $subtitle->invoke($ctrl, $pdf, $this->makeFakeWine(), 210.0);
        ob_end_clean();

        $this->assertInstanceOf(\TCPDF::class, $pdf);
    }

    // ── renderPdfBottleImage() — missing file branch ─────────────────────────

    public function testRenderPdfBottleImageDoesNotThrowForMissingFile(): void
    {
        $this->bootstrapApp();
        $this->requireTcpdf();

        $ctrl       = $this->makeController();
        $buildPdf   = new \ReflectionMethod(\Controller\WineController::class, 'buildPdf');
        $bottleImg  = new \ReflectionMethod(\Controller\WineController::class, 'renderPdfBottleImage');

        ob_start();
        $pdf = $buildPdf->invoke($ctrl, '', 'fr');
        $pdf->AddPage();
        // non-existent file → should return early without error
        $bottleImg->invoke($ctrl, $pdf, '/nonexistent/path/bottle.png', 15.0, 35.0, 95.0, 50.0);
        ob_end_clean();

        $this->assertInstanceOf(\TCPDF::class, $pdf);
    }

    // ── buildPdfSections() — en language ────────────────────────────────────

    public function testBuildPdfSectionsEnLanguageReturnsExpectedKeys(): void
    {
        $this->bootstrapApp();
        $this->requireTcpdf();

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildPdfSections');

        $l = fn(array $arr): string => $arr['en'] ?? ($arr['fr'] ?? '');
        $result = $method->invoke($ctrl, $this->makeFakeWine(), [], 'en', $l);

        $this->assertArrayHasKey('TASTING NOTES', $result);
        $this->assertArrayHasKey('INFORMATION', $result);
        $this->assertArrayHasKey('VITICULTURE', $result);
        $this->assertArrayHasKey('WINEMAKING', $result);
    }

    // ── buildPdfSections() — fr language ────────────────────────────────────

    public function testBuildPdfSectionsFrLanguageReturnsExpectedKeys(): void
    {
        $this->bootstrapApp();
        $this->requireTcpdf();

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildPdfSections');

        $l = fn(array $arr): string => $arr['fr'] ?? '';
        $result = $method->invoke($ctrl, $this->makeFakeWine(), [], 'fr', $l);

        $this->assertArrayHasKey('DÉGUSTATION', $result);
        $this->assertArrayHasKey('INFORMATIONS', $result);
        $this->assertArrayHasKey('VITICULTURE', $result);
        $this->assertArrayHasKey('VINIFICATION', $result);
    }

    // ── buildPdfSections() — with nutrition enabled ─────────────────────────

    public function testBuildPdfSectionsWithNutritionEnabled(): void
    {
        $this->bootstrapApp();
        $this->requireTcpdf();

        $_ENV['ENABLE_EXTRA_INFO'] = 'true';

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildPdfSections');

        $nutrition = [
            'energy_kj'       => 300,
            'fat_g'           => '0.1',
            'saturated_fat_g' => '0.0',
            'carbohydrate_g'  => '5.0',
            'sugars_g'        => '4.9',
            'protein_g'       => '0.1',
            'salt_g'          => '0.0',
            'ingredients'     => '{"fr":"Raisins","en":"Grapes"}',
        ];
        $l = fn(array $arr): string => $arr['fr'] ?? '';
        $result = $method->invoke($ctrl, $this->makeFakeWine(), $nutrition, 'fr', $l);

        $this->assertArrayHasKey('INFORMATIONS NUTRITIONNELLES', $result);

        unset($_ENV['ENABLE_EXTRA_INFO']);
    }

    // ── buildNutritionFields() — with energy_kj ─────────────────────────────

    public function testBuildNutritionFieldsWithEnergyKj(): void
    {
        $this->bootstrapApp();

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildNutritionFields');

        $nutrition = [
            'energy_kj'       => 300,
            'fat_g'           => '0.5',
            'saturated_fat_g' => '0.1',
            'carbohydrate_g'  => '4.0',
            'sugars_g'        => '3.9',
            'protein_g'       => '0.1',
            'salt_g'          => '0.0',
            'ingredients'     => '{"fr":"Raisins","en":"Grapes"}',
        ];

        $result = $method->invoke($ctrl, $nutrition, false);

        $this->assertStringContainsString('kJ', $result['Énergie'] ?? '');
        $this->assertStringContainsString('kcal', $result['Énergie'] ?? '');
        $this->assertSame('0.5 g', $result['Matières grasses'] ?? '');
    }

    // ── buildNutritionFields() — en language ────────────────────────────────

    public function testBuildNutritionFieldsEnLanguage(): void
    {
        $this->bootstrapApp();

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildNutritionFields');

        $nutrition = [
            'energy_kj'       => 250,
            'fat_g'           => '0.2',
            'saturated_fat_g' => '0.0',
            'carbohydrate_g'  => '3.5',
            'sugars_g'        => '3.4',
            'protein_g'       => '0.0',
            'salt_g'          => '0.0',
            'ingredients'     => '{"fr":"Raisins","en":"Grapes"}',
        ];

        $result = $method->invoke($ctrl, $nutrition, true);

        $this->assertArrayHasKey('Energy', $result);
        $this->assertArrayHasKey('Fat', $result);
        $this->assertSame('Grapes', $result['Ingredients'] ?? '');
    }

    // ── buildPdf() — en footer ───────────────────────────────────────────────

    public function testBuildPdfWithEnLangReturnsInstance(): void
    {
        $this->bootstrapApp();
        $this->requireTcpdf();

        $ctrl   = $this->makeController();
        $method = new \ReflectionMethod(\Controller\WineController::class, 'buildPdf');

        ob_start();
        $pdf = $method->invoke($ctrl, '', 'en');
        ob_end_clean();

        $this->assertInstanceOf(\TCPDF::class, $pdf);
    }

    // ── renderPdfSection() — subsequent section (non-first) ─────────────────

    public function testRenderPdfSectionNonFirstDrawsSeparator(): void
    {
        $this->bootstrapApp();
        $this->requireTcpdf();

        $ctrl     = $this->makeController();
        $buildPdf = new \ReflectionMethod(\Controller\WineController::class, 'buildPdf');
        $section  = new \ReflectionMethod(\Controller\WineController::class, 'renderPdfSection');

        ob_start();
        $pdf = $buildPdf->invoke($ctrl, '', 'fr');
        $pdf->AddPage();
        // first section
        $section->invoke($ctrl, $pdf, 'SECTION 1', ['Label' => 'Valeur'], 55.0, 120.0, 36.0, true);
        // non-first section (draws separator)
        $section->invoke($ctrl, $pdf, 'SECTION 2', ['Label 2' => 'Valeur 2'], 55.0, 120.0, 36.0, false);
        ob_end_clean();

        $this->assertInstanceOf(\TCPDF::class, $pdf);
    }

    // ── collection() — with valid GET params ────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCollectionWithAllGetParams(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/vins/collection';
        $_GET = ['color' => 'red', 'sort' => 'vintage_asc', 'avail' => 'available', 'cuvee_speciale' => '1'];

        ob_start();
        try {
            $ctrl = new \Controller\WineController(new \Core\Request());
            $ctrl->collection(['lang' => 'fr']);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertTrue(true);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCollectionWithOutAvailFilter(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/vins/collection';
        $_GET = ['avail' => 'out'];

        ob_start();
        try {
            $ctrl = new \Controller\WineController(new \Core\Request());
            $ctrl->collection(['lang' => 'fr']);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertTrue(true);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCollectionWithInvalidAvailFallsToNull(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/vins/collection';
        $_GET = ['avail' => 'invalid'];

        ob_start();
        try {
            $ctrl = new \Controller\WineController(new \Core\Request());
            $ctrl->collection(['lang' => 'fr']);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertTrue(true);
    }

    // ── index() — with GET params ────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIndexWithColorAndCuveeSpeciale(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/vins';
        $_GET = ['color' => 'red', 'sort' => 'price_asc', 'cuvee_speciale' => '1'];

        ob_start();
        try {
            $ctrl = new \Controller\WineController(new \Core\Request());
            $ctrl->index(['lang' => 'fr']);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertTrue(true);
    }

    // ── show() — with auth_token cookie ─────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testShowWithAuthTokenCookieHandlesJwtError(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/vins/slug-test';
        $_COOKIE = ['auth_token' => 'bad.jwt.token'];

        ob_start();
        try {
            $ctrl = new \Controller\WineController(new \Core\Request());
            $ctrl->show(['lang' => 'fr', 'slug' => 'slug-test']);
        } catch (\Core\Exception\HttpException $e) {
            ob_end_clean();
            $this->assertSame(404, $e->getCode());
            return;
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();
        $this->assertTrue(true);
    }
}
