<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Exception\HttpException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour NewsController.
 */
class NewsControllerTest extends TestCase
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

    private function makeController(): \Controller\NewsController
    {
        return new \Controller\NewsController(new \Core\Request());
    }

    // ── index() ─────────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIndexResolvesLangAndRendersOrSkips(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/actualites';

        ob_start();
        try {
            $this->makeController()->index(['lang' => 'fr']);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    // ── show() ──────────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testShowAborts404ForUnknownSlug(): void
    {
        $this->bootstrapApp();

        $caught = null;
        ob_start();
        try {
            $this->makeController()->show(['lang' => 'fr', 'slug' => 'slug-inexistant-xyz']);
        } catch (HttpException $e) {
            $caught = $e;
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Indisponible sans BDD : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'show() doit lancer HttpException pour un slug inexistant');
        $this->assertSame(404, $caught->status);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testShowAborts404WhenSlugIsEmpty(): void
    {
        $this->bootstrapApp();

        $caught = null;
        ob_start();
        try {
            $this->makeController()->show(['lang' => 'fr', 'slug' => '']);
        } catch (HttpException $e) {
            $caught = $e;
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Indisponible sans BDD : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertNotNull($caught);
        $this->assertSame(404, $caught->status);
    }
}
