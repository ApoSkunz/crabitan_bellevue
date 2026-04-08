<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour les méthodes admin de AccountModel :
 * findAdmins, blockAccount, unblockAccount.
 */
class AccountModelGestionAdminsTest extends IntegrationTestCase
{
    private AccountModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AccountModel();
    }

    private function insertAdmin(string $email = 'admin@test.local', string $role = 'admin'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, ?, 'fr', NOW())",
            [$email, password_hash('Admin123!', PASSWORD_BCRYPT), $role]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Admin', 'Test', 'M')",
            [$id]
        );
        return $id;
    }

    public function testFindAdminsReturnsOnlyAdminRoles(): void
    {
        $adminId      = $this->insertAdmin('admin1@test.local', 'admin');
        $superAdminId = $this->insertAdmin('superadmin@test.local', 'super_admin');

        $admins = $this->model->findAdmins();
        $ids    = array_column($admins, 'id');

        $this->assertContains($adminId, $ids);
        $this->assertContains($superAdminId, $ids);

        foreach ($admins as $a) {
            $this->assertContains($a['role'], ['admin', 'super_admin']);
        }
    }

    public function testFindAdminsExcludesDeletedAccounts(): void
    {
        $adminId = $this->insertAdmin('deleted@test.local', 'admin');
        self::$db->execute("UPDATE accounts SET deleted_at = NOW() WHERE id = ?", [$adminId]);

        $admins = $this->model->findAdmins();
        $ids    = array_column($admins, 'id');
        $this->assertNotContains($adminId, $ids);
    }

    public function testFindAdminsContainsIsBlockedField(): void
    {
        $this->insertAdmin('admin2@test.local', 'admin');
        $admins = $this->model->findAdmins();
        $this->assertNotEmpty($admins);
        $this->assertArrayHasKey('is_blocked', $admins[0]);
        $this->assertSame(0, (int) $admins[0]['is_blocked']);
    }

    public function testBlockAccountSetsIsBlockedFlag(): void
    {
        $adminId = $this->insertAdmin();
        $this->model->blockAccount($adminId);
        $row = self::$db->fetchOne("SELECT is_blocked FROM accounts WHERE id = ?", [$adminId]);
        $this->assertSame(1, (int) $row['is_blocked']);
    }

    public function testBlockAccountRevokesActiveSessions(): void
    {
        $adminId = $this->insertAdmin();
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, 'test-token-abc', 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$adminId]
        );
        $this->model->blockAccount($adminId);
        $row = self::$db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM connections WHERE user_id = ? AND status = 'active'",
            [$adminId]
        );
        $this->assertSame(0, (int) $row['cnt']);
    }

    public function testBlockAccountDeletesTrustedDevices(): void
    {
        $adminId = $this->insertAdmin();
        self::$db->insert(
            "INSERT INTO trusted_devices (user_id, device_token, device_name)
             VALUES (?, 'device-abc', 'Chrome · Windows')",
            [$adminId]
        );
        $this->model->blockAccount($adminId);
        $row = self::$db->fetchOne("SELECT COUNT(*) AS cnt FROM trusted_devices WHERE user_id = ?", [$adminId]);
        $this->assertSame(0, (int) $row['cnt']);
    }

    public function testUnblockAccountClearsIsBlockedFlag(): void
    {
        $adminId = $this->insertAdmin();
        self::$db->execute("UPDATE accounts SET is_blocked = 1 WHERE id = ?", [$adminId]);
        $this->model->unblockAccount($adminId);
        $row = self::$db->fetchOne("SELECT is_blocked FROM accounts WHERE id = ?", [$adminId]);
        $this->assertSame(0, (int) $row['is_blocked']);
    }

    public function testUnblockAccountIdempotentWhenAlreadyUnblocked(): void
    {
        $adminId = $this->insertAdmin();
        $this->model->unblockAccount($adminId);
        $row = self::$db->fetchOne("SELECT is_blocked FROM accounts WHERE id = ?", [$adminId]);
        $this->assertSame(0, (int) $row['is_blocked']);
    }
}
