<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\HomeController;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour HomeController.
 * La BDD est requise car HomeController charge les dernières actualités.
 */
class HomeControllerTest extends IntegrationTestCase
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

    private function makeController(string $uri = '/fr'): HomeController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET = [];
        return new HomeController(new Request());
    }

    // ----------------------------------------------------------------
    // index — rendu de base
    // ----------------------------------------------------------------

    public function testIndexRendersMainContent(): void
    {
        ob_start();
        $this->makeController('/fr')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
    }

    public function testIndexRendersCarousel(): void
    {
        ob_start();
        $this->makeController('/fr')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('hero-carousel', $output);
    }

    public function testIndexRendersWithEmptyNews(): void
    {
        // BDD vide (transaction rollbackée) → $news = [] → pas d'erreur
        ob_start();
        $this->makeController('/fr')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('home-news', $output);
    }

    public function testIndexRendersNewsCards(): void
    {
        self::$db->insert(
            "INSERT INTO news (title, text_content, slug, created_at)
             VALUES (?, ?, ?, NOW())",
            [
                '{"fr":"Titre test","en":"Test title"}',
                '{"fr":"Contenu test","en":"Test content"}',
                'titre-test',
            ]
        );

        ob_start();
        $this->makeController('/fr')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('news-card', $output);
    }
}
