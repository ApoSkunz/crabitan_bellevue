<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class ConnectionModel extends Model
{
    protected string $table = 'connections';

    public function create(int $userId, string $token, string $clientMachine, int $expiry): void
    {
        $this->db->insert(
            "INSERT INTO {$this->table} (user_id, token, client_machine, status, expired_at)
             VALUES (?, ?, ?, 'active', ?)",
            [$userId, $token, $clientMachine, date('Y-m-d H:i:s', time() + $expiry)]
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
