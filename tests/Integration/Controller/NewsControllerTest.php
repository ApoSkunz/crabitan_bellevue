<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\NewsController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour NewsController.
 */
class NewsControllerTest extends IntegrationTestCase
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

    private function makeController(string $uri = '/fr/actualites'): NewsController
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET = [];
        return new NewsController(new Request());
    }

    private function insertNews(string $slug = 'actu-test'): void
    {
        self::$db->insert(
            "INSERT INTO news (title, text_content, slug, created_at)
             VALUES (?, ?, ?, NOW())",
            [
                '{"fr":"Actualité test","en":"Test news"}',
                '{"fr":"Contenu de test","en":"Test content"}',
                $slug,
            ]
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersView(): void
    {
        ob_start();
        $this->makeController('/fr/actualites')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('news', $output);
    }

    public function testIndexRendersNewsList(): void
    {
        $this->insertNews('actu-1');

        ob_start();
        $this->makeController('/fr/actualites')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('news-card', $output);
    }

    public function testIndexRendersSection(): void
    {
        ob_start();
        $this->makeController('/fr/actualites')->index(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('news-list', $output);
    }

    // ----------------------------------------------------------------
    // show
    // ----------------------------------------------------------------

    public function testShowRendersNewsArticle(): void
    {
        $this->insertNews('mon-vin-2024');

        ob_start();
        $this->makeController('/fr/actualites/mon-vin-2024')
            ->show(['lang' => 'fr', 'slug' => 'mon-vin-2024']);
        $output = ob_get_clean();

        $this->assertStringContainsString('<main', $output);
        $this->assertStringContainsString('Actualité test', $output);
    }

    public function testShowWithInvalidSlugAborts404(): void
    {
        $this->expectException(HttpException::class);

        $this->makeController('/fr/actualites/inexistant')
            ->show(['lang' => 'fr', 'slug' => 'inexistant']);
    }
}
