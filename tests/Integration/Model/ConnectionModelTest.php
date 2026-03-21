<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Model\ConnectionModel;
use Tests\Integration\IntegrationTestCase;

class ConnectionModelTest extends IntegrationTestCase
{
    private ConnectionModel $model;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new ConnectionModel();

        $accountModel = new AccountModel();
        $this->userId = (int)$accountModel->create(
            'Conn',
            'User',
            'conn@example.com',
            password_hash('pass', PASSWORD_BCRYPT),
            'M',
            null,
            'fr',
            0,
            bin2hex(random_bytes(16))
        );
    }

    public function testCreateConnection(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, 'Mozilla/5.0', 3600);

        $row = self::$db->fetchOne(
            "SELECT * FROM connections WHERE token = ?",
            [$token]
        );

        $this->assertIsArray($row);
        $this->assertSame('active', $row['status']);
        $this->assertSame($this->userId, (int)$row['user_id']);
    }

    public function testRevokeConnection(): void
    {
        $token = bin2hex(random_bytes(32));
        $this->model->create($this->userId, $token, 'Mozilla/5.0', 3600);
        $this->model->revoke($token);

        $row = self::$db->fetchOne(
            "SELECT * FROM connections WHERE token = ?",
            [$token]
        );

        $this->assertIsArray($row);
        $this->assertSame('revoked', $row['status']);
    }

    public function testRevokeNonExistentTokenDoesNotFail(): void
    {
        $this->expectNotToPerformAssertions();
        $this->model->revoke('nonexistent-token');
    }

    public function testMultipleConnectionsForSameUser(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->model->create($this->userId, $token1, 'Chrome', 3600);
        $this->model->create($this->userId, $token2, 'Firefox', 3600);

        $rows = self::$db->fetchAll(
            "SELECT * FROM connections WHERE user_id = ?",
            [$this->userId]
        );

        $this->assertCount(2, $rows);
    }
}
