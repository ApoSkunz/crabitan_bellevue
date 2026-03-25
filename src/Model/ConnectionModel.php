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
}
