<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration — détection d'inactivité et purge RGPD (Art. 5 §1e).
 */
class AccountModelInactivityTest extends IntegrationTestCase
{
    private AccountModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AccountModel();
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function insertCustomer(string $email, string $createdAt = '-37 months'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, has_connected)
             VALUES (?, 'hash', 'customer', 'fr', NOW(), 1)",
            [$email]
        );
        // Antidater created_at pour simuler l'inactivité
        self::$db->execute(
            "UPDATE accounts SET created_at = DATE_SUB(NOW(), INTERVAL ? MONTH) WHERE id = ?",
            [37, $id]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Inactif', 'Test', 'M')",
            [$id]
        );
        return $id;
    }

    private function addConnection(int $userId, string $interval = '-1 month'): void
    {
        // $interval doit être un entier de mois (positif)
        // Utilisation directe de la date calculée
        self::$db->insert(
            "INSERT INTO connections (user_id, token, device_token, ip_address, user_agent, device_name, auth_method, status, expired_at, created_at)
             VALUES (?, 'tok', 'dev', '127.0.0.1', 'UA', 'PC', 'password', 'active',
                     DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 1 MONTH))",
            [$userId]
        );
    }

    private function addRecentConnection(int $userId): void
    {
        self::$db->insert(
            "INSERT INTO connections (user_id, token, device_token, ip_address, user_agent, device_name, auth_method, status, expired_at, created_at)
             VALUES (?, 'tok_recent', 'dev_recent', '127.0.0.1', 'UA', 'PC', 'password', 'active', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())",
            [$userId]
        );
        self::$db->execute(
            "UPDATE accounts SET last_login_at = NOW() WHERE id = ?",
            [$userId]
        );
    }

    // ── getInactiveAccounts ──────────────────────────────────────────────────

    /**
     * Retourne un compte sans connexion depuis 37 mois (seuil 36).
     */
    public function testGetInactiveAccountsReturnsStaleAccount(): void
    {
        $id = $this->insertCustomer('inactive.stale@test.local');

        $result = $this->model->getInactiveAccounts(36);

        $ids = array_column($result, 'id');
        $this->assertContains($id, $ids);
    }

    /**
     * Exclut un compte avec connexion récente.
     */
    public function testGetInactiveAccountsExcludesRecentlyConnected(): void
    {
        $id = $this->insertCustomer('inactive.recent@test.local');
        $this->addRecentConnection($id);

        $result = $this->model->getInactiveAccounts(36);

        $ids = array_column($result, 'id');
        $this->assertNotContains($id, $ids);
    }

    /**
     * Exclut les admins.
     */
    public function testGetInactiveAccountsExcludesAdmins(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES ('inactive.admin@test.local', 'hash', 'admin', 'fr', NOW())",
            []
        );
        self::$db->execute(
            "UPDATE accounts SET created_at = DATE_SUB(NOW(), INTERVAL 37 MONTH) WHERE id = ?",
            [$id]
        );

        $result = $this->model->getInactiveAccounts(36);

        $ids = array_column($result, 'id');
        $this->assertNotContains($id, $ids);
    }

    /**
     * Exclut les comptes déjà soft-deleted.
     */
    public function testGetInactiveAccountsExcludesSoftDeleted(): void
    {
        $id = $this->insertCustomer('inactive.deleted@test.local');
        self::$db->execute(
            "UPDATE accounts SET deleted_at = NOW() WHERE id = ?",
            [$id]
        );

        $result = $this->model->getInactiveAccounts(36);

        $ids = array_column($result, 'id');
        $this->assertNotContains($id, $ids);
    }

    /**
     * Exclut les comptes déjà avertis.
     */
    public function testGetInactiveAccountsExcludesAlreadyWarned(): void
    {
        $id = $this->insertCustomer('inactive.warned@test.local');
        self::$db->execute(
            "UPDATE accounts SET inactivity_warning_sent_at = NOW() WHERE id = ?",
            [$id]
        );

        $result = $this->model->getInactiveAccounts(36);

        $ids = array_column($result, 'id');
        $this->assertNotContains($id, $ids);
    }

    // ── markInactivityWarningSent ────────────────────────────────────────────

    /**
     * Pose inactivity_warning_sent_at sur le compte.
     */
    public function testMarkInactivityWarningSentUpdatesColumn(): void
    {
        $id = $this->insertCustomer('inactive.mark@test.local');

        $before = self::$db->fetchOne(
            "SELECT inactivity_warning_sent_at FROM accounts WHERE id = ?",
            [$id]
        );
        $this->assertNull($before['inactivity_warning_sent_at']);

        $this->model->markInactivityWarningSent($id);

        $after = self::$db->fetchOne(
            "SELECT inactivity_warning_sent_at FROM accounts WHERE id = ?",
            [$id]
        );
        $this->assertNotNull($after['inactivity_warning_sent_at']);
    }

    // ── getAccountsToSoftDelete ──────────────────────────────────────────────

    /**
     * Retourne un compte averti il y a 31 jours sans reconnexion.
     */
    public function testGetAccountsToSoftDeleteReturnsExpiredWarning(): void
    {
        $id = $this->insertCustomer('inactive.tosoftdelete@test.local');
        self::$db->execute(
            "UPDATE accounts SET inactivity_warning_sent_at = DATE_SUB(NOW(), INTERVAL 31 DAY) WHERE id = ?",
            [$id]
        );

        $result = $this->model->getAccountsToSoftDelete();

        $ids = array_column($result, 'id');
        $this->assertContains($id, $ids);
    }

    /**
     * Exclut un compte qui s'est reconnecté après l'avertissement.
     */
    public function testGetAccountsToSoftDeleteExcludesReconnectedAccount(): void
    {
        $id = $this->insertCustomer('inactive.reconnected@test.local');
        self::$db->execute(
            "UPDATE accounts SET inactivity_warning_sent_at = DATE_SUB(NOW(), INTERVAL 31 DAY) WHERE id = ?",
            [$id]
        );
        // Connexion APRÈS l'avertissement
        self::$db->insert(
            "INSERT INTO connections (user_id, token, device_token, ip_address, user_agent, device_name, auth_method, status, expired_at, created_at)
             VALUES (?, 'tok_after', 'dev_after', '127.0.0.1', 'UA', 'PC', 'password', 'active', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())",
            [$id]
        );

        $result = $this->model->getAccountsToSoftDelete();

        $ids = array_column($result, 'id');
        $this->assertNotContains($id, $ids);
    }

    /**
     * Exclut un compte averti il y a moins de 30 jours.
     */
    public function testGetAccountsToSoftDeleteExcludesRecentWarning(): void
    {
        $id = $this->insertCustomer('inactive.recent.warning@test.local');
        self::$db->execute(
            "UPDATE accounts SET inactivity_warning_sent_at = DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE id = ?",
            [$id]
        );

        $result = $this->model->getAccountsToSoftDelete();

        $ids = array_column($result, 'id');
        $this->assertNotContains($id, $ids);
    }

    // ── softDeleteForInactivity ──────────────────────────────────────────────

    /**
     * Soft-delete pose deleted_at et scheduled_deletion_at.
     */
    public function testSoftDeleteForInactivitySetsDates(): void
    {
        $id = $this->insertCustomer('inactive.softdelete@test.local');

        $this->model->softDeleteForInactivity($id);

        $account = self::$db->fetchOne(
            "SELECT deleted_at, scheduled_deletion_at, reactivation_token FROM accounts WHERE id = ?",
            [$id]
        );
        $this->assertNotNull($account['deleted_at']);
        $this->assertNotNull($account['scheduled_deletion_at']);
        $this->assertNotNull($account['reactivation_token']);
    }

    /**
     * Soft-delete révoque toutes les sessions actives.
     */
    public function testSoftDeleteForInactivityRevokesActiveSessions(): void
    {
        $id = $this->insertCustomer('inactive.revoke@test.local');
        self::$db->insert(
            "INSERT INTO connections (user_id, token, device_token, ip_address, user_agent, device_name, auth_method, status, expired_at)
             VALUES (?, 'tok_active', 'dev', '127.0.0.1', 'UA', 'PC', 'password', 'active', DATE_ADD(NOW(), INTERVAL 30 DAY))",
            [$id]
        );

        $this->model->softDeleteForInactivity($id);

        $active = self::$db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM connections WHERE user_id = ? AND status = 'active'",
            [$id]
        );
        $this->assertSame(0, (int) $active['cnt']);
    }
}
