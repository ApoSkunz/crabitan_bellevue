<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\OrderAdminController;
use Core\Exception\HttpException;

class OrderAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): OrderAdminController
    {
        return new OrderAdminController($this->makeRequest($method, '/admin/commandes'));
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersOrdersList(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
        $this->assertStringContainsString('Commandes', $output);
    }

    public function testIndexWithFiltersRendersView(): void
    {
        $_GET['status']  = 'pending';
        $_GET['search']  = 'test';
        $_GET['payment'] = 'card';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-filters', $output);
    }

    public function testIndexWithInvalidPerPageUsesDefault(): void
    {
        $_GET['per_page'] = '999';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    // ----------------------------------------------------------------
    // show — id introuvable
    // ----------------------------------------------------------------

    public function testShowAborts404WhenNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->show(['id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // updateStatus — CSRF invalide
    // ----------------------------------------------------------------

    public function testUpdateStatusRedirectsOnInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->updateStatus(['id' => '1']);
    }

    // ----------------------------------------------------------------
    // uploadInvoice — CSRF invalide
    // ----------------------------------------------------------------

    public function testUploadInvoiceRedirectsOnInvalidCsrf(): void
    {
        $_POST['csrf_token'] = 'bad';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->uploadInvoice(['id' => '1']);
    }
}
