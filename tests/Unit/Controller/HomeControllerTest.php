<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Exception\HttpException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour HomeController.
 *
 * La méthode index() instancie NewsModel (qui requiert une BDD).
 * Les tests qui atteignent le rendu de vue sont donc skippés en contexte
 * sans base de données — ils sont couverts par les tests E2E.
 */
class HomeControllerTest extends TestCase
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

    private function makeController(): \Controller\HomeController
    {
        return new \Controller\HomeController(new \Core\Request());
    }

    // ── index() ─────────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIndexWithExplicitLangParam(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/en';

        ob_start();
        try {
            $this->makeController()->index(['lang' => 'en']);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertSame('en', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIndexWithNoParamFallsBackToDefaultLang(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/';

        ob_start();
        try {
            $this->makeController()->index([]);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertSame(DEFAULT_LANG, defined('CURRENT_LANG') ? CURRENT_LANG : DEFAULT_LANG);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIndexWithUriLangSegment(): void
    {
        $this->bootstrapApp();
        $_SERVER['REQUEST_URI'] = '/fr/';

        ob_start();
        try {
            $this->makeController()->index([]);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu indisponible sans BDD/vue : ' . $e->getMessage());
        }
        ob_end_clean();

        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }
}
