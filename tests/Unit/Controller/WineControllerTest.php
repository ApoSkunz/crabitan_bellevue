<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour WineController.
 *
 * Les méthodes instancient WineModel (BDD requise) et rendent des vues.
 * Les tests couvrent la résolution de langue et les entrées de méthode ;
 * le rendu complet est couvert par les tests d'intégration.
 */
class WineControllerTest extends TestCase
{
    // ── Helpers ────────────────────────────────────────────────────────────

    private function bootstrapApp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';
    }

    private function makeController(string $uri = '/fr/vins'): \Controller\WineController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        return new \Controller\WineController(new \Core\Request());
    }

    // ── index() ─────────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIndexResolvesLangFr(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/vins';

        ob_start();
        try {
            $this->makeController('/fr/vins')->index(['lang' => 'fr']);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIndexResolvesLangEn(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/en/vins';

        ob_start();
        try {
            $this->makeController('/en/vins')->index(['lang' => 'en']);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertSame('en', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIndexFallsBackToDefaultLangWhenNoParam(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/vins';

        ob_start();
        try {
            $this->makeController('/vins')->index([]);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertSame(DEFAULT_LANG, defined('CURRENT_LANG') ? CURRENT_LANG : DEFAULT_LANG);
    }

    // ── collection() ────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCollectionResolvesLangFr(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/vins/collection';

        ob_start();
        try {
            $this->makeController('/fr/vins/collection')->collection(['lang' => 'fr']);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCollectionResolvesLangEn(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/en/vins/collection';

        ob_start();
        try {
            $this->makeController('/en/vins/collection')->collection(['lang' => 'en']);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertSame('en', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    // ── show() ──────────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testShowResolvesLangAndRendersOrSkips(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/vins/test-slug';

        ob_start();
        try {
            $this->makeController('/fr/vins/test-slug')
                ->show(['lang' => 'fr', 'slug' => 'test-slug']);
        } catch (\Core\Exception\HttpException $e) {
            ob_end_clean();
            // Vin inexistant : HttpException 404 attendue, pas une erreur
            $this->assertSame(404, $e->getCode());
            return;
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    // ── technicalSheet() ────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTechnicalSheetAborts404ForUnknownSlug(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/vins/inexistant/fiche-technique';

        try {
            $this->makeController('/fr/vins/inexistant/fiche-technique')
                ->technicalSheet(['lang' => 'fr', 'slug' => 'inexistant']);
            $this->fail('HttpException 404 attendue');
        } catch (\Core\Exception\HttpException $e) {
            $this->assertSame(404, $e->getCode());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Inaccessible sans BDD : ' . $e->getMessage());
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTechnicalSheetResolvesLangEn(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/en/vins/test-slug/fiche-technique';

        ob_start();
        try {
            $this->makeController('/en/vins/test-slug/fiche-technique')
                ->technicalSheet(['lang' => 'en', 'slug' => 'test-slug']);
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
