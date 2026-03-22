<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\PageController;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour PageController.
 * Couvre les 6 pages statiques : château, savoir-faire, contact,
 * mentions légales, plan du site, webmaster.
 */
class PageControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
    }

    private function makeController(string $uri = '/fr'): PageController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET = [];
        return new PageController(new Request());
    }

    // ----------------------------------------------------------------
    // chateau
    // ----------------------------------------------------------------

    public function testChateauRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/le-chateau')->chateau(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('chateau', $output);
    }

    // ----------------------------------------------------------------
    // savoirFaire
    // ----------------------------------------------------------------

    public function testSavoirFaireRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/savoir-faire')->savoirFaire(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('savoir', $output);
    }

    // ----------------------------------------------------------------
    // contact
    // ----------------------------------------------------------------

    public function testContactRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/contact')->contact(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('contact', $output);
    }

    // ----------------------------------------------------------------
    // mentionsLegales
    // ----------------------------------------------------------------

    public function testMentionsLegalesRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/mentions-legales')->mentionsLegales(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    // ----------------------------------------------------------------
    // planDuSite
    // ----------------------------------------------------------------

    public function testPlanDuSiteRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/plan-du-site')->planDuSite(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('sitemap', $output);
    }

    // ----------------------------------------------------------------
    // webmaster
    // ----------------------------------------------------------------

    public function testWebmasterRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/webmaster')->webmaster(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('webmaster', $output);
    }
}
