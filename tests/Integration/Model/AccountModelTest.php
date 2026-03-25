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
}
