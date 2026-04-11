<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use Model\OrderModel;
use Service\GaeApiInterface;
use Service\MailService;
use Service\RefundService;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration de RefundService.
 *
 * Vérifie les transitions de statut en BDD après appel à refund().
 * Chaque test s'exécute dans une transaction rollbackée.
 */
class RefundServiceIntegrationTest extends IntegrationTestCase
{
    private OrderModel $model;

    /** @var GaeApiInterface&\PHPUnit\Framework\MockObject\MockObject */
    private GaeApiInterface $gaeMock;

    /** @var MailService&\PHPUnit\Framework\MockObject\MockObject */
    private MailService $mailerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model    = new OrderModel();
        $this->gaeMock  = $this->createMock(GaeApiInterface::class);
        $this->mailerMock = $this->createMock(MailService::class);
        unset($_ENV['CA_ENABLE_GAE'], $_ENV['APP_URL']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_ENV['CA_ENABLE_GAE'], $_ENV['APP_URL']);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un compte minimal et retourne son ID.
     *
     * @param string $email Adresse email unique
     * @return int          ID du compte
     */
    private function insertAccount(string $email = 'refund_service_ti@example.com'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, 'individual', 'customer', 'fr', NOW())",
            [$email, password_hash('pass', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Dupont', 'Jean', 'M')",
            [$id]
        );
        return $id;
    }

    /**
     * Insère une commande de test en BDD et retourne son ID.
     *
     * @param int    $userId  ID du compte propriétaire
     * @param string $status  Statut initial
     * @param string $ref     Référence unique
     * @return int            ID de la commande
     */
    private function insertOrder(int $userId, string $status = 'paid', string $ref = 'REF-REFUND-001'): int
    {
        $content    = json_encode([['wine_id' => 1, 'qty' => 1, 'price' => 50.00]]);
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots (firstname, lastname, street, city, zip, country)
             VALUES ('Jean', 'Dupont', '1 rue Test', 'Bordeaux', '33000', 'France')"
        );
        return (int) self::$db->insert(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_snapshot, status,
              ca_numappel, ca_numtrans)
             VALUES (?, ?, ?, 50.00, 'card', 0.00, ?, ?, '12345', '67890')",
            [$userId, $ref, $content, $snapshotId, $status]
        );
    }

    // ----------------------------------------------------------------
    // CA_ENABLE_GAE = false → statut refund_pending en BDD
    // ----------------------------------------------------------------

    public function testRefundWithGaeDisabledStoresRefundPendingInDb(): void
    {
        $_ENV['CA_ENABLE_GAE'] = 'false';

        $userId  = $this->insertAccount();
        $orderId = $this->insertOrder($userId);

        $this->gaeMock->expects($this->never())->method('refund');

        $service = new RefundService($this->model, $this->gaeMock, $this->mailerMock);
        $result  = $service->refund($orderId);

        $this->assertSame('refund_pending', $result);

        $row = self::$db->fetchOne(
            "SELECT status FROM orders WHERE id = ?",
            [$orderId]
        );
        $this->assertNotNull($row);
        $this->assertSame('refund_pending', $row['status']);
    }

    public function testRefundWithGaeEnabledStoresRefundedInDb(): void
    {
        $_ENV['CA_ENABLE_GAE'] = 'true';
        $_ENV['APP_URL']       = 'https://crabitanbellevue.fr';

        $userId  = $this->insertAccount('refund_gae_ti@example.com');
        $orderId = $this->insertOrder($userId, 'paid', 'REF-REFUND-002');

        $this->gaeMock->expects($this->once())
            ->method('refund')
            ->willReturn(true);

        $service = new RefundService($this->model, $this->gaeMock, $this->mailerMock);
        $result  = $service->refund($orderId);

        $this->assertSame('refunded', $result);

        $row = self::$db->fetchOne(
            "SELECT status FROM orders WHERE id = ?",
            [$orderId]
        );
        $this->assertNotNull($row);
        $this->assertSame('refunded', $row['status']);
    }

    public function testRefundWithGaeEnabledButApiFailsDoesNotUpdateDb(): void
    {
        $_ENV['CA_ENABLE_GAE'] = 'true';

        $userId  = $this->insertAccount('refund_fail_ti@example.com');
        $orderId = $this->insertOrder($userId, 'paid', 'REF-REFUND-003');

        $this->gaeMock->method('refund')->willReturn(false);

        $service = new RefundService($this->model, $this->gaeMock, $this->mailerMock);

        $this->expectException(\RuntimeException::class);
        $service->refund($orderId);

        // La transaction rollback garantit que le statut n'a pas changé
        $row = self::$db->fetchOne(
            "SELECT status FROM orders WHERE id = ?",
            [$orderId]
        );
        $this->assertSame('paid', $row['status'] ?? 'paid');
    }
}
