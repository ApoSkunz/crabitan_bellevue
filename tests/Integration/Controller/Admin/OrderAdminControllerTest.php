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

    // ----------------------------------------------------------------
    // downloadInvoice — facture enregistrée en BDD mais fichier absent → 404
    // ----------------------------------------------------------------

    public function testDownloadInvoiceAborts404WhenFileDoesNotExistOnDisk(): void
    {
        $id = $this->insertOrder();
        // Mise à jour directe du path_invoice avec un fichier inexistant
        self::$db->execute(
            "UPDATE orders SET path_invoice = 'storage/invoices/nonexistent_ti_invoice.pdf' WHERE id = ?",
            [$id]
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController()->downloadInvoice(['id' => (string) $id]);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // index — per_page valide à 25
    // ----------------------------------------------------------------

    public function testIndexWithValidPerPage25(): void
    {
        $_GET['per_page'] = '25';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    // ----------------------------------------------------------------
    // updateStatus — commande introuvable → 404
    // ----------------------------------------------------------------

    /**
     * Un id inconnu lève un 404 même avec un CSRF valide.
     */
    public function testUpdateStatusAborts404WhenOrderNotFound(): void
    {
        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['status']     = 'paid';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->makeController('POST')->updateStatus(['id' => '999999']);
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // updateStatus — commande déjà annulée → flash error + 302
    // ----------------------------------------------------------------

    /**
     * Une commande annulée ne peut pas changer de statut.
     */
    public function testUpdateStatusRedirectsWithErrorWhenAlreadyCancelled(): void
    {
        $id = $this->insertOrder();
        self::$db->execute("UPDATE orders SET status = 'cancelled' WHERE id = ?", [$id]);

        $_POST['csrf_token'] = self::CSRF_TOKEN;
        $_POST['status']     = 'paid';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        try {
            $this->makeController('POST')->updateStatus(['id' => (string) $id]);
        } catch (HttpException $e) {
            $row = self::$db->fetchOne("SELECT status FROM orders WHERE id = ?", [$id]);
            $this->assertSame('cancelled', $row['status']);
            $this->assertNotEmpty($_SESSION['admin_flash']['error'] ?? null);
            throw $e;
        }
    }
}
