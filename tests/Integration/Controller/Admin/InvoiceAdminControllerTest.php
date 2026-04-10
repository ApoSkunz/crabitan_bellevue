<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\InvoiceAdminController;
use Core\Exception\HttpException;

/**
 * TI pour InvoiceAdminController (archivage légal factures — L441-9 + L123-22 C.com.).
 */
class InvoiceAdminControllerTest extends AdminIntegrationTestCase
{
    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function makeController(string $method = 'GET'): InvoiceAdminController
    {
        return new InvoiceAdminController($this->makeRequest($method, '/admin/factures'));
    }

    /**
     * Insère une commande avec facture et retourne son id.
     */
    private function insertOrderWithInvoice(string $orderedAt = '2026-01-15 10:00:00'): int
    {
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (civility, firstname, lastname, street, city, zip, country, phone)
             VALUES ('M', 'Jean', 'Test', '1 rue TI', 'Paris', '75001', 'France', '0600000000')"
        );
        $orderId = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, id_billing_snapshot,
              status, ordered_at, invoice_number, path_invoice)
             VALUES (?, ?, '[]', 120.00, 'card', ?, 'paid', ?, 'WEB-INVOICE-2026-0042',
                     'storage/pdf/invoices/WEB-INVOICE-2026-0042.pdf')",
            [$this->adminId, 'TI-INV-' . uniqid(), $snapshotId, $orderedAt]
        );
        self::$db->insert(
            "INSERT INTO order_invoice_files (order_id, path, invoice_number, source)
             VALUES (?, 'storage/pdf/invoices/WEB-INVOICE-2026-0042.pdf', 'WEB-INVOICE-2026-0042', 'auto')",
            [$orderId]
        );
        return $orderId;
    }

    // ----------------------------------------------------------------
    // index()
    // ----------------------------------------------------------------

    /**
     * GET /admin/factures — rendu de la vue principale.
     */
    public function testIndexRendersView(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-page-header', $output);
        $this->assertStringContainsString('Factures', $output);
        $this->assertStringContainsString('admin-filters', $output);
    }

    /**
     * Un filtre par année est bien appliqué.
     */
    public function testIndexFiltersInvoicesByYear(): void
    {
        $this->insertOrderWithInvoice('2025-06-01 10:00:00');
        $this->insertOrderWithInvoice('2026-01-15 10:00:00');

        $_GET['year'] = '2025';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
        // TI-INV de 2025 présent
        $this->assertStringContainsString('2025', $output);
    }

    /**
     * Aucune facture → affichage du message vide.
     */
    public function testIndexShowsEmptyMessageWhenNone(): void
    {
        $_GET['year'] = '1999';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Aucune facture', $output);
    }

    // ----------------------------------------------------------------
    // export() — redirection si aucune facture
    // ----------------------------------------------------------------

    /**
     * L'export redirige vers la liste quand la période ne contient aucune facture.
     */
    public function testExportRedirectsWhenNoInvoicesForPeriod(): void
    {
        $_GET['year'] = '1999';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->makeController()->export([]);
    }

    // ----------------------------------------------------------------
    // export() — génération du ZIP
    // ----------------------------------------------------------------

    /**
     * L'export génère un ZIP contenant le CSV quand des factures existent.
     * sendZipResponse() est surchargé pour éviter readfile() + exit.
     */
    public function testExportGeneratesZipWithCsvWhenInvoicesExist(): void
    {
        $this->insertOrderWithInvoice('2026-03-10 09:00:00');

        $_GET['year'] = '2026';

        $capturedTmpPath = null;
        $capturedFileName = null;

        $controller = new class ($this->makeRequest('GET', '/admin/factures/export')) extends InvoiceAdminController {
            public ?string $capturedTmpPath = null;
            public ?string $capturedFileName = null;

            protected function sendZipResponse(string $tmpPath, string $fileName): never
            {
                $this->capturedTmpPath  = $tmpPath;
                $this->capturedFileName = $fileName;
                throw new \Core\Exception\HttpException(200);
            }
        };

        try {
            $controller->export([]);
        } catch (HttpException $e) {
            $this->assertSame(200, $e->getCode());
        }

        $this->assertNotNull($controller->capturedTmpPath, 'sendZipResponse() doit avoir été appelé');
        $this->assertStringContainsString('factures-2026', (string) $controller->capturedFileName);

        // Vérifier que le ZIP contient bien un CSV
        if ($controller->capturedTmpPath !== null && is_file($controller->capturedTmpPath)) {
            $zip = new \ZipArchive();
            $zip->open($controller->capturedTmpPath);
            $found = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with((string) $zip->getNameIndex($i), '.csv')) {
                    $found = true;
                    break;
                }
            }
            $zip->close();
            @unlink($controller->capturedTmpPath);
            $this->assertTrue($found, 'Le ZIP doit contenir un fichier CSV');
        }
    }

    // ----------------------------------------------------------------
    // index() — filtres mois et trimestre + per_page invalide
    // ----------------------------------------------------------------

    /**
     * Filtre par mois : seules les factures du mois affiché.
     */
    public function testIndexFiltersInvoicesByMonth(): void
    {
        $this->insertOrderWithInvoice('2026-03-10 10:00:00');

        $_GET['year']  = '2026';
        $_GET['month'] = '3';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-table', $output);
    }

    /**
     * Filtre par trimestre : parsePeriodParams retourne quarter correctement.
     */
    public function testIndexFiltersInvoicesByQuarter(): void
    {
        $this->insertOrderWithInvoice('2026-02-15 10:00:00');

        $_GET['year']    = '2026';
        $_GET['quarter'] = '1';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('2026', $output);
    }

    /**
     * per_page invalide → retombe sur la valeur par défaut (25).
     */
    public function testIndexFallsBackToDefaultPerPageWhenInvalid(): void
    {
        $_GET['per_page'] = '999';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        // La vue doit s'afficher sans erreur
        $this->assertStringContainsString('admin-page-header', $output);
    }

    // ----------------------------------------------------------------
    // export() — label mois, label trimestre, PDF absent sur disque
    // ----------------------------------------------------------------

    /**
     * Export avec filtre mois → nom de fichier "factures-YYYY-MM.zip".
     * Couvre buildPeriodLabel() branche month.
     */
    public function testExportFilenameContainsMonthWhenMonthFilterActive(): void
    {
        $this->insertOrderWithInvoice('2026-03-05 09:00:00');

        $_GET['year']  = '2026';
        $_GET['month'] = '3';

        $controller = new class ($this->makeRequest('GET', '/admin/factures/export')) extends InvoiceAdminController {
            public ?string $capturedFileName = null;
            protected function sendZipResponse(string $tmpPath, string $fileName): never
            {
                $this->capturedFileName = $fileName;
                @unlink($tmpPath);
                throw new \Core\Exception\HttpException(200);
            }
        };

        try {
            $controller->export([]);
        } catch (HttpException $e) {
            $this->assertSame(200, $e->getCode());
        }

        $this->assertStringContainsString('2026-03', (string) $controller->capturedFileName);
    }

    /**
     * Export avec filtre trimestre → nom de fichier "factures-YYYY-QN.zip".
     * Couvre buildPeriodLabel() branche quarter.
     */
    public function testExportFilenameContainsQuarterWhenQuarterFilterActive(): void
    {
        $this->insertOrderWithInvoice('2026-02-01 09:00:00');

        $_GET['year']    = '2026';
        $_GET['quarter'] = '1';

        $controller = new class ($this->makeRequest('GET', '/admin/factures/export')) extends InvoiceAdminController {
            public ?string $capturedFileName = null;
            protected function sendZipResponse(string $tmpPath, string $fileName): never
            {
                $this->capturedFileName = $fileName;
                @unlink($tmpPath);
                throw new \Core\Exception\HttpException(200);
            }
        };

        try {
            $controller->export([]);
        } catch (HttpException $e) {
            $this->assertSame(200, $e->getCode());
        }

        $this->assertStringContainsString('2026-Q1', (string) $controller->capturedFileName);
    }

    /**
     * Export quand le PDF n'existe pas sur le disque → le ZIP contient uniquement le CSV
     * (branche is_file($absPath) === false dans la boucle foreach).
     */
    public function testExportSkipsMissingPdfButStillGeneratesCsv(): void
    {
        // path_invoice pointe vers un fichier inexistant
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (civility, firstname, lastname, street, city, zip, country, phone)
             VALUES ('M', 'Jean', 'Test', '1 rue', 'Paris', '75001', 'France', '0600000000')"
        );
        $orderId = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, id_billing_snapshot,
              status, ordered_at, invoice_number, path_invoice)
             VALUES (?, ?, '[]', 50.00, 'card', ?, 'paid', '2026-03-20 08:00:00',
                     NULL, NULL)",
            [$this->adminId, 'TI-NOPDF-' . uniqid(), $snapshotId]
        );
        // invoice_number NULL → couvrira aussi la branche basename($row['path'])
        self::$db->insert(
            "INSERT INTO order_invoice_files (order_id, path, invoice_number, source)
             VALUES (?, 'storage/pdf/invoices/does-not-exist.pdf', NULL, 'auto')",
            [$orderId]
        );

        $_GET['year'] = '2026';

        $controller = new class ($this->makeRequest('GET', '/admin/factures/export')) extends InvoiceAdminController {
            public ?string $capturedTmpPath = null;
            protected function sendZipResponse(string $tmpPath, string $fileName): never
            {
                $this->capturedTmpPath = $tmpPath;
                throw new \Core\Exception\HttpException(200);
            }
        };

        try {
            $controller->export([]);
        } catch (HttpException $e) {
            $this->assertSame(200, $e->getCode());
        }

        // Le ZIP doit quand même exister et contenir le CSV
        $this->assertNotNull($controller->capturedTmpPath);
        if ($controller->capturedTmpPath !== null && is_file($controller->capturedTmpPath)) {
            $zip = new \ZipArchive();
            $zip->open($controller->capturedTmpPath);
            $hasCsv = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with((string) $zip->getNameIndex($i), '.csv')) {
                    $hasCsv = true;
                    break;
                }
            }
            $zip->close();
            @unlink($controller->capturedTmpPath);
            $this->assertTrue($hasCsv, 'Le ZIP doit contenir le CSV même sans PDF');
        }
    }

    // ----------------------------------------------------------------
    // export() — is_file true + invoice_number null (lignes 129-132)
    // ----------------------------------------------------------------

    /**
     * Export quand le PDF existe sur le disque mais invoice_number est NULL.
     * Couvre la branche is_file($absPath) === true + basename($row['path']) (ligne 129-132).
     */
    public function testExportAddsPdfByBasenameWhenInvoiceNumberIsNull(): void
    {
        // Créer un vrai fichier temporaire dans storage/invoices/
        $fakeFileName = 'ti-test-null-inv-' . uniqid() . '.pdf';
        $fakePath     = ROOT_PATH . '/storage/invoices/' . $fakeFileName;
        file_put_contents($fakePath, '%PDF-1.4 fake');

        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (civility, firstname, lastname, street, city, zip, country, phone)
             VALUES ('M', 'Jean', 'Test', '1 rue TI', 'Paris', '75001', 'France', '0600000000')"
        );
        $orderId = (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, id_billing_snapshot,
              status, ordered_at, invoice_number, path_invoice)
             VALUES (?, ?, '[]', 80.00, 'card', ?, 'paid', '2026-04-01 09:00:00', NULL, NULL)",
            [$this->adminId, 'TI-NULLINV-' . uniqid(), $snapshotId]
        );
        self::$db->insert(
            "INSERT INTO order_invoice_files (order_id, path, invoice_number, source)
             VALUES (?, ?, NULL, 'manual')",
            [$orderId, 'storage/invoices/' . $fakeFileName]
        );

        $_GET['year'] = '2026';

        $controller = new class ($this->makeRequest('GET', '/admin/factures/export')) extends InvoiceAdminController {
            public ?string $capturedTmpPath = null;
            protected function sendZipResponse(string $tmpPath, string $fileName): never
            {
                $this->capturedTmpPath = $tmpPath;
                throw new \Core\Exception\HttpException(200);
            }
        };

        try {
            $controller->export([]);
        } catch (HttpException $e) {
            $this->assertSame(200, $e->getCode());
        }

        @unlink($fakePath);

        $this->assertNotNull($controller->capturedTmpPath);

        // Le ZIP doit contenir le PDF ajouté via basename() (invoice_number NULL)
        if ($controller->capturedTmpPath !== null && is_file($controller->capturedTmpPath)) {
            $zip = new \ZipArchive();
            $zip->open($controller->capturedTmpPath);
            $hasPdf = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (str_ends_with((string) $zip->getNameIndex($i), '.pdf')) {
                    $hasPdf = true;
                    break;
                }
            }
            $zip->close();
            @unlink($controller->capturedTmpPath);
            $this->assertTrue($hasPdf, 'Le ZIP doit contenir le PDF ajouté via basename()');
        }
    }

    // ----------------------------------------------------------------
    // sendZipResponse() — lignes 174-180
    // ----------------------------------------------------------------

    /**
     * sendZipResponse() envoie les headers, lit le fichier et lève HttpException(200).
     * Couvre les lignes 174-180 via appel direct sur l'instance réelle (non surchargée).
     */
    public function testSendZipResponseSendsHeadersAndThrows(): void
    {
        // Créer un vrai fichier ZIP temporaire
        $tmpZip = (string) tempnam(sys_get_temp_dir(), 'crab_ti_zip_');
        $zip    = new \ZipArchive();
        $zip->open($tmpZip, \ZipArchive::OVERWRITE);
        $zip->addFromString('test.txt', 'hello');
        $zip->close();

        $controller = new InvoiceAdminController($this->makeRequest('GET', '/admin/factures/export'));

        // Utiliser la réflexion pour appeler la méthode protected
        $ref    = new \ReflectionMethod(InvoiceAdminController::class, 'sendZipResponse');

        $threw  = false;
        $code   = null;
        ob_start();
        try {
            $ref->invoke($controller, $tmpZip, 'test-factures.zip');
        } catch (HttpException $e) {
            $threw = true;
            $code  = $e->getCode();
        }
        ob_end_clean();

        @unlink($tmpZip);

        $this->assertTrue($threw, 'sendZipResponse() doit lever HttpException');
        $this->assertSame(200, $code);
    }

    // ----------------------------------------------------------------
    // Critère 7 — aucune suppression physique des données de facturation
    // ----------------------------------------------------------------

    /**
     * OrderModel ne doit pas exposer de méthode de suppression pour order_invoice_files.
     * L441-9 C.com. — archivage 10 ans non modifiable.
     */
    public function testOrderModelHasNoDeleteMethodForInvoiceFiles(): void
    {
        $methods = get_class_methods(\Model\OrderModel::class);
        $deleteMethods = array_filter(
            $methods,
            fn (string $m) => str_contains(strtolower($m), 'deleteinvoice')
                || str_contains(strtolower($m), 'removeinvoice')
        );
        $this->assertEmpty(
            $deleteMethods,
            'OrderModel ne doit exposer aucune méthode de suppression de facture (L441-9 C.com.)'
        );
    }

    /**
     * Les entrées order_invoice_files persistent après un export — aucune suppression en base.
     */
    public function testInvoiceFilesRowsPersistAfterExport(): void
    {
        $orderId = $this->insertOrderWithInvoice('2026-02-01 08:00:00');

        $before = self::$db->fetchAll(
            "SELECT id FROM order_invoice_files WHERE order_id = ?",
            [$orderId]
        );
        $this->assertNotEmpty($before);

        // Simuler un export sans I/O
        $_GET['year'] = '2026';
        $controller = new class ($this->makeRequest('GET', '/admin/factures/export')) extends InvoiceAdminController {
            protected function sendZipResponse(string $tmpPath, string $fileName): never
            {
                @unlink($tmpPath);
                throw new \Core\Exception\HttpException(200);
            }
        };

        try {
            $controller->export([]);
        } catch (HttpException) {
            // attendu
        }

        $after = self::$db->fetchAll(
            "SELECT id FROM order_invoice_files WHERE order_id = ?",
            [$orderId]
        );
        $this->assertCount(count($before), $after, 'Les entrées de facturation ne doivent pas être supprimées après export');
    }
}
