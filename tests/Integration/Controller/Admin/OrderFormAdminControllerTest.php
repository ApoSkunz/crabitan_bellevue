<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\OrderFormAdminController;
use Core\Exception\HttpException;
use Model\OrderFormModel;

class OrderFormAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): OrderFormAdminController
    {
        return new OrderFormAdminController(
            $this->makeRequest($method, '/admin/bons-de-commande')
        );
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersOrderFormsList(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-card', $output);
        $this->assertStringContainsString('Bons de commande', $output);
    }

    public function testIndexRespectsPagination(): void
    {
        $_GET['per_page'] = '25';
        $_GET['page'] = '1';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    // ----------------------------------------------------------------
    // upload — CSRF invalide
    // ----------------------------------------------------------------

    public function testUploadRedirectsOnInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->upload([]);
    }

    // ----------------------------------------------------------------
    // upload — CSRF valide, année invalide
    // ----------------------------------------------------------------

    public function testUploadRedirectsOnInvalidYear(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year'] = '1800';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->upload([]);
    }

    // ----------------------------------------------------------------
    // upload — CSRF valide, année valide, pas de fichier
    // ----------------------------------------------------------------

    public function testUploadRedirectsOnMissingFile(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['year'] = '2025';
        $_FILES = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->upload([]);
    }

    // ----------------------------------------------------------------
    // delete — CSRF invalide
    // ----------------------------------------------------------------

    public function testDeleteRedirectsOnInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->delete(['id' => '1']);
    }

    // ----------------------------------------------------------------
    // delete — CSRF valide, id introuvable
    // ----------------------------------------------------------------

    public function testDeleteRedirectsWhenNotFound(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->delete(['id' => '999999']);
    }

    // ----------------------------------------------------------------
    // delete — CSRF valide, id existant (sans fichier physique)
    // ----------------------------------------------------------------

    public function testDeleteRemovesExistingRecord(): void
    {
        $model = new OrderFormModel();
        $id    = $model->create(2099, 'TI', 'ti_test.pdf');

        $_POST['csrf_token'] = self::CSRF_TOKEN;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->delete(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // download — id introuvable
    // ----------------------------------------------------------------

    public function testDownloadAborts404WhenNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->download(['id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }
}
