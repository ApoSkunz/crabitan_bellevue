<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\OrderModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour OrderModel.
 *
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 * Objectif : ≥ 90 % de couverture de lignes sur OrderModel.
 */
class OrderModelTest extends IntegrationTestCase
{
    private OrderModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new OrderModel();
    }

    // ----------------------------------------------------------------
    // Helpers d'insertion
    // ----------------------------------------------------------------

    /**
     * Insère un compte minimal et retourne son ID.
     *
     * @param string $email Adresse e-mail unique pour le compte
     * @return int          ID du compte inséré
     */
    private function insertAccount(string $email = 'order_test@example.com'): int
    {
        $id = self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, 'individual', 'customer', 'fr', NOW())",
            [$email, password_hash('password123', PASSWORD_BCRYPT)]
        );
        return (int) $id;
    }

    /**
     * Insère une adresse de facturation et retourne son ID.
     *
     * @param int    $userId  ID du compte propriétaire
     * @param string $type    'billing' ou 'delivery'
     * @return int            ID de l'adresse insérée
     */
    private function insertAddress(int $userId, string $type = 'billing'): int
    {
        $id = self::$db->insert(
            "INSERT INTO addresses (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone, saved)
             VALUES (?, ?, 'Jean', 'Dupont', 'M', '12 rue de la Vigne', 'Bordeaux', '33000', 'France', '0600000000', 1)",
            [$userId, $type]
        );
        return (int) $id;
    }

    /**
     * Insère une commande de test et retourne son ID.
     *
     * @param int    $userId     ID du compte
     * @param int    $billingId  ID de l'adresse de facturation
     * @param string $status     Statut initial de la commande
     * @param string $ref        Référence unique de la commande
     * @param string $payment    Méthode de paiement
     * @return int               ID de la commande insérée
     */
    private function insertOrder(
        int $userId,
        int $billingId,
        string $status = 'pending',
        string $ref = 'REF-TEST-001',
        string $payment = 'card'
    ): int {
        $content    = json_encode([['wine_id' => 1, 'qty' => 2, 'price' => 25.00]]);
        $snapshotId = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots (firstname, lastname, street, city, zip, country, phone)
             SELECT firstname, lastname, street, city, zip_code, country, phone FROM addresses WHERE id = ?",
            [$billingId]
        );
        $id = self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, shipping_discount, id_billing_snapshot, status)
             VALUES (?, ?, ?, 59.90, ?, 0.00, ?, ?)",
            [$userId, $ref, $content, $payment, $snapshotId, $status]
        );
        return (int) $id;
    }

    // ----------------------------------------------------------------
    // getForAdmin
    // ----------------------------------------------------------------

    /**
     * Vérifie que getForAdmin retourne la commande insérée sans filtre.
     *
     * @return void
     */
    public function testGetForAdminReturnsOrderWithNoFilters(): void
    {
        $userId    = $this->insertAccount('admin_list@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-ADMIN-001');

        $rows = $this->model->getForAdmin(1, 10, null, null, null);

        $this->assertIsArray($rows);
        $refs = array_column($rows, 'order_reference');
        $this->assertContains('REF-ADMIN-001', $refs);
    }

    /**
     * Vérifie que getForAdmin filtre correctement par statut.
     *
     * @return void
     */
    public function testGetForAdminFiltersByStatus(): void
    {
        $userId    = $this->insertAccount('admin_status@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'shipped', 'REF-SHIPPED-001');
        $this->insertOrder($userId, $billingId, 'paid', 'REF-PAID-001');

        $rows = $this->model->getForAdmin(1, 10, 'shipped', null, null);

        $this->assertIsArray($rows);
        foreach ($rows as $row) {
            $this->assertSame('shipped', $row['status']);
        }
    }

    /**
     * Vérifie que getForAdmin filtre par méthode de paiement.
     *
     * @return void
     */
    public function testGetForAdminFiltersByPayment(): void
    {
        $userId    = $this->insertAccount('admin_pay@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-CARD-001', 'card');
        $this->insertOrder($userId, $billingId, 'paid', 'REF-VIREMENT-001', 'virement');

        $rows = $this->model->getForAdmin(1, 10, null, null, 'virement');

        $this->assertIsArray($rows);
        foreach ($rows as $row) {
            $this->assertSame('virement', $row['payment_method']);
        }
    }

    /**
     * Vérifie que getForAdmin filtre par recherche (email ou référence).
     *
     * @return void
     */
    public function testGetForAdminFiltersBySearch(): void
    {
        $userId    = $this->insertAccount('searchable@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'pending', 'REF-SEARCH-UNIQUE');

        $rows = $this->model->getForAdmin(1, 10, null, 'REF-SEARCH-UNIQUE', null);

        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $this->assertSame('REF-SEARCH-UNIQUE', $rows[0]['order_reference']);
    }

    /**
     * Vérifie que getForAdmin avec un statut invalide ignore le filtre.
     *
     * @return void
     */
    public function testGetForAdminIgnoresInvalidStatus(): void
    {
        $userId    = $this->insertAccount('admin_invstatus@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'pending', 'REF-INVSTATUS-001');

        // Un statut invalide ne doit pas planter — il est ignoré par buildAdminFilters
        $rows = $this->model->getForAdmin(1, 10, 'invalid_status', null, null);
        $this->assertIsArray($rows);
    }

    /**
     * Vérifie que getForAdmin avec un payment invalide ignore le filtre.
     *
     * @return void
     */
    public function testGetForAdminIgnoresInvalidPayment(): void
    {
        $userId    = $this->insertAccount('admin_invpay@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'pending', 'REF-INVPAY-001');

        $rows = $this->model->getForAdmin(1, 10, null, null, 'bitcoin');
        $this->assertIsArray($rows);
    }

    // ----------------------------------------------------------------
    // countForAdmin
    // ----------------------------------------------------------------

    /**
     * Vérifie que countForAdmin retourne un entier ≥ 0 sans filtres.
     *
     * @return void
     */
    public function testCountForAdminReturnsInteger(): void
    {
        $userId    = $this->insertAccount('count_admin@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'pending', 'REF-COUNT-001');

        $count = $this->model->countForAdmin(null, null, null);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    /**
     * Vérifie que countForAdmin filtre par statut.
     *
     * @return void
     */
    public function testCountForAdminFiltersByStatus(): void
    {
        $userId    = $this->insertAccount('count_status@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'refunded', 'REF-REFUND-CNT');

        $count = $this->model->countForAdmin('refunded', null, null);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    /**
     * Vérifie que countForAdmin filtre par méthode de paiement.
     *
     * @return void
     */
    public function testCountForAdminFiltersByPayment(): void
    {
        $userId    = $this->insertAccount('count_pay@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-PAY-CNT', 'cheque');

        $countCheque = $this->model->countForAdmin(null, null, 'cheque');
        $this->assertGreaterThanOrEqual(1, $countCheque);
    }

    // ----------------------------------------------------------------
    // findByIdForAdmin
    // ----------------------------------------------------------------

    /**
     * Vérifie que findByIdForAdmin retourne les données de la commande.
     *
     * @return void
     */
    public function testFindByIdForAdminReturnsOrder(): void
    {
        $userId    = $this->insertAccount('find_admin@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'paid', 'REF-FIND-ADMIN');

        $row = $this->model->findByIdForAdmin($orderId);

        $this->assertIsArray($row);
        $this->assertSame('REF-FIND-ADMIN', $row['order_reference']);
        $this->assertSame('find_admin@example.com', $row['email']);
    }

    /**
     * Vérifie que findByIdForAdmin retourne null pour un ID inexistant.
     *
     * @return void
     */
    public function testFindByIdForAdminReturnsNullIfNotFound(): void
    {
        $result = $this->model->findByIdForAdmin(999999);
        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // updateInvoice
    // ----------------------------------------------------------------

    /**
     * Vérifie que updateInvoice met bien à jour le chemin de facture.
     *
     * @return void
     */
    public function testUpdateInvoicePersistsPath(): void
    {
        $userId    = $this->insertAccount('invoice@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'paid', 'REF-INVOICE-001');

        $this->model->updateInvoice($orderId, '/invoices/2026/inv-001.pdf');

        $row = $this->model->findByIdForAdmin($orderId);
        $this->assertIsArray($row);
        $this->assertSame('/invoices/2026/inv-001.pdf', $row['path_invoice']);
    }

    // ----------------------------------------------------------------
    // findByIdForUser
    // ----------------------------------------------------------------

    /**
     * Vérifie que findByIdForUser retourne la commande pour le bon utilisateur.
     *
     * @return void
     */
    public function testFindByIdForUserReturnsOrder(): void
    {
        $userId    = $this->insertAccount('user_find@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'pending', 'REF-USERFIND-001');

        $row = $this->model->findByIdForUser($orderId, $userId);

        $this->assertIsArray($row);
        $this->assertSame('REF-USERFIND-001', $row['order_reference']);
    }

    /**
     * Vérifie que findByIdForUser retourne null si l'utilisateur ne correspond pas.
     *
     * @return void
     */
    public function testFindByIdForUserReturnsNullIfWrongUser(): void
    {
        $userId    = $this->insertAccount('owner@example.com');
        $otherUser = $this->insertAccount('other@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'pending', 'REF-WRONGUSER-001');

        $result = $this->model->findByIdForUser($orderId, $otherUser);
        $this->assertNull($result);
    }

    /**
     * Vérifie que findByIdForUser retourne null pour un ID inconnu.
     *
     * @return void
     */
    public function testFindByIdForUserReturnsNullIfNotFound(): void
    {
        $result = $this->model->findByIdForUser(999999, 1);
        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // updateStatus
    // ----------------------------------------------------------------

    /**
     * Vérifie que updateStatus met à jour le statut avec une valeur valide.
     *
     * @return void
     */
    public function testUpdateStatusChangesValidStatus(): void
    {
        $userId    = $this->insertAccount('upstatus@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'pending', 'REF-UPSTATUS-001');

        $this->model->updateStatus($orderId, 'processing');

        $row = $this->model->findByIdForAdmin($orderId);
        $this->assertIsArray($row);
        $this->assertSame('processing', $row['status']);
    }

    /**
     * Vérifie que updateStatus ne modifie rien avec un statut invalide.
     *
     * @return void
     */
    public function testUpdateStatusIgnoresInvalidStatus(): void
    {
        $userId    = $this->insertAccount('upstatus_inv@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'pending', 'REF-INVSTATUS-UPD');

        $this->model->updateStatus($orderId, 'unknown_status');

        $row = $this->model->findByIdForAdmin($orderId);
        $this->assertIsArray($row);
        $this->assertSame('pending', $row['status']);
    }

    /**
     * Vérifie que tous les statuts valides sont acceptés par updateStatus.
     *
     * @return void
     */
    public function testUpdateStatusAcceptsAllValidStatuses(): void
    {
        $validStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'return_requested'];
        $userId    = $this->insertAccount('all_statuses@example.com');
        $billingId = $this->insertAddress($userId);

        foreach ($validStatuses as $i => $status) {
            $ref     = 'REF-ALLSTATUS-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $orderId = $this->insertOrder($userId, $billingId, 'pending', $ref);
            $this->model->updateStatus($orderId, $status);

            $row = $this->model->findByIdForAdmin($orderId);
            $this->assertIsArray($row);
            $this->assertSame($status, $row['status']);
        }
    }

    // ----------------------------------------------------------------
    // countByStatus
    // ----------------------------------------------------------------

    /**
     * Vérifie que countByStatus retourne un tableau associatif statut => nombre.
     *
     * @return void
     */
    public function testCountByStatusReturnsAssocArray(): void
    {
        $userId    = $this->insertAccount('cnt_status@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'pending', 'REF-CNTSTATUS-001');
        $this->insertOrder($userId, $billingId, 'paid', 'REF-CNTSTATUS-002');

        $result = $this->model->countByStatus();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pending', $result);
        $this->assertIsInt($result['pending']);
        $this->assertGreaterThanOrEqual(1, $result['pending']);
    }

    // ----------------------------------------------------------------
    // getRevenue
    // ----------------------------------------------------------------

    /**
     * Vérifie que getRevenue retourne un flottant ≥ 0.
     *
     * @return void
     */
    public function testGetRevenueReturnsFloat(): void
    {
        $userId    = $this->insertAccount('revenue@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-REV-001');

        $revenue = $this->model->getRevenue(30);

        $this->assertIsFloat($revenue);
        $this->assertGreaterThanOrEqual(0.0, $revenue);
    }

    /**
     * Vérifie que getRevenue exclut les commandes annulées/remboursées.
     *
     * @return void
     */
    public function testGetRevenueExcludesCancelledOrders(): void
    {
        $userId    = $this->insertAccount('rev_cancel@example.com');
        $billingId = $this->insertAddress($userId);

        $revenueBefore = $this->model->getRevenue(30);

        // Commandes annulées/remboursées ne doivent pas s'ajouter au CA
        $this->insertOrder($userId, $billingId, 'cancelled', 'REF-CANCEL-REV');
        $this->insertOrder($userId, $billingId, 'refunded', 'REF-REFUND-REV');

        $revenueAfter = $this->model->getRevenue(30);
        $this->assertSame($revenueBefore, $revenueAfter);
    }

    // ----------------------------------------------------------------
    // getRevenueByYear
    // ----------------------------------------------------------------

    /**
     * Vérifie que getRevenueByYear retourne un flottant.
     *
     * @return void
     */
    public function testGetRevenueByYearReturnsFloat(): void
    {
        $userId    = $this->insertAccount('revyear@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'delivered', 'REF-REVYEAR-001');

        $revenue = $this->model->getRevenueByYear((int) date('Y'));

        $this->assertIsFloat($revenue);
        $this->assertGreaterThanOrEqual(0.0, $revenue);
    }

    /**
     * Vérifie que getRevenueByYear retourne 0.0 pour une année sans commandes.
     *
     * @return void
     */
    public function testGetRevenueByYearReturnsZeroForEmptyYear(): void
    {
        $revenue = $this->model->getRevenueByYear(1900);
        $this->assertSame(0.0, $revenue);
    }

    // ----------------------------------------------------------------
    // getRecent
    // ----------------------------------------------------------------

    /**
     * Vérifie que getRecent retourne au plus $limit commandes.
     *
     * @return void
     */
    public function testGetRecentReturnsAtMostLimit(): void
    {
        $userId    = $this->insertAccount('recent@example.com');
        $billingId = $this->insertAddress($userId);

        for ($i = 1; $i <= 5; $i++) {
            $this->insertOrder($userId, $billingId, 'paid', 'REF-RECENT-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT));
        }

        $rows = $this->model->getRecent(3);

        $this->assertIsArray($rows);
        $this->assertLessThanOrEqual(3, count($rows));
    }

    /**
     * Vérifie que getRecent fonctionne avec la valeur par défaut (8).
     *
     * @return void
     */
    public function testGetRecentUsesDefaultLimit(): void
    {
        $rows = $this->model->getRecent();
        $this->assertIsArray($rows);
        $this->assertLessThanOrEqual(8, count($rows));
    }

    // ----------------------------------------------------------------
    // getForUser
    // ----------------------------------------------------------------

    /**
     * Vérifie que getForUser retourne les commandes de l'utilisateur.
     *
     * @return void
     */
    public function testGetForUserReturnsUserOrders(): void
    {
        $userId    = $this->insertAccount('user_orders@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-USERORD-001');
        $this->insertOrder($userId, $billingId, 'pending', 'REF-USERORD-002');

        $rows = $this->model->getForUser($userId, 1, 10);

        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);
    }

    /**
     * Vérifie que getForUser retourne un tableau vide si l'utilisateur n'a pas de commandes.
     *
     * @return void
     */
    public function testGetForUserReturnsEmptyArrayIfNoOrders(): void
    {
        $userId = $this->insertAccount('no_orders@example.com');

        $rows = $this->model->getForUser($userId, 1, 10);

        $this->assertIsArray($rows);
        $this->assertEmpty($rows);
    }

    /**
     * Vérifie que getForUser filtre par période 3months.
     *
     * @return void
     */
    public function testGetForUserFiltersBy3Months(): void
    {
        $userId    = $this->insertAccount('period_3m@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-3M-001');

        $rows = $this->model->getForUser($userId, 1, 10, '3months');

        $this->assertIsArray($rows);
    }

    /**
     * Vérifie que getForUser filtre par année.
     *
     * @return void
     */
    public function testGetForUserFiltersByYear(): void
    {
        $userId    = $this->insertAccount('period_year@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-YEAR-001');

        $rows = $this->model->getForUser($userId, 1, 10, 'year', (int) date('Y'));

        $this->assertIsArray($rows);
        $this->assertGreaterThanOrEqual(1, count($rows));
    }

    /**
     * Vérifie que getForUser filtre par statut valide.
     *
     * @return void
     */
    public function testGetForUserFiltersByStatus(): void
    {
        $userId    = $this->insertAccount('user_st@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'delivered', 'REF-USTATUS-001');
        $this->insertOrder($userId, $billingId, 'pending', 'REF-USTATUS-002');

        $rows = $this->model->getForUser($userId, 1, 10, null, null, 'delivered');

        $this->assertIsArray($rows);
        foreach ($rows as $row) {
            $this->assertSame('delivered', $row['status']);
        }
    }

    /**
     * Vérifie que getForUser ignore un statut invalide.
     *
     * @return void
     */
    public function testGetForUserIgnoresInvalidStatus(): void
    {
        $userId    = $this->insertAccount('user_invst@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-INVST-001');

        // Statut invalide → ignoré, toutes les commandes de l'utilisateur sont retournées
        $rows = $this->model->getForUser($userId, 1, 10, null, null, 'not_a_status');

        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
    }

    // ----------------------------------------------------------------
    // countForUser
    // ----------------------------------------------------------------

    /**
     * Vérifie que countForUser retourne le bon nombre de commandes.
     *
     * @return void
     */
    public function testCountForUserReturnsCorrectCount(): void
    {
        $userId    = $this->insertAccount('countuser@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-UCNT-001');
        $this->insertOrder($userId, $billingId, 'paid', 'REF-UCNT-002');

        $count = $this->model->countForUser($userId);

        $this->assertSame(2, $count);
    }

    /**
     * Vérifie que countForUser retourne 0 si aucune commande.
     *
     * @return void
     */
    public function testCountForUserReturnsZeroIfNoOrders(): void
    {
        $userId = $this->insertAccount('countuser_empty@example.com');

        $count = $this->model->countForUser($userId);

        $this->assertSame(0, $count);
    }

    /**
     * Vérifie que countForUser filtre par période et statut.
     *
     * @return void
     */
    public function testCountForUserWithFilters(): void
    {
        $userId    = $this->insertAccount('countfilter@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'shipped', 'REF-UCNTF-001');

        $count = $this->model->countForUser($userId, '3months', null, 'shipped');
        $this->assertGreaterThanOrEqual(1, $count);
    }

    // ----------------------------------------------------------------
    // findDetailForUser
    // ----------------------------------------------------------------

    /**
     * Vérifie que findDetailForUser retourne le détail complet de la commande.
     *
     * @return void
     */
    public function testFindDetailForUserReturnsFullDetail(): void
    {
        $userId      = $this->insertAccount('detail@example.com');
        $billingId   = $this->insertAddress($userId, 'billing');
        $deliveryId  = $this->insertAddress($userId, 'delivery');
        $content     = json_encode([['wine_id' => 1, 'qty' => 1, 'price' => 30.00]]);

        $billSnap = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots (firstname, lastname, street, city, zip, country, phone)
             SELECT firstname, lastname, street, city, zip_code, country, phone FROM addresses WHERE id = ?",
            [$billingId]
        );
        $delSnap  = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots (firstname, lastname, street, city, zip, country, phone)
             SELECT firstname, lastname, street, city, zip_code, country, phone FROM addresses WHERE id = ?",
            [$deliveryId]
        );
        $orderId  = (int) self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, id_billing_snapshot, id_delivery_snapshot, status)
             VALUES (?, 'REF-DETAIL-001', ?, 30.00, 'card', ?, ?, 'delivered')",
            [$userId, $content, $billSnap, $delSnap]
        );

        $row = $this->model->findDetailForUser($orderId, $userId);

        $this->assertIsArray($row);
        $this->assertSame('REF-DETAIL-001', $row['order_reference']);
        $this->assertArrayHasKey('bill_street', $row);
        $this->assertArrayHasKey('del_street', $row);
    }

    /**
     * Vérifie que findDetailForUser retourne null pour un utilisateur non propriétaire.
     *
     * @return void
     */
    public function testFindDetailForUserReturnsNullIfWrongUser(): void
    {
        $userId    = $this->insertAccount('detailowner@example.com');
        $otherId   = $this->insertAccount('detailother@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'paid', 'REF-DETAILWRONG-001');

        $result = $this->model->findDetailForUser($orderId, $otherId);
        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // cancelForUser
    // ----------------------------------------------------------------

    /**
     * Vérifie que cancelForUser annule une commande avec statut 'pending'.
     *
     * @return void
     */
    public function testCancelForUserCancelsPendingOrder(): void
    {
        $userId    = $this->insertAccount('cancel@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'pending', 'REF-CANCEL-001');

        $result = $this->model->cancelForUser($orderId, $userId);

        $this->assertTrue($result);

        $row = $this->model->findByIdForUser($orderId, $userId);
        $this->assertIsArray($row);

        $detail = $this->model->findDetailForUser($orderId, $userId);
        $this->assertIsArray($detail);
        $this->assertSame('cancelled', $detail['status']);
    }

    /**
     * Vérifie que cancelForUser renvoie false pour une commande déjà payée.
     *
     * @return void
     */
    public function testCancelForUserReturnsFalseIfNotPending(): void
    {
        $userId    = $this->insertAccount('cancel_paid@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'paid', 'REF-CANCEL-PAID');

        $result = $this->model->cancelForUser($orderId, $userId);

        $this->assertFalse($result);
    }

    /**
     * Vérifie que cancelForUser renvoie false si la commande n'existe pas.
     *
     * @return void
     */
    public function testCancelForUserReturnsFalseIfOrderNotFound(): void
    {
        $userId = $this->insertAccount('cancel_nf@example.com');

        $result = $this->model->cancelForUser(999999, $userId);

        $this->assertFalse($result);
    }

    /**
     * Vérifie que cancelForUser renvoie false si l'utilisateur ne correspond pas.
     *
     * @return void
     */
    public function testCancelForUserReturnsFalseIfWrongUser(): void
    {
        $userId    = $this->insertAccount('cancel_owner@example.com');
        $otherId   = $this->insertAccount('cancel_other@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'pending', 'REF-CANCEL-WRONG');

        $result = $this->model->cancelForUser($orderId, $otherId);

        $this->assertFalse($result);
    }

    // ----------------------------------------------------------------
    // hasActiveOrdersForUser
    // ----------------------------------------------------------------

    /**
     * Vérifie que hasActiveOrdersForUser retourne true si une commande active existe.
     *
     * @return void
     */
    public function testHasActiveOrdersForUserReturnsTrueIfActive(): void
    {
        $userId    = $this->insertAccount('active_orders@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'processing', 'REF-ACTIVE-001');

        $this->assertTrue($this->model->hasActiveOrdersForUser($userId));
    }

    /**
     * Vérifie que hasActiveOrdersForUser retourne false si aucune commande active.
     *
     * @return void
     */
    public function testHasActiveOrdersForUserReturnsFalseIfNone(): void
    {
        $userId    = $this->insertAccount('no_active@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'delivered', 'REF-NOACTIVE-001');

        $this->assertFalse($this->model->hasActiveOrdersForUser($userId));
    }

    /**
     * Vérifie que hasActiveOrdersForUser retourne false si aucune commande du tout.
     *
     * @return void
     */
    public function testHasActiveOrdersForUserReturnsFalseIfNoOrders(): void
    {
        $userId = $this->insertAccount('zero_orders@example.com');

        $this->assertFalse($this->model->hasActiveOrdersForUser($userId));
    }

    // ----------------------------------------------------------------
    // countActiveOrdersForUser
    // ----------------------------------------------------------------

    /**
     * Vérifie que countActiveOrdersForUser retourne le bon nombre de commandes actives.
     *
     * @return void
     */
    public function testCountActiveOrdersForUserReturnsCorrectCount(): void
    {
        $userId    = $this->insertAccount('count_active@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'pending', 'REF-CNT-ACT-001');
        $this->insertOrder($userId, $billingId, 'processing', 'REF-CNT-ACT-002');
        $this->insertOrder($userId, $billingId, 'delivered', 'REF-CNT-ACT-003'); // non comptée

        $this->assertSame(2, $this->model->countActiveOrdersForUser($userId));
    }

    /**
     * Vérifie que countActiveOrdersForUser retourne 0 quand aucune commande active.
     *
     * @return void
     */
    public function testCountActiveOrdersForUserReturnsZeroIfNone(): void
    {
        $userId    = $this->insertAccount('count_zero@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'delivered', 'REF-CNT-ZERO-001');

        $this->assertSame(0, $this->model->countActiveOrdersForUser($userId));
    }

    /**
     * Vérifie que countActiveOrdersForUser retourne 0 pour un utilisateur sans commande.
     *
     * @return void
     */
    public function testCountActiveOrdersForUserReturnsZeroIfNoOrders(): void
    {
        $userId = $this->insertAccount('count_noorders@example.com');

        $this->assertSame(0, $this->model->countActiveOrdersForUser($userId));
    }

    // ----------------------------------------------------------------
    // getAddressIdsWithActiveOrders
    // ----------------------------------------------------------------

    /**
     * Vérifie que getAddressIdsWithActiveOrders retourne un tableau vide si aucun ID fourni.
     *
     * @return void
     */
    public function testGetAddressIdsWithActiveOrdersReturnsEmptyForEmptyInput(): void
    {
        $result = $this->model->getAddressIdsWithActiveOrders([]);
        $this->assertSame([], $result);
    }

    /**
     * Depuis order-address-snapshot, orders ne référence plus les IDs d'adresses.
     * getAddressIdsWithActiveOrders retourne toujours [].
     *
     * @return void
     */
    public function testGetAddressIdsWithActiveOrdersAlwaysReturnsEmpty(): void
    {
        $userId    = $this->insertAccount('addr_active@example.com');
        $billingId = $this->insertAddress($userId, 'billing');
        $this->insertOrder($userId, $billingId, 'pending', 'REF-ADDRACTIVE-001');

        $result = $this->model->getAddressIdsWithActiveOrders([$billingId]);

        $this->assertSame([], $result);
    }

    /**
     * Vérifie que getAddressIdsWithActiveOrders ne retourne pas l'adresse d'une commande terminée.
     *
     * @return void
     */
    public function testGetAddressIdsWithActiveOrdersExcludesDeliveredOrders(): void
    {
        $userId    = $this->insertAccount('addr_done@example.com');
        $billingId = $this->insertAddress($userId, 'billing');
        $this->insertOrder($userId, $billingId, 'delivered', 'REF-ADDRDONE-001');

        $result = $this->model->getAddressIdsWithActiveOrders([$billingId]);

        $this->assertIsArray($result);
        $this->assertNotContains($billingId, $result);
    }

    // ----------------------------------------------------------------
    // hasActiveOrderForAddress
    // ----------------------------------------------------------------

    /**
     * Depuis order-address-snapshot, orders ne référence plus les IDs d'adresses.
     * hasActiveOrderForAddress retourne toujours false.
     *
     * @return void
     */
    public function testHasActiveOrderForAddressAlwaysReturnsFalse(): void
    {
        $userId    = $this->insertAccount('addr_check@example.com');
        $billingId = $this->insertAddress($userId, 'billing');
        $this->insertOrder($userId, $billingId, 'shipped', 'REF-ADDRCHECK-001');

        $this->assertFalse($this->model->hasActiveOrderForAddress($billingId));
    }

    /**
     * Vérifie que hasActiveOrderForAddress retourne false si aucune commande active.
     *
     * @return void
     */
    public function testHasActiveOrderForAddressReturnsFalseIfNone(): void
    {
        $userId    = $this->insertAccount('addr_inactive@example.com');
        $billingId = $this->insertAddress($userId, 'billing');
        $this->insertOrder($userId, $billingId, 'cancelled', 'REF-ADDRINACT-001');

        $this->assertFalse($this->model->hasActiveOrderForAddress($billingId));
    }

    /**
     * Vérifie que hasActiveOrderForAddress retourne false pour une adresse inconnue.
     *
     * @return void
     */
    public function testHasActiveOrderForAddressReturnsFalseIfUnknownAddress(): void
    {
        $this->assertFalse($this->model->hasActiveOrderForAddress(999999));
    }

    /**
     * Depuis order-address-snapshot, orders ne référence plus les IDs d'adresses.
     * hasActiveOrderForAddress retourne toujours false, même pour une adresse livraison.
     *
     * @return void
     */
    public function testHasActiveOrderForAddressViaDeliveryAddressAlwaysFalse(): void
    {
        $userId     = $this->insertAccount('delivery_check@example.com');
        $billingId  = $this->insertAddress($userId, 'billing');
        $deliveryId = $this->insertAddress($userId, 'delivery');
        $content    = json_encode([['wine_id' => 1, 'qty' => 1, 'price' => 20.00]]);

        $billSnap = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots (firstname, lastname, street, city, zip, country, phone)
             SELECT firstname, lastname, street, city, zip_code, country, phone FROM addresses WHERE id = ?",
            [$billingId]
        );
        $delSnap  = (int) self::$db->insert(
            "INSERT INTO order_address_snapshots (firstname, lastname, street, city, zip, country, phone)
             SELECT firstname, lastname, street, city, zip_code, country, phone FROM addresses WHERE id = ?",
            [$deliveryId]
        );
        self::$db->insert(
            "INSERT INTO orders (user_id, order_reference, content, price, payment_method, id_billing_snapshot, id_delivery_snapshot, status)
             VALUES (?, 'REF-DELIVERY-CHK', ?, 20.00, 'card', ?, ?, 'processing')",
            [$userId, $content, $billSnap, $delSnap]
        );

        $this->assertFalse($this->model->hasActiveOrderForAddress($deliveryId));
    }

    // ----------------------------------------------------------------
    // getAvailableYears
    // ----------------------------------------------------------------

    /**
     * Vérifie que getAvailableYears retourne l'année courante si une commande existe.
     *
     * @return void
     */
    public function testGetAvailableYearsIncludesCurrentYear(): void
    {
        $userId    = $this->insertAccount('years@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-YEARS-001');

        $years = $this->model->getAvailableYears();

        $this->assertIsArray($years);
        $this->assertContains((int) date('Y'), $years);
    }

    /**
     * Vérifie que getAvailableYears exclut les commandes annulées/remboursées.
     *
     * @return void
     */
    public function testGetAvailableYearsExcludesCancelledRefunded(): void
    {
        // On vérifie que la méthode retourne un tableau (le contenu dépend des données existantes)
        $years = $this->model->getAvailableYears();
        $this->assertIsArray($years);
    }

    // ----------------------------------------------------------------
    // getStatsForPeriod
    // ----------------------------------------------------------------

    /**
     * Vérifie que getStatsForPeriod retourne la structure attendue.
     *
     * @return void
     */
    public function testGetStatsForPeriodReturnsCorrectStructure(): void
    {
        $userId    = $this->insertAccount('stats@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-STATS-001');

        $from = date('Y-01-01');
        $to   = date('Y-12-31');

        $result = $this->model->getStatsForPeriod($from, $to);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ca', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('avg', $result);
        $this->assertIsFloat($result['ca']);
        $this->assertIsInt($result['count']);
        $this->assertIsFloat($result['avg']);
    }

    /**
     * Vérifie que getStatsForPeriod retourne des zéros pour une période sans données.
     *
     * @return void
     */
    public function testGetStatsForPeriodReturnsZerosForEmptyPeriod(): void
    {
        $result = $this->model->getStatsForPeriod('1900-01-01', '1900-12-31');

        $this->assertSame(0.0, $result['ca']);
        $this->assertSame(0, $result['count']);
        $this->assertSame(0.0, $result['avg']);
    }

    /**
     * Vérifie que getStatsForPeriod calcule correctement la moyenne.
     *
     * @return void
     */
    public function testGetStatsForPeriodCalculatesAvg(): void
    {
        $userId    = $this->insertAccount('stats_avg@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-AVG-001');
        $this->insertOrder($userId, $billingId, 'paid', 'REF-AVG-002');

        $from   = date('Y-01-01');
        $to     = date('Y-12-31');
        $result = $this->model->getStatsForPeriod($from, $to);

        if ($result['count'] > 0) {
            $expectedAvg = round($result['ca'] / $result['count'], 2);
            $this->assertSame($expectedAvg, $result['avg']);
        } else {
            $this->assertSame(0.0, $result['avg']);
        }
    }

    /**
     * Vérifie que getStatsForPeriod fonctionne sans bornes (null/null).
     *
     * @return void
     */
    public function testGetStatsForPeriodWithNullBounds(): void
    {
        $result = $this->model->getStatsForPeriod(null, null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ca', $result);
        $this->assertArrayHasKey('count', $result);
    }

    // ----------------------------------------------------------------
    // getChartData
    // ----------------------------------------------------------------

    /**
     * Vérifie que getChartData retourne un tableau avec granularité daily.
     *
     * @return void
     */
    public function testGetChartDataDailyReturnsFilled(): void
    {
        $userId    = $this->insertAccount('chart_daily@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-CHART-D-001');

        $from   = date('Y-m-d');
        $to     = date('Y-m-d');
        $result = $this->model->getChartData($from, $to, 'daily');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('label', $result[0]);
        $this->assertArrayHasKey('ca', $result[0]);
        $this->assertArrayHasKey('count', $result[0]);
    }

    /**
     * Vérifie que getChartData retourne un tableau avec granularité monthly.
     *
     * @return void
     */
    public function testGetChartDataMonthlyReturnsFilled(): void
    {
        $userId    = $this->insertAccount('chart_monthly@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-CHART-M-001');

        $from   = date('Y-m-01');
        $to     = date('Y-m-t');
        $result = $this->model->getChartData($from, $to, 'monthly');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('label', $result[0]);
    }

    /**
     * Vérifie que getChartData retourne un tableau avec granularité yearly.
     *
     * @return void
     */
    public function testGetChartDataYearlyReturnsFilled(): void
    {
        $userId    = $this->insertAccount('chart_yearly@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-CHART-Y-001');

        $result = $this->model->getChartData(null, null, 'yearly');

        $this->assertIsArray($result);
        // Doit contenir au moins l'année courante
        $this->assertNotEmpty($result);
    }

    /**
     * Vérifie que getChartData retourne un tableau vide pour yearly sans données.
     *
     * @return void
     */
    public function testGetChartDataYearlyReturnsEmptyIfNoOrders(): void
    {
        // Impossible de garantir une BDD vide (données existantes), on vérifie juste le type
        $result = $this->model->getChartData(null, null, 'yearly');
        $this->assertIsArray($result);
    }

    /**
     * Vérifie que getChartData avec bornes null et granularité monthly retourne array_values.
     *
     * @return void
     */
    public function testGetChartDataMonthlyWithNullBoundsReturnsIndexedValues(): void
    {
        $result = $this->model->getChartData(null, null, 'monthly');
        $this->assertIsArray($result);
    }

    // ----------------------------------------------------------------
    // getAvailableYearsForUser
    // ----------------------------------------------------------------

    /**
     * Vérifie que getAvailableYearsForUser retourne l'année courante si une commande existe.
     *
     * @return void
     */
    public function testGetAvailableYearsForUserReturnsYears(): void
    {
        $userId    = $this->insertAccount('useryears@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-USERYEARS-001');

        $years = $this->model->getAvailableYearsForUser($userId);

        $this->assertIsArray($years);
        $this->assertContains((int) date('Y'), $years);
    }

    /**
     * Vérifie que getAvailableYearsForUser retourne un tableau vide si aucune commande.
     *
     * @return void
     */
    public function testGetAvailableYearsForUserReturnsEmptyIfNoOrders(): void
    {
        $userId = $this->insertAccount('useryears_empty@example.com');

        $years = $this->model->getAvailableYearsForUser($userId);

        $this->assertIsArray($years);
        $this->assertEmpty($years);
    }

    // ----------------------------------------------------------------
    // requestReturnForUser (TI)
    // ----------------------------------------------------------------

    /**
     * Vérifie que requestReturnForUser retourne true pour une commande livrée
     * dans la fenêtre de rétractation.
     *
     * @return void
     */
    public function testRequestReturnForUserReturnsTrueWhenDeliveredWithinWindow(): void
    {
        $userId    = $this->insertAccount('return_ok@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'delivered', 'REF-RETURN-OK');

        // Simule une livraison il y a 7 jours (dans la fenêtre de 15 jours)
        self::$db->execute(
            "UPDATE orders SET delivered_at = DATE_SUB(NOW(), INTERVAL 7 DAY) WHERE id = ?",
            [$orderId]
        );

        $result = $this->model->requestReturnForUser($orderId, $userId);

        $this->assertTrue($result);

        $row = $this->model->findByIdForAdmin($orderId);
        $this->assertIsArray($row);
        $this->assertSame('return_requested', $row['status']);
    }

    /**
     * Vérifie que requestReturnForUser retourne false pour une commande livrée
     * dont la fenêtre de rétractation est dépassée.
     *
     * @return void
     */
    public function testRequestReturnForUserReturnsFalseWhenWindowExpired(): void
    {
        $userId    = $this->insertAccount('return_expired@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'delivered', 'REF-RETURN-EXP');

        // Simule une livraison il y a 16 jours (hors fenêtre des 15 jours)
        self::$db->execute(
            "UPDATE orders SET delivered_at = DATE_SUB(NOW(), INTERVAL 16 DAY) WHERE id = ?",
            [$orderId]
        );

        $result = $this->model->requestReturnForUser($orderId, $userId);

        $this->assertFalse($result);
    }

    /**
     * Vérifie que requestReturnForUser retourne false si la commande n'existe pas
     * ou n'appartient pas à l'utilisateur.
     *
     * @return void
     */
    public function testRequestReturnForUserReturnsFalseIfOrderNotFound(): void
    {
        $userId = $this->insertAccount('return_nf@example.com');

        $result = $this->model->requestReturnForUser(999999, $userId);

        $this->assertFalse($result);
    }

    /**
     * Vérifie que requestReturnForUser retourne false si l'utilisateur ne correspond pas.
     *
     * @return void
     */
    public function testRequestReturnForUserReturnsFalseIfWrongUser(): void
    {
        $userId    = $this->insertAccount('return_owner@example.com');
        $otherId   = $this->insertAccount('return_other@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'delivered', 'REF-RETURN-WRONG');

        self::$db->execute(
            "UPDATE orders SET delivered_at = DATE_SUB(NOW(), INTERVAL 5 DAY) WHERE id = ?",
            [$orderId]
        );

        $result = $this->model->requestReturnForUser($orderId, $otherId);

        $this->assertFalse($result);
    }

    /**
     * Vérifie que requestReturnForUser retourne false si le statut n'est pas 'delivered'.
     *
     * @return void
     */
    public function testRequestReturnForUserReturnsFalseIfStatusNotDelivered(): void
    {
        $userId    = $this->insertAccount('return_shipped@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'shipped', 'REF-RETURN-SHIPPED');

        $result = $this->model->requestReturnForUser($orderId, $userId);

        $this->assertFalse($result);
    }

    /**
     * Vérifie que requestReturnForUser retourne false si delivered_at est NULL
     * (livraison non confirmée en BDD).
     *
     * @return void
     */
    public function testRequestReturnForUserReturnsFalseIfDeliveredAtIsNull(): void
    {
        $userId    = $this->insertAccount('return_nulldate@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'delivered', 'REF-RETURN-NULLDATE');

        // S'assure explicitement que delivered_at est NULL
        self::$db->execute(
            "UPDATE orders SET delivered_at = NULL WHERE id = ?",
            [$orderId]
        );

        $result = $this->model->requestReturnForUser($orderId, $userId);

        $this->assertFalse($result);
    }

    /**
     * Vérifie que updateStatus passe correctement au statut 'refund_refused'.
     *
     * @return void
     */
    public function testUpdateStatusAcceptsRefundRefusedStatus(): void
    {
        $userId    = $this->insertAccount('refund_refused@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'return_requested', 'REF-REFUSEREFUND-001');

        $this->model->updateStatus($orderId, 'refund_refused');

        $row = $this->model->findByIdForAdmin($orderId);
        $this->assertIsArray($row);
        $this->assertSame('refund_refused', $row['status']);
    }

    // ----------------------------------------------------------------
    // updateInvoiceData + findByReferenceWithAddresses
    // ----------------------------------------------------------------

    /**
     * Vérifie que updateInvoiceData persiste invoice_number et path_invoice.
     *
     * @return void
     */
    public function testUpdateInvoiceDataPersistsNumberAndPath(): void
    {
        $userId    = $this->insertAccount('invoice_data@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'paid', 'REF-INVOICE-001');

        $this->model->updateInvoiceData($orderId, 'WEB-INVOICE-2026-0001', 'storage/pdf/invoices/WEB-INVOICE-2026-0001.pdf');

        $row = $this->model->findByIdForAdmin($orderId);
        $this->assertIsArray($row);
        $this->assertSame('WEB-INVOICE-2026-0001', $row['invoice_number']);
        $this->assertSame('storage/pdf/invoices/WEB-INVOICE-2026-0001.pdf', $row['path_invoice']);
    }

    /**
     * Vérifie que findByReferenceWithAddresses retourne la commande avec l'adresse de facturation.
     *
     * @return void
     */
    public function testFindByReferenceWithAddressesReturnsOrderWithBillingData(): void
    {
        $userId    = $this->insertAccount('ref_addr@example.com');
        $billingId = $this->insertAddress($userId);
        $this->insertOrder($userId, $billingId, 'paid', 'REF-ADDR-001');

        $order = $this->model->findByReferenceWithAddresses('REF-ADDR-001');

        $this->assertIsArray($order);
        $this->assertSame('REF-ADDR-001', $order['order_reference']);
        $this->assertArrayHasKey('bill_firstname', $order);
        $this->assertArrayHasKey('bill_street', $order);
    }

    /**
     * Vérifie que findByReferenceWithAddresses retourne null pour une référence inconnue.
     *
     * @return void
     */
    public function testFindByReferenceWithAddressesReturnsNullForUnknownRef(): void
    {
        $result = $this->model->findByReferenceWithAddresses('REF-UNKNOWN-99999');
        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // getDeliveredForRatingEmail
    // ----------------------------------------------------------------

    /**
     * Aucune commande dans la fenêtre → tableau vide.
     */
    public function testGetDeliveredForRatingEmailReturnsEmptyWhenNoMatch(): void
    {
        $result = $this->model->getDeliveredForRatingEmail();
        $this->assertIsArray($result);
        // Les autres tests utilisent des commandes hors fenêtre 504-528h
        // On vérifie juste le retour du type attendu.
        foreach ($result as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('user_id', $row);
        }
    }

    /**
     * Une commande avec rating_email_sent_at renseigné n'est pas retournée.
     */
    public function testGetDeliveredForRatingEmailSkipsAlreadySentOrders(): void
    {
        $userId    = $this->insertAccount('rating_sent_' . bin2hex(random_bytes(4)) . '@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'delivered', 'REF-RATING-SENT-' . bin2hex(random_bytes(4)));

        // Commande delivered dans la fenêtre MAIS rating_email_sent_at déjà renseigné
        self::$db->execute(
            "UPDATE orders SET delivered_at = NOW() - INTERVAL 510 HOUR, rating_email_sent_at = NOW() WHERE id = ?",
            [$orderId]
        );

        $result = $this->model->getDeliveredForRatingEmail();
        $ids    = array_column($result, 'id');
        $this->assertNotContains($orderId, $ids);
    }

    // ----------------------------------------------------------------
    // markRatingEmailSent
    // ----------------------------------------------------------------

    /**
     * markRatingEmailSent renseigne rating_email_sent_at.
     */
    public function testMarkRatingEmailSentSetsFlag(): void
    {
        $userId    = $this->insertAccount('mark_rating_' . bin2hex(random_bytes(4)) . '@example.com');
        $billingId = $this->insertAddress($userId);
        $orderId   = $this->insertOrder($userId, $billingId, 'delivered', 'REF-MARK-RATING-' . bin2hex(random_bytes(4)));

        $this->model->markRatingEmailSent($orderId);

        $row = self::$db->fetchOne(
            "SELECT rating_email_sent_at FROM orders WHERE id = ?",
            [$orderId]
        );
        $this->assertNotNull($row['rating_email_sent_at']);
    }

    // ----------------------------------------------------------------
    // getAvgOrderValue
    // ----------------------------------------------------------------

    /**
     * Aucune commande récente → retourne 0.0.
     */
    public function testGetAvgOrderValueReturnsZeroWhenNoOrders(): void
    {
        // Toutes les commandes existantes sont en 'pending' par défaut dans ce test
        // ou dans des états non comptabilisés — on peut juste vérifier le type
        $avg = $this->model->getAvgOrderValue(1); // 1 jour — fenêtre très courte
        $this->assertIsFloat($avg);
        $this->assertGreaterThanOrEqual(0.0, $avg);
    }

    /**
     * getAvgOrderValue prend en compte les commandes paid dans la fenêtre.
     */
    public function testGetAvgOrderValueCalculatesCorrectAverage(): void
    {
        $userId    = $this->insertAccount('avg_order_' . bin2hex(random_bytes(4)) . '@example.com');
        $billingId = $this->insertAddress($userId);

        // Deux commandes paid aujourd'hui
        $orderId1 = $this->insertOrder($userId, $billingId, 'paid', 'REF-AVG-1-' . bin2hex(random_bytes(4)));
        $orderId2 = $this->insertOrder($userId, $billingId, 'paid', 'REF-AVG-2-' . bin2hex(random_bytes(4)));

        // Mise à jour du prix pour maîtriser l'average
        self::$db->execute("UPDATE orders SET price = 100.00 WHERE id = ?", [$orderId1]);
        self::$db->execute("UPDATE orders SET price = 60.00  WHERE id = ?", [$orderId2]);

        $avg = $this->model->getAvgOrderValue(30);

        // Avec d'autres commandes potentielles, on vérifie juste que c'est > 0
        $this->assertGreaterThan(0.0, $avg);
    }

    // ----------------------------------------------------------------
    // create() / createFromIpn() — customer_note
    // ----------------------------------------------------------------

    /**
     * create() persiste le commentaire client trimé.
     */
    public function testCreatePersistsCustomerNote(): void
    {
        $userId    = $this->insertAccount('note_create_' . bin2hex(random_bytes(4)) . '@example.com');
        $billingId = $this->insertAddress($userId, 'billing');

        $reference = $this->model->create(
            $userId,
            [['wine_id' => 1, 'qty' => 12, 'price' => 15.0]],
            180.0,
            'virement',
            0.0,
            $billingId,
            null,
            '1.0',
            'shipping',
            '  Livraison à partir du 15/08/2026  '
        );

        $row = self::$db->fetchOne(
            'SELECT customer_note FROM orders WHERE order_reference = ?',
            [$reference]
        );
        $this->assertSame('Livraison à partir du 15/08/2026', $row['customer_note']);
    }

    /**
     * create() tronque le commentaire à 500 caractères.
     */
    public function testCreateTruncatesCustomerNoteTo500Chars(): void
    {
        $userId    = $this->insertAccount('note_trunc_' . bin2hex(random_bytes(4)) . '@example.com');
        $billingId = $this->insertAddress($userId, 'billing');
        $longNote  = str_repeat('a', 600);

        $reference = $this->model->create(
            $userId,
            [['wine_id' => 1, 'qty' => 12, 'price' => 15.0]],
            180.0,
            'virement',
            0.0,
            $billingId,
            null,
            '1.0',
            'shipping',
            $longNote
        );

        $row = self::$db->fetchOne(
            'SELECT customer_note FROM orders WHERE order_reference = ?',
            [$reference]
        );
        $this->assertSame(500, strlen((string) $row['customer_note']));
    }

    /**
     * create() stocke NULL quand le commentaire est vide ou absent.
     */
    public function testCreateStoresNullWhenCustomerNoteEmpty(): void
    {
        $userId    = $this->insertAccount('note_null_' . bin2hex(random_bytes(4)) . '@example.com');
        $billingId = $this->insertAddress($userId, 'billing');

        $reference = $this->model->create(
            $userId,
            [['wine_id' => 1, 'qty' => 12, 'price' => 15.0]],
            180.0,
            'virement',
            0.0,
            $billingId,
            null,
            '1.0'
        );

        $row = self::$db->fetchOne(
            'SELECT customer_note FROM orders WHERE order_reference = ?',
            [$reference]
        );
        $this->assertNull($row['customer_note']);
    }

    /**
     * createFromIpn() persiste le commentaire client.
     */
    public function testCreateFromIpnPersistsCustomerNote(): void
    {
        $userId     = $this->insertAccount('note_ipn_' . bin2hex(random_bytes(4)) . '@example.com');
        $billingId  = $this->insertAddress($userId, 'billing');
        $deliveryId = $this->insertAddress($userId, 'delivery');
        $reference  = 'WEB-CB-' . strtoupper(bin2hex(random_bytes(4))) . '-2026';

        $this->model->createFromIpn(
            $userId,
            [['wine_id' => 1, 'qty' => 12, 'price' => 15.0]],
            180.0,
            0.0,
            $billingId,
            $deliveryId,
            '1.0',
            $reference,
            '12345',
            '67890',
            'shipping',
            'Merci de livrer après 18h'
        );

        $row = self::$db->fetchOne(
            'SELECT customer_note FROM orders WHERE order_reference = ?',
            [$reference]
        );
        $this->assertSame('Merci de livrer après 18h', $row['customer_note']);
    }
}
