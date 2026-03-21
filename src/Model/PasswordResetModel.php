<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class PasswordResetModel extends Model
{
    protected string $table = 'password_reset';

    public function create(int $userId, string $token): void
    {
        $this->db->execute("DELETE FROM {$this->table} WHERE user_id = ?", [$userId]);
        $this->db->insert(
            "INSERT INTO {$this->table} (user_id, token, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)",
            [$userId, $token]
        );
    }

    public function findByToken(string $token): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE token = ? AND expires_at > NOW()",
            [$token]
        );
    }

    public function deleteByUserId(int $userId): void
    {
        $this->db->execute("DELETE FROM {$this->table} WHERE user_id = ?", [$userId]);
    }
}
