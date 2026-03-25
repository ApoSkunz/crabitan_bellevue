<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\PricingAdminController;
use Core\Exception\HttpException;

class PricingAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): PricingAdminController
    {
        return new PricingAdminController($this->makeRequest($method, '/admin/tarifs'));
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersPricingView(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-pricing-form', $output);
        $this->assertStringContainsString('Tarifs', $output);
    }

    // ----------------------------------------------------------------
    // update — CSRF invalide
    // ----------------------------------------------------------------

    public function testUpdateRedirectsOnInvalidCsrf(): void
    {
        $_SESSION['csrf'] = 'wrong-token';
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update([]);
    }

    // ----------------------------------------------------------------
    // update — CSRF valide, ids vide
    // ----------------------------------------------------------------

    public function testUpdateRedirectsOnSuccess(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['id'] = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update([]);
    }

    // ----------------------------------------------------------------
    // update — id invalide ignoré
    // ----------------------------------------------------------------

    public function testUpdateIgnoresInvalidId(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['id'] = [0, -1];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->update([]);
    }
}
