<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\AuditLogAdminController;
use Core\Exception\HttpException;
use Core\Jwt;
use Model\AdminLogModel;

/**
 * Tests d'intégration pour AuditLogAdminController.
 *
 * Vérifie :
 * - Que updateStatus dans OrderAdminController insère bien une entrée admin_logs (TI critère 9)
 * - Que /admin/logs est accessible au super_admin uniquement
 * - Que /admin/logs/export génère un CSV
 */
class AuditLogAdminControllerTest extends AdminIntegrationTestCase
{
    private AdminLogModel $logModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logModel = new AdminLogModel();
    }

    // ----------------------------------------------------------------
    // TI critère 9 : updateStatus() insère dans admin_logs
    // ----------------------------------------------------------------

    public function testUpdateStatusInsertsAuditLog(): void
    {
        // Arrange : admin super_admin
        $superAdminId  = $this->insertAdminAccount('super_admin', 'super@test.local');
        $token         = Jwt::generate($superAdminId, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($superAdminId, $token);

        // Crée une commande
        $addressId  = (int) self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone)
             VALUES (?, 'billing', 'Test', 'Audit', 'M', '1 rue audit', 'Bordeaux', '33000', 'France', '0600000000')",
            [$superAdminId]
        );
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             SELECT ?, civility, firstname, lastname, street, city, zip_code, country, phone
             FROM addresses WHERE id = ?",
            [$addressId, $addressId]
        );
        $orderId = (int) self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, id_billing_snapshot, status)
             VALUES (?, ?, '[]', 50.00, 'transfer', ?, 'pending')",
            [$superAdminId, 'AUDIT-ORD-' . uniqid(), $snapshotId]
        );

        // Simule POST updateStatus
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_POST['status']        = 'processing';
        $_POST['csrf_token']    = self::CSRF_TOKEN;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = "/admin/commandes/{$orderId}/statut";

        $request    = new \Core\Request();
        $controller = new \Controller\Admin\OrderAdminController($request);

        try {
            ob_start();
            $controller->updateStatus(['id' => (string) $orderId]);
            ob_end_clean();
        } catch (\Throwable) {
            ob_end_clean();
            // redirect attendu — normal
        }

        // Vérifie qu'une entrée a été insérée dans admin_logs
        $row = self::$db->fetchOne(
            "SELECT * FROM admin_logs WHERE admin_id = ? AND action = 'order.status_updated' AND target_id = ?",
            [$superAdminId, $orderId]
        );

        $this->assertNotFalse($row, 'Une entrée admin_logs doit être insérée après updateStatus');
        $this->assertSame('order', $row['target_type']);
    }

    // ----------------------------------------------------------------
    // /admin/logs — super_admin uniquement
    // ----------------------------------------------------------------

    public function testIndexForbiddenForRegularAdmin(): void
    {
        // adminId de setUp est role 'admin'
        $this->expectException(HttpException::class);

        $request    = $this->makeRequest('GET', '/admin/logs');
        $controller = new AuditLogAdminController($request);

        ob_start();
        try {
            $controller->index([]);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        ob_end_clean();
    }

    public function testIndexAccessibleForSuperAdmin(): void
    {
        $superAdminId          = $this->insertAdminAccount('super_admin', 'super2@test.local');
        $token                 = Jwt::generate($superAdminId, 'super_admin');
        $_COOKIE['auth_token'] = $token;
        $this->insertConnection($superAdminId, $token);

        $request    = $this->makeRequest('GET', '/admin/logs');
        $controller = new AuditLogAdminController($request);

        ob_start();
        $controller->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Journal d\'audit', $output);
    }

    // ----------------------------------------------------------------
    // AdminLogModel::insert — TI basique
    // ----------------------------------------------------------------

    public function testAdminLogModelInsertAndFind(): void
    {
        $superAdminId = $this->insertAdminAccount('super_admin', 'super4@test.local');

        $id = $this->logModel->insert(
            $superAdminId,
            'wine.created',
            'wine',
            42,
            ['label_name' => 'Test Vin', 'vintage' => 2022],
            '192.168.1.1'
        );

        $this->assertGreaterThan(0, $id);

        $rows = $this->logModel->fetchFiltered(['admin_id' => $superAdminId]);
        $this->assertNotEmpty($rows);
        $this->assertSame('wine.created', $rows[0]['action']);
        $this->assertSame('wine', $rows[0]['target_type']);
        $this->assertSame(42, (int) $rows[0]['target_id']);
    }

    public function testAdminLogModelCount(): void
    {
        $superAdminId = $this->insertAdminAccount('super_admin', 'super5@test.local');

        $this->logModel->insert($superAdminId, 'order.status_updated', 'order', 1, [], '1.1.1.1');
        $this->logModel->insert($superAdminId, 'order.status_updated', 'order', 2, [], '1.1.1.1');

        $count = $this->logModel->count(['admin_id' => $superAdminId, 'action' => 'order.status_updated']);
        $this->assertSame(2, $count);
    }

    public function testAdminLogModelExportCsv(): void
    {
        $superAdminId = $this->insertAdminAccount('super_admin', 'super6@test.local');
        $this->logModel->insert($superAdminId, 'newsletter.sent', 'newsletter', 5, ['sent' => 10], '2.2.2.2');

        $csv = $this->logModel->exportCsv(['admin_id' => $superAdminId]);

        $this->assertStringContainsString('newsletter.sent', $csv);
        $this->assertStringContainsString('newsletter', $csv);
    }
}
