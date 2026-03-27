<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class ConnectionModel extends Model
{
    protected string $table = 'connections';

    public function create( // NOSONAR — params nécessaires pour le tracking appareil, DTO prévu avec feat/account
        int $userId,
        string $token,
        ?string $deviceToken,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $deviceName,
        string $authMethod,
        int $expiry
    ): void {
        $this->db->insert(
            "INSERT INTO {$this->table}
             (user_id, token, device_token, ip_address, user_agent, device_name, auth_method, status, expired_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)",
            [
                $userId,
                $token,
                $deviceToken,
                $ipAddress,
                $userAgent !== null ? substr($userAgent, 0, 65535) : null,
                $deviceName,
                $authMethod,
                date('Y-m-d H:i:s', time() + $expiry),
            ]
        );
    }

    public function revoke(string $token): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET status = 'revoked' WHERE token = ?",
            [$token]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActiveForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT id, token, device_name, ip_address, auth_method, created_at, expired_at
             FROM {$this->table}
             WHERE user_id = ? AND status = 'active' AND expired_at > NOW()
             ORDER BY created_at DESC",
            [$userId]
        );
    }

    public function getTokenById(int $id, int $userId): ?string
    {
        $row = $this->db->fetchOne(
            "SELECT token FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        return $row ? (string) $row['token'] : null;
    }

    public function revokeById(int $id, int $userId): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET status = 'revoked' WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }
}
