<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\InvoiceController;
use Core\Exception\HttpException;
use Core\Jwt;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * TI pour InvoiceController (liste et téléchargement de factures côté client).
 */
class InvoiceControllerTest extends IntegrationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';

        $this->userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', NOW())",
            ['invoice-ti@test.local', password_hash('Pass123!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Client', 'Test', 'M')",
            [$this->userId]
        );
        $token = Jwt::generate($this->userId, 'customer');
        $_COOKIE['auth_token'] = $token;
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$this->userId, $token]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE = [];
        $_SESSION = [];
    }

    private function makeController(string $method = 'GET', string $uri = '/fr/mon-compte/factures'): InvoiceController
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        return new InvoiceController(new Request());
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function insertOrderWithInvoice(string $invoicePath = 'storage/pdf/invoices/test.pdf'): int
    {
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (civility, firstname, lastname, street, city, zip, country, phone)
             VALUES ('M', 'Test', 'Client', '1 rue TI', 'Paris', '75001', 'France', '0600000000')"
        );
        $orderId = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, id_billing_snapshot,
              status, invoice_number, path_invoice)
             VALUES (?, ?, '[]', 99.00, 'card', ?, 'paid', 'WEB-INV-TI-0001', ?)",
            [$this->userId, 'TI-INV-' . uniqid(), $snapshotId, $invoicePath]
        );
        self::$db->insert(
            "INSERT INTO order_invoice_files (order_id, path, invoice_number, source)
             VALUES (?, ?, 'WEB-INV-TI-0001', 'auto')",
            [$orderId, $invoicePath]
        );
        return $orderId;
    }

    // ----------------------------------------------------------------
    // list()
    // ----------------------------------------------------------------

    /**
     * GET /fr/mon-compte/factures — rendu de la vue pour un utilisateur connecté.
     */
    public function testListRendersViewForAuthenticatedUser(): void
    {
        $this->insertOrderWithInvoice();

        ob_start();
        $this->makeController()->list(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-table-wrap', $output);
        $this->assertStringContainsString('WEB-INV-TI-0001', $output);
    }

    /**
     * Un client sans facture voit le message vide.
     */
    public function testListRendersEmptyStateWhenNoInvoices(): void
    {
        ob_start();
        $this->makeController()->list(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('account-empty', $output);
    }

    // ----------------------------------------------------------------
    // download() — cas d'erreur
    // ----------------------------------------------------------------

    /**
     * 404 si la commande n'existe pas.
     */
    public function testDownloadAborts404WhenOrderNotFound(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController()->download(['lang' => 'fr', 'id' => '99999']);
    }

    /**
     * 404 si la commande existe mais n'a pas de facture.
     */
    public function testDownloadAborts404WhenNoInvoicePath(): void
    {
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (civility, firstname, lastname, street, city, zip, country, phone)
             VALUES ('M', 'Test', 'NoInv', '1 rue', 'Paris', '75001', 'France', '0600000000')"
        );
        $orderId = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, id_billing_snapshot, status)
             VALUES (?, ?, '[]', 50.00, 'card', ?, 'paid')",
            [$this->userId, 'TI-NOINV-' . uniqid(), $snapshotId]
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController()->download(['lang' => 'fr', 'id' => (string) $orderId]);
    }

    /**
     * 404 si le chemin de la facture est renseigné mais le fichier n'existe pas sur le disque.
     */
    public function testDownloadAborts404WhenFileNotOnDisk(): void
    {
        $orderId = $this->insertOrderWithInvoice('storage/pdf/invoices/does-not-exist.pdf');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        $this->makeController()->download(['lang' => 'fr', 'id' => (string) $orderId]);
    }

    /**
     * 200 + readfile si le fichier existe sur le disque.
     */
    public function testDownloadServesFileWhenExists(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ti_inv_') . '.pdf';
        file_put_contents($tmpFile, '%PDF-1.4 fake content');
        $relativePath = 'storage/pdf/invoices/' . basename($tmpFile);

        // Crée le fichier à l'emplacement attendu par ROOT_PATH
        $absDir = ROOT_PATH . '/storage/pdf/invoices';
        if (!is_dir($absDir)) {
            mkdir($absDir, 0755, true);
        }
        $absPath = $absDir . '/' . basename($tmpFile);
        copy($tmpFile, $absPath);

        $orderId = $this->insertOrderWithInvoice($relativePath);

        try {
            ob_start();
            $this->makeController()->download(['lang' => 'fr', 'id' => (string) $orderId]);
        } catch (HttpException $e) {
            $output = ob_get_clean();
            $this->assertSame(200, $e->getCode());
            $this->assertStringContainsString('%PDF', $output);
        } finally {
            @unlink($tmpFile);
            @unlink($absPath);
        }
    }
}
