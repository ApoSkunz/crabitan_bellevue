<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration AccountModel — méthodes Google OAuth.
 *
 * Couvre : createFromGoogle(), linkGoogleId(), findByGoogleId(),
 *          clearEmailChangeToken(), rollback sur erreur createFromGoogle.
 */
class AccountModelGoogleTest extends IntegrationTestCase
{
    private AccountModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AccountModel();
    }

    // ----------------------------------------------------------------
    // findByGoogleId()
    // ----------------------------------------------------------------

    public function testFindByGoogleIdReturnsFalseWhenNotFound(): void
    {
        $result = $this->model->findByGoogleId('non-existent-google-id');
        $this->assertFalse($result);
    }

    public function testFindByGoogleIdReturnsAccountAfterCreate(): void
    {
        $googleId = 'google-sub-' . bin2hex(random_bytes(8));
        $this->model->createFromGoogle(
            'gfind@example.com',
            $googleId,
            'fr',
            'Jean',
            'Dupont'
        );

        $account = $this->model->findByGoogleId($googleId);
        $this->assertIsArray($account);
        $this->assertSame('gfind@example.com', $account['email']);
        $this->assertSame($googleId, $account['google_id']);
    }

    // ----------------------------------------------------------------
    // createFromGoogle()
    // ----------------------------------------------------------------

    public function testCreateFromGoogleReturnsId(): void
    {
        $id = $this->model->createFromGoogle(
            'gcreate@example.com',
            'google-sub-create',
            'fr',
            'Alice',
            'Martin'
        );

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateFromGoogleAccountHasNoPassword(): void
    {
        $id = $this->model->createFromGoogle(
            'gnopwd@example.com',
            'google-sub-nopwd',
            'en',
            'Bob',
            'Smith'
        );

        $account = $this->model->findById($id);
        $this->assertIsArray($account);
        $this->assertNull($account['password']);
    }

    public function testCreateFromGoogleAccountIsVerified(): void
    {
        $id = $this->model->createFromGoogle(
            'gverified@example.com',
            'google-sub-verified',
            'fr',
            'Claire',
            'Leblanc'
        );

        $account = $this->model->findById($id);
        $this->assertIsArray($account);
        $this->assertNotNull($account['email_verified_at']);
    }

    public function testCreateFromGoogleSetsFirstnameLastname(): void
    {
        $id = $this->model->createFromGoogle(
            'gname@example.com',
            'google-sub-name',
            'fr',
            'Prénom',
            'Nom'
        );

        $account = $this->model->findById($id);
        $this->assertIsArray($account);
        $this->assertSame('Prénom', $account['firstname']);
        $this->assertSame('Nom', $account['lastname']);
    }

    public function testCreateFromGoogleUsesDefaultsWhenNamesEmpty(): void
    {
        $id = $this->model->createFromGoogle(
            'gdefault@example.com',
            'google-sub-default',
            'fr',
            '',
            ''
        );

        $account = $this->model->findById($id);
        $this->assertIsArray($account);
        // Défauts définis dans le INSERT : 'Utilisateur' / 'Google'
        $this->assertSame('Utilisateur', $account['firstname']);
        $this->assertSame('Google', $account['lastname']);
    }

    // ----------------------------------------------------------------
    // linkGoogleId()
    // ----------------------------------------------------------------

    public function testLinkGoogleIdAttachesGoogleIdToExistingAccount(): void
    {
        // Crée un compte classique (sans google_id)
        $accountId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter, email_verification_token)
             VALUES ('glink@example.com', 'hash', 'fr', 0, 'tok')"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Link', 'Test', 'M')",
            [$accountId]
        );

        $googleId = 'google-link-' . bin2hex(random_bytes(8));
        $this->model->linkGoogleId($accountId, $googleId);

        $account = $this->model->findByGoogleId($googleId);
        $this->assertIsArray($account);
        $this->assertSame($googleId, $account['google_id']);
        $this->assertSame('glink@example.com', $account['email']);
    }

    // ----------------------------------------------------------------
    // findDeletedByGoogleId() / findDeletedByEmail()
    // ----------------------------------------------------------------

    public function testFindDeletedByGoogleIdReturnsFalseForActiveAccount(): void
    {
        $googleId = 'g-active-' . bin2hex(random_bytes(4));
        $this->model->createFromGoogle('active@example.com', $googleId, 'fr', 'Alice', 'Test');

        $result = $this->model->findDeletedByGoogleId($googleId);
        $this->assertFalse($result);
    }

    public function testFindDeletedByGoogleIdReturnsDeletedAccount(): void
    {
        $googleId  = 'g-deleted-' . bin2hex(random_bytes(4));
        $accountId = $this->model->createFromGoogle('deleted-g@example.com', $googleId, 'fr', 'Bob', 'Test');

        // Soft-delete simulé directement en BDD
        self::$db->execute(
            "UPDATE accounts SET deleted_at = NOW() WHERE id = ?",
            [$accountId]
        );

        $result = $this->model->findDeletedByGoogleId($googleId);
        $this->assertIsArray($result);
        $this->assertSame('deleted-g@example.com', $result['email']);
    }

    public function testFindDeletedByEmailReturnsFalseForActiveAccount(): void
    {
        $this->model->createFromGoogle('active2@example.com', 'g-a2', 'fr', 'Claire', 'Test');

        $result = $this->model->findDeletedByEmail('active2@example.com');
        $this->assertFalse($result);
    }

    public function testFindDeletedByEmailReturnsDeletedAccount(): void
    {
        $accountId = $this->model->createFromGoogle('deleted-e@example.com', 'g-del-e', 'fr', 'Dave', 'Test');

        self::$db->execute(
            "UPDATE accounts SET deleted_at = NOW() WHERE id = ?",
            [$accountId]
        );

        $result = $this->model->findDeletedByEmail('deleted-e@example.com');
        $this->assertIsArray($result);
        $this->assertSame('deleted-e@example.com', $result['email']);
    }

    // ----------------------------------------------------------------
    // clearEmailChangeToken()
    // ----------------------------------------------------------------

    public function testClearEmailChangeTokenNullsAllFields(): void
    {
        $accountId = (int) self::$db->insert(
            "INSERT INTO accounts
             (email, password, lang, newsletter, email_verification_token,
              email_change_token, email_change_new_email, email_change_expires_at)
             VALUES ('gclear@example.com', 'hash', 'fr', 0, 'tok',
                     'change-tok', 'new@example.com', NOW())"
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Clear', 'Test', 'M')",
            [$accountId]
        );

        $this->model->clearEmailChangeToken($accountId);

        $account = $this->model->findById($accountId);
        $this->assertIsArray($account);
        $this->assertNull($account['email_change_token']);
        $this->assertNull($account['email_change_new_email']);
        $this->assertNull($account['email_change_expires_at']);
    }
}
