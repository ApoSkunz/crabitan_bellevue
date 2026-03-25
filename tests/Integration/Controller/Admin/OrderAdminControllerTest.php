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

    private function insertOrder(): int
    {
        $addressId = (int) self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone)
             VALUES (?, 'billing', 'Test', 'Order', 'M', '1 rue TI', 'Bordeaux', '33000', 'France', '0600000000')",
            [$this->adminId]
        );
        return (int) self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, id_billing_address, status)
             VALUES (?, ?, '[]', 99.00, 'card', ?, 'pending')",
            [$this->adminId, 'TI-ORD-' . uniqid(), $addressId]
        );
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
    // show — commande existante
    // ----------------------------------------------------------------

    public function testShowRendersOrderDetail(): void
    {
        $id = $this->insertOrder();

        ob_start();
        $this->makeController()->show(['id' => (string) $id]);
        $output = ob_get_clean();

        $this->assertStringContainsString('TI-ORD-', $output);
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
    // updateStatus — CSRF valide → redirect 302
    // ----------------------------------------------------------------

    public function testUpdateStatusRedirectsOnSuccess(): void
    {
        $id = $this->insertOrder();
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['status']     = 'paid';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->updateStatus(['id' => (string) $id]);
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

    // ----------------------------------------------------------------
    // uploadInvoice — CSRF valide, commande introuvable → 404
    // ----------------------------------------------------------------

    public function testUploadInvoiceAborts404WhenOrderNotFound(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_FILES = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController('POST')->uploadInvoice(['id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // uploadInvoice — CSRF valide, commande trouvée, aucun fichier → 302
    // ----------------------------------------------------------------

    public function testUploadInvoiceRedirectsWhenNoFile(): void
    {
        $id = $this->insertOrder();
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_FILES = [];

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController('POST')->uploadInvoice(['id' => (string) $id]);
    }

    // ----------------------------------------------------------------
    // downloadInvoice — commande introuvable → 404
    // ----------------------------------------------------------------

    public function testDownloadInvoiceAborts404WhenNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->downloadInvoice(['id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // downloadInvoice — commande sans facture → 404
    // ----------------------------------------------------------------

    public function testDownloadInvoiceAborts404WhenNoInvoice(): void
    {
        $id = $this->insertOrder(); // path_invoice = NULL

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->downloadInvoice(['id' => (string) $id]);
        } finally {
            ob_end_clean();
        }
    }
}
