<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\WineController;
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
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
    }

    private function makeController(string $uri = '/fr/vins'): WineController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET = [];
        return new WineController(new Request());
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

    // ----------------------------------------------------------------
    // show
    // ----------------------------------------------------------------

    public function testShowRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/vins/sauternes-2021')
            ->show(['lang' => 'fr', 'slug' => 'sauternes-2021']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('sauternes-2021', $output);
    }
}
