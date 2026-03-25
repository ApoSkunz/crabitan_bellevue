<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Model\PasswordResetModel;
use Tests\Integration\IntegrationTestCase;

class PasswordResetModelTest extends IntegrationTestCase
{
    private PasswordResetModel $model;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new PasswordResetModel();

        // Créer un compte de base pour les FK
        $accountModel = new AccountModel();
        $this->userId = (int)$accountModel->create(
            'individual',
            'reset@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Reset',
            'User',
            ''
        );
    }

    public function testCreateAndFindByToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token);

        $reset = $this->model->findByToken($token);
        $this->assertIsArray($reset);
        $this->assertSame($this->userId, (int)$reset['user_id']);
    }

    public function testCreateReplacesExistingToken(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->model->create($this->userId, $token1);
        $this->model->create($this->userId, $token2);

        $this->assertFalse($this->model->findByToken($token1));
        $this->assertIsArray($this->model->findByToken($token2));
    }

    public function testFindByTokenReturnsfalseIfExpired(): void
    {
        $token = bin2hex(random_bytes(32));
        self::$db->insert(
            "INSERT INTO password_reset (user_id, token, expires_at) VALUES (?, ?, ?)",
            [$this->userId, $token, date('Y-m-d H:i:s', time() - 1)]
        );

        $result = $this->model->findByToken($token);
        $this->assertFalse($result);
    }

    public function testFindByTokenReturnsfalseIfNotFound(): void
    {
        $result = $this->model->findByToken('nonexistenttoken');
        $this->assertFalse($result);
    }

    public function testDeleteByUserId(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token);
        $this->model->deleteByUserId($this->userId);

        $result = $this->model->findByToken($token);
        $this->assertFalse($result);
    }
}
