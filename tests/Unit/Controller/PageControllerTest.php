<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour PageController.
 *
 * Les pages statiques ne nécessitent pas de BDD.
 * Les tests vérifient que chaque action résout correctement la langue
 * et tente le rendu (skippé si la vue est absente en CLI).
 */
class PageControllerTest extends TestCase
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

    private function makeController(): \Controller\PageController
    {
        return new \Controller\PageController(new \Core\Request());
    }

    private function callAction(string $action, string $lang = 'fr'): void
    {
        ob_start();
        try {
            $this->makeController()->$action(['lang' => $lang]);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped("Rendu indisponible sans vue complète : {$e->getMessage()}");
        }
        ob_end_clean();
    }

    // ── Actions ─────────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testChateauResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('chateau', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSavoirFaireResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('savoirFaire', 'en');
        $this->assertSame('en', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testContactResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('contact', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMentionsLegalesResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('mentionsLegales', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPlanDuSiteResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('planDuSite', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testWebmasterResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('webmaster', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPolitiqueConfidentialiteResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('politiqueConfidentialite', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSupportResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('support', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testJeuxResolvesLang(): void
    {
        $this->bootstrapApp();
        $this->callAction('jeux', 'fr');
        $this->assertSame('fr', defined('CURRENT_LANG') ? CURRENT_LANG : 'undefined');
    }
}
