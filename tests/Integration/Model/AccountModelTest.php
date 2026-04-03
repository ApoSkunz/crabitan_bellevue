<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Tests\Integration\IntegrationTestCase;

class AccountModelTest extends IntegrationTestCase
{
    private AccountModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AccountModel();
    }

    private function createAccount(string $email = 'test@example.com'): string
    {
        return $this->model->create(
            'individual',
            $email,
            password_hash('password123', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Dupont',
            'Jean',
            ''
        );
    }

    public function testCreateReturnsId(): void
    {
        $id = $this->createAccount();
        $this->assertNotEmpty($id);
        $this->assertIsNumeric($id);
    }

    public function testFindByEmail(): void
    {
        $this->createAccount('find@example.com');
        $account = $this->model->findByEmail('find@example.com');

        $this->assertIsArray($account);
        $this->assertSame('find@example.com', $account['email']);
        $this->assertSame('Dupont', $account['lastname']);
    }

    public function testFindByEmailReturnsfalseIfNotFound(): void
    {
        $result = $this->model->findByEmail('nobody@example.com');
        $this->assertFalse($result);
    }

    public function testFindByVerificationToken(): void
    {
        $token     = bin2hex(random_bytes(16));
        $accountId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter, email_verification_token)
             VALUES ('token@example.com', 'h', 'fr', 0, ?)",
            [$token]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Token', 'User', 'M')",
            [$accountId]
        );

        $account = $this->model->findByVerificationToken($token);
        $this->assertIsArray($account);
        $this->assertSame('token@example.com', $account['email']);
    }

    public function testFindByVerificationTokenReturnsfalseIfInvalid(): void
    {
        $result = $this->model->findByVerificationToken('invalidtoken');
        $this->assertFalse($result);
    }

    public function testVerifyEmail(): void
    {
        $id = $this->createAccount('verify@example.com');
        $this->model->verifyEmail((int)$id);

        $account = $this->model->findById((int)$id);
        $this->assertIsArray($account);
        $this->assertNotNull($account['email_verified_at']);
        $this->assertNull($account['email_verification_token']);
    }

    public function testRefreshVerificationTokenUpdatesTokenAndExpiry(): void
    {
        $id       = $this->createAccount('refresh@example.com');
        $newToken = bin2hex(random_bytes(32));

        $this->model->refreshVerificationToken((int)$id, $newToken);

        $account = $this->model->findById((int)$id);
        $this->assertIsArray($account);
        $this->assertSame($newToken, $account['email_verification_token']);
        $this->assertNull($account['email_verified_at'], 'Le compte ne doit pas être considéré vérifié');
        $this->assertNotNull($account['email_verification_token_expires_at']);
    }

    public function testRefreshVerificationTokenDoesNotUpdateAlreadyVerifiedAccount(): void
    {
        $id = $this->createAccount('refresh-verified@example.com');
        // Vérifie d'abord le compte
        $this->model->verifyEmail((int)$id);
        $originalToken = 'should-not-change';

        // La méthode ne doit rien modifier car email_verified_at IS NOT NULL
        $this->model->refreshVerificationToken((int)$id, $originalToken);

        $account = $this->model->findById((int)$id);
        $this->assertIsArray($account);
        $this->assertNull($account['email_verification_token'], 'Le token ne doit pas être réécrit après vérification');
    }

    public function testUpdatePassword(): void
    {
        $id = $this->createAccount('pwd@example.com');
        $newHash = password_hash('newpassword', PASSWORD_BCRYPT);
        $this->model->updatePassword((int)$id, $newHash);

        $account = $this->model->findById((int)$id);
        $this->assertIsArray($account);
        $this->assertTrue(password_verify('newpassword', $account['password']));
    }

    public function testUpdateLang(): void
    {
        $id = $this->createAccount('lang@example.com');
        $this->model->updateLang((int)$id, 'en');

        $account = $this->model->findById((int)$id);
        $this->assertIsArray($account);
        $this->assertSame('en', $account['lang']);
    }

    public function testFindById(): void
    {
        $id = $this->createAccount('byid@example.com');
        $account = $this->model->findById((int)$id);

        $this->assertIsArray($account);
        $this->assertSame('byid@example.com', $account['email']);
    }

    public function testFindByIdReturnsfalseIfNotFound(): void
    {
        $result = $this->model->findById(999999);
        $this->assertFalse($result);
    }

    public function testDeleteRemovesAccount(): void
    {
        $id = $this->createAccount('del@example.com');
        $this->model->delete((int)$id);

        $result = $this->model->findById((int)$id);
        $this->assertFalse($result);
    }

    public function testSoftDeleteExcludedFromFindByEmail(): void
    {
        $id = $this->createAccount('soft@example.com');
        self::$db->execute(
            "UPDATE accounts SET deleted_at = NOW() WHERE id = ?",
            [(int)$id]
        );

        $result = $this->model->findByEmail('soft@example.com');
        $this->assertFalse($result);
    }

    // ----------------------------------------------------------------
    // findByGoogleId / findByAppleId
    // ----------------------------------------------------------------

    public function testFindByGoogleIdReturnsAccount(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter, google_id)
             VALUES ('google@example.com', 'h', 'fr', 0, 'g-uid-123')"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Google', 'User', 'M')",
            [$id]
        );

        $result = $this->model->findByGoogleId('g-uid-123');
        $this->assertIsArray($result);
        $this->assertSame('google@example.com', $result['email']);
    }

    public function testFindByGoogleIdReturnsFalseIfNotFound(): void
    {
        $result = $this->model->findByGoogleId('nonexistent-google-id');
        $this->assertFalse($result);
    }

    public function testFindByAppleIdReturnsAccount(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter, apple_id)
             VALUES ('apple@example.com', 'h', 'fr', 0, 'apple-uid-456')"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Apple', 'User', 'F')",
            [$id]
        );

        $result = $this->model->findByAppleId('apple-uid-456');
        $this->assertIsArray($result);
        $this->assertSame('apple@example.com', $result['email']);
    }

    public function testFindByAppleIdReturnsFalseIfNotFound(): void
    {
        $result = $this->model->findByAppleId('nonexistent-apple-id');
        $this->assertFalse($result);
    }

    // ----------------------------------------------------------------
    // create — compte company
    // ----------------------------------------------------------------

    public function testCreateCompanyAccount(): void
    {
        $id = $this->model->create(
            'company',
            'company@example.com',
            password_hash('password123', PASSWORD_BCRYPT),
            'fr',
            1,
            bin2hex(random_bytes(16)),
            '',
            '',
            '',
            'Château Test SARL'
        );

        $this->assertNotEmpty($id);
        $account = $this->model->findByEmail('company@example.com');
        $this->assertIsArray($account);
        $this->assertSame('company', $account['account_type']);
        $this->assertSame('Château Test SARL', $account['company_name']);
    }

    // ----------------------------------------------------------------
    // updateIndividualProfile / updateCompanyProfile
    // ----------------------------------------------------------------

    public function testUpdateIndividualProfile(): void
    {
        $id = $this->createAccount('profile@example.com');
        $this->model->updateIndividualProfile((int)$id, 'F', 'Alice', 'Martin');

        $row = self::$db->fetchOne(
            "SELECT civility, firstname, lastname FROM account_individuals WHERE account_id = ?",
            [(int)$id]
        );
        $this->assertIsArray($row);
        $this->assertSame('F', $row['civility']);
        $this->assertSame('Alice', $row['firstname']);
        $this->assertSame('Martin', $row['lastname']);
    }

    public function testUpdateCompanyProfile(): void
    {
        $companyId = $this->model->create(
            'company',
            'companyprofile@example.com',
            password_hash('password123', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            '',
            '',
            '',
            'Old Company Name'
        );

        $this->model->updateCompanyProfile((int)$companyId, 'New Company SARL', '12345678901234');

        $row = self::$db->fetchOne(
            "SELECT company_name, siret FROM account_companies WHERE account_id = ?",
            [(int)$companyId]
        );
        $this->assertIsArray($row);
        $this->assertSame('New Company SARL', $row['company_name']);
        $this->assertSame('12345678901234', $row['siret']);
    }

    public function testUpdateCompanyProfileWithNullSiret(): void
    {
        $companyId = $this->model->create(
            'company',
            'companynull@example.com',
            password_hash('password123', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            '',
            '',
            '',
            'Some Company'
        );

        $this->model->updateCompanyProfile((int)$companyId, 'Some Company', null);

        $row = self::$db->fetchOne(
            "SELECT siret FROM account_companies WHERE account_id = ?",
            [(int)$companyId]
        );
        $this->assertIsArray($row);
        $this->assertNull($row['siret']);
    }

    // ----------------------------------------------------------------
    // updateNewsletter
    // ----------------------------------------------------------------

    public function testUpdateNewsletterSubscribes(): void
    {
        $id = $this->createAccount('nl@example.com');
        $this->model->updateNewsletter((int)$id, true);

        $row = self::$db->fetchOne(
            "SELECT newsletter FROM accounts WHERE id = ?",
            [(int)$id]
        );
        $this->assertSame(1, (int)$row['newsletter']);
    }

    public function testUpdateNewsletterUnsubscribes(): void
    {
        // Insert subscriber directly
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter)
             VALUES ('nl-unsub@example.com', 'h', 'fr', 1)"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'NL', 'User', 'M')",
            [$id]
        );

        $this->model->updateNewsletter($id, false);

        $row = self::$db->fetchOne(
            "SELECT newsletter FROM accounts WHERE id = ?",
            [$id]
        );
        $this->assertSame(0, (int)$row['newsletter']);
    }

    // ----------------------------------------------------------------
    // revokeAllSessions
    // ----------------------------------------------------------------

    public function testRevokeAllSessionsUpdatesActiveConnections(): void
    {
        $id = (int) $this->createAccount('revoke@example.com');

        // Insert two active connections
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, 'tok1', 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$id]
        );
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, 'tok2', 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$id]
        );

        $this->model->revokeAllSessions($id);

        $active = self::$db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM connections WHERE user_id = ? AND status = 'active'",
            [$id]
        );
        $this->assertSame(0, (int)$active['cnt']);

        $revoked = self::$db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM connections WHERE user_id = ? AND status = 'revoked'",
            [$id]
        );
        $this->assertSame(2, (int)$revoked['cnt']);
    }

    // ----------------------------------------------------------------
    // markAsConnected
    // ----------------------------------------------------------------

    public function testMarkAsConnected(): void
    {
        $id = (int) $this->createAccount('connected@example.com');
        $this->model->markAsConnected($id);

        $row = self::$db->fetchOne(
            "SELECT has_connected FROM accounts WHERE id = ?",
            [$id]
        );
        $this->assertSame(1, (int)$row['has_connected']);
    }

    // ----------------------------------------------------------------
    // delete + getReactivationToken
    // ----------------------------------------------------------------

    public function testDeleteSetsReactivationToken(): void
    {
        $id = (int) $this->createAccount('dtoken@example.com');
        $this->model->delete($id);

        $token = $this->model->getReactivationToken($id);
        $this->assertNotNull($token);
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testGetReactivationTokenReturnsNullIfNoToken(): void
    {
        $id = (int) $this->createAccount('notoken@example.com');
        // Account not deleted — reactivation_token is NULL
        $token = $this->model->getReactivationToken($id);
        $this->assertNull($token);
    }

    // ----------------------------------------------------------------
    // findByReactivationToken + reactivate
    // ----------------------------------------------------------------

    public function testFindByReactivationTokenReturnsPendingDeletion(): void
    {
        $id = (int) $this->createAccount('react@example.com');
        $this->model->delete($id);

        $token = $this->model->getReactivationToken($id);
        $this->assertNotNull($token);

        $result = $this->model->findByReactivationToken($token);
        $this->assertIsArray($result);
        $this->assertSame('react@example.com', $result['email']);
    }

    public function testFindByReactivationTokenReturnsFalseIfInvalid(): void
    {
        $result = $this->model->findByReactivationToken('invalid-token-xyz');
        $this->assertFalse($result);
    }

    public function testReactivateRestoresAccount(): void
    {
        $id = (int) $this->createAccount('reactivate@example.com');
        $this->model->delete($id);

        // Account is soft-deleted, findById should return false
        $this->assertFalse($this->model->findById($id));

        $this->model->reactivate($id);

        // After reactivation, account should be findable again
        $account = $this->model->findById($id);
        $this->assertIsArray($account);
        $this->assertSame('reactivate@example.com', $account['email']);
    }

    // ----------------------------------------------------------------
    // findByUnsubscribeToken + unsubscribeByToken
    // ----------------------------------------------------------------

    public function testFindByUnsubscribeTokenReturnsAccount(): void
    {
        $token = bin2hex(random_bytes(16));
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter, newsletter_unsubscribe_token)
             VALUES ('unsub@example.com', 'h', 'fr', 1, ?)",
            [$token]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Unsub', 'User', 'M')",
            [$id]
        );

        $result = $this->model->findByUnsubscribeToken($token);
        $this->assertIsArray($result);
        $this->assertSame('unsub@example.com', $result['email']);
    }

    public function testFindByUnsubscribeTokenReturnsFalseIfInvalid(): void
    {
        $result = $this->model->findByUnsubscribeToken('nonexistent-unsub-token');
        $this->assertFalse($result);
    }

    public function testUnsubscribeByTokenUnsubscribesAndRotatesToken(): void
    {
        $originalToken = bin2hex(random_bytes(16));
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter, newsletter_unsubscribe_token)
             VALUES ('unsub2@example.com', 'h', 'fr', 1, ?)",
            [$originalToken]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Unsub2', 'User', 'F')",
            [$id]
        );

        $result = $this->model->unsubscribeByToken($originalToken);
        $this->assertTrue($result);

        $row = self::$db->fetchOne(
            "SELECT newsletter, newsletter_unsubscribe_token FROM accounts WHERE id = ?",
            [$id]
        );
        $this->assertSame(0, (int)$row['newsletter']);
        // Token should have been rotated (different from original)
        $this->assertNotSame($originalToken, $row['newsletter_unsubscribe_token']);
    }

    public function testUnsubscribeByTokenReturnsFalseForInvalidToken(): void
    {
        $result = $this->model->unsubscribeByToken('invalid-token-xyz');
        $this->assertFalse($result);
    }

    // ----------------------------------------------------------------
    // purgeScheduledDeletions
    // ----------------------------------------------------------------

    public function testPurgeScheduledDeletionsReturnsZeroWhenNoneExpired(): void
    {
        // Create a recently deleted account (not yet expired)
        $id = (int) $this->createAccount('purge-future@example.com');
        self::$db->execute(
            "UPDATE accounts SET deleted_at = NOW(), scheduled_deletion_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?",
            [$id]
        );

        $count = $this->model->purgeScheduledDeletions();
        $this->assertSame(0, $count);
    }

    public function testPurgeScheduledDeletionsAnonymisesExpiredAccounts(): void
    {
        // Create an account with an already-expired deletion date
        $id = (int) $this->createAccount('purge-expired@example.com');
        self::$db->execute(
            "UPDATE accounts SET deleted_at = DATE_SUB(NOW(), INTERVAL 31 DAY),
                scheduled_deletion_at = DATE_SUB(NOW(), INTERVAL 1 DAY)
             WHERE id = ?",
            [$id]
        );

        $count = $this->model->purgeScheduledDeletions();
        $this->assertGreaterThanOrEqual(1, $count);

        $row = self::$db->fetchOne(
            "SELECT email, password, newsletter, scheduled_deletion_at FROM accounts WHERE id = ?",
            [$id]
        );
        $this->assertIsArray($row);
        $this->assertStringContainsString('@purged.invalid', $row['email']);
        $this->assertNull($row['password']);
        $this->assertSame(0, (int)$row['newsletter']);
        $this->assertNull($row['scheduled_deletion_at']);
    }

    // ----------------------------------------------------------------
    // getForAdmin / countForAdmin / updateRole / countTotal
    // ----------------------------------------------------------------

    public function testCountTotalReturnsCorrectCount(): void
    {
        $before = $this->model->countTotal();
        $this->createAccount('count1@example.com');
        $this->createAccount('count2@example.com');
        $after = $this->model->countTotal();

        $this->assertSame($before + 2, $after);
    }

    public function testGetForAdminReturnsAccountsWithoutFilters(): void
    {
        $this->createAccount('admin-list@example.com');
        $results = $this->model->getForAdmin(10, 0, null, null);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function testGetForAdminFiltersbyRole(): void
    {
        $this->createAccount('admin-role@example.com');
        // All created accounts get default role 'customer'
        $results = $this->model->getForAdmin(10, 0, 'customer', null);

        $this->assertIsArray($results);
        foreach ($results as $row) {
            $this->assertSame('customer', $row['role']);
        }
    }

    public function testGetForAdminFiltersbyTypeIndividual(): void
    {
        $this->createAccount('admin-type-ind@example.com');
        $results = $this->model->getForAdmin(10, 0, null, null, 'individual');

        $this->assertIsArray($results);
        foreach ($results as $row) {
            $this->assertSame('individual', $row['account_type']);
        }
    }

    public function testGetForAdminFiltersbyTypeCompany(): void
    {
        $this->model->create(
            'company',
            'admin-type-co@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            '',
            '',
            '',
            'Test Company'
        );

        $results = $this->model->getForAdmin(10, 0, null, null, 'company');

        $this->assertIsArray($results);
        foreach ($results as $row) {
            $this->assertSame('company', $row['account_type']);
        }
    }

    public function testGetForAdminSearchByEmail(): void
    {
        $this->createAccount('searchable@example.com');
        $results = $this->model->getForAdmin(10, 0, null, 'searchable');

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $emails = array_column($results, 'email');
        $this->assertContains('searchable@example.com', $emails);
    }

    public function testCountForAdminWithNoFilters(): void
    {
        $before = $this->model->countForAdmin(null, null);
        $this->createAccount('cf-admin@example.com');
        $after = $this->model->countForAdmin(null, null);

        $this->assertSame($before + 1, $after);
    }

    public function testCountForAdminWithRoleFilter(): void
    {
        $this->createAccount('cf-cust@example.com');
        $count = $this->model->countForAdmin('customer', null);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testCountForAdminWithInvalidRoleIgnored(): void
    {
        $count = $this->model->countForAdmin('hacker', null);
        $this->assertIsInt($count);
    }

    public function testUpdateRoleChangesRole(): void
    {
        $id = (int) $this->createAccount('role@example.com');
        $this->model->updateRole($id, 'admin');

        $account = $this->model->findById($id);
        $this->assertIsArray($account);
        $this->assertSame('admin', $account['role']);
    }

    public function testUpdateRoleIgnoresInvalidRole(): void
    {
        $id = (int) $this->createAccount('role-invalid@example.com');
        $this->model->updateRole($id, 'hacker');

        $account = $this->model->findById($id);
        $this->assertIsArray($account);
        // Role should remain 'customer' (default)
        $this->assertSame('customer', $account['role']);
    }

    // ----------------------------------------------------------------
    // Newsletter admin methods
    // ----------------------------------------------------------------

    public function testGetNewsletterSubscribersReturnsSubscribers(): void
    {
        // Insert a subscribed account
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter, newsletter_unsubscribe_token)
             VALUES ('nl-sub@example.com', 'h', 'fr', 1, 'some-token')"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Sub', 'NL', 'M')",
            [$id]
        );

        $results = $this->model->getNewsletterSubscribers(10, 0);
        $emails = array_column($results, 'email');
        $this->assertContains('nl-sub@example.com', $emails);
    }

    public function testGetNewsletterSubscribersExcludesDeleted(): void
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter, deleted_at)
             VALUES ('nl-deleted@example.com', 'h', 'fr', 1, NOW())"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Del', 'NL', 'M')",
            [$id]
        );

        $results = $this->model->getNewsletterSubscribers(100, 0);
        $emails = array_column($results, 'email');
        $this->assertNotContains('nl-deleted@example.com', $emails);
    }

    public function testCountNewsletterSubscribers(): void
    {
        $before = $this->model->countNewsletterSubscribers();

        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter)
             VALUES ('nl-count@example.com', 'h', 'fr', 1)"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Count', 'NL', 'F')",
            [$id]
        );

        $after = $this->model->countNewsletterSubscribers();
        $this->assertSame($before + 1, $after);
    }

    // ----------------------------------------------------------------
    // getReactivationToken — branche id inexistant
    // ----------------------------------------------------------------

    /**
     * Vérifie que getReactivationToken retourne null quand l'ID n'existe pas en BDD.
     *
     * Couvre la branche `$row === false` de la ligne :
     *   return $row !== false ? ($row['reactivation_token'] ?? null) : null;
     *
     * @return void
     */
    public function testGetReactivationTokenReturnsNullForUnknownId(): void
    {
        $token = $this->model->getReactivationToken(999999);
        $this->assertNull($token);
    }
}
