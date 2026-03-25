<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\NewsAdminController;
use Core\Exception\HttpException;

class NewsAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): NewsAdminController
    {
        return new NewsAdminController($this->makeRequest($method, '/admin/actualites'));
    }

    private function insertArticle(): int
    {
        return (int) self::$db->insert(
            "INSERT INTO news (title, text_content, image_path, slug)
             VALUES (?, ?, 'test.jpg', 'test-article-ti')",
            [
                json_encode(['fr' => 'Article TI', 'en' => 'TI Article']),
                json_encode(['fr' => 'Contenu test', 'en' => 'Test content']),
            ]
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersNewsList(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
        $this->assertStringContainsString('Actualités', $output);
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
    // store — CSRF valide, champs manquants → re-rendu avec erreurs
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithErrorsOnMissingFields(): void
    {
        $_POST['csrf_token']    = self::CSRF_TOKEN;
        $_POST['title_fr']      = '';
        $_POST['text_content_fr'] = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('obligatoire', $output);
    }

    // ----------------------------------------------------------------
    // store — CSRF valide, title_fr rempli, contenu manquant → re-rendu
    // ----------------------------------------------------------------

    public function testStoreRendersFormWithContentError(): void
    {
        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['title_fr']        = 'Mon article test';
        $_POST['text_content_fr'] = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->store([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    // ----------------------------------------------------------------
    // edit — article introuvable
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
    // edit — article existant
    // ----------------------------------------------------------------

    public function testEditRendersFormWithExistingArticle(): void
    {
        $id = $this->insertArticle();

        ob_start();
        $this->makeController()->edit(['id' => (string) $id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
        $this->assertStringContainsString('Modifier', $output);
    }

    // ----------------------------------------------------------------
    // update — CSRF invalide
    // ----------------------------------------------------------------

    public function testUpdateRedirectsOnInvalidCsrf(): void
    {
        $id = $this->insertArticle();
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // update — article introuvable
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
    // update — CSRF valide, champs manquants → re-rendu avec erreurs
    // ----------------------------------------------------------------

    public function testUpdateRendersFormWithErrorsOnMissingFields(): void
    {
        $id = $this->insertArticle();
        $_POST['csrf_token']      = self::CSRF_TOKEN;
        $_POST['title_fr']        = '';
        $_POST['text_content_fr'] = '';
        $_FILES = [];

        ob_start();
        $this->makeController('POST')->update(['id' => (string) $id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }
}
