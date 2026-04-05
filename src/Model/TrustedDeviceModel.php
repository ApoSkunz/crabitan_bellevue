<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class TrustedDeviceModel extends Model
{
    protected string $table = 'trusted_devices';

    public function isTrusted(int $userId, string $deviceToken): bool
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM {$this->table} WHERE user_id = ? AND device_token = ? LIMIT 1",
            [$userId, $deviceToken]
        );
        return (bool) $row;
    }

    public function trust(int $userId, string $deviceToken, string $deviceName): void
    {
        $this->db->insert(
            "INSERT INTO {$this->table} (user_id, device_token, device_name)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE device_name = VALUES(device_name), last_seen = CURRENT_TIMESTAMP",
            [$userId, $deviceToken, $deviceName]
        );
    }

    public function updateLastSeen(int $userId, string $deviceToken): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET last_seen = CURRENT_TIMESTAMP WHERE user_id = ? AND device_token = ?",
            [$userId, $deviceToken]
        );
    }

    public function untrust(int $userId, string $deviceToken): void
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE user_id = ? AND device_token = ?",
            [$userId, $deviceToken]
        );
    }

    public function deleteAllForUser(int $userId): void
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT device_token, device_name, confirmed_at, last_seen
             FROM {$this->table}
             WHERE user_id = ?
             ORDER BY last_seen DESC",
            [$userId]
        );
    }

    /**
     * Retourne les appareils de confiance avec le user-agent de la dernière connexion connue.
     * Utilisé pour l'export RGPD (Art. 20) — le token de device est exclu pour minimisation.
     *
     * @param int $userId Identifiant du compte
     * @return array<int, array<string, mixed>>
     */
    public function getForExport(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT td.device_name,
                    td.confirmed_at,
                    td.last_seen,
                    (SELECT c.user_agent
                     FROM connections c
                     WHERE c.user_id = td.user_id
                       AND c.device_token = td.device_token
                     ORDER BY c.created_at DESC
                     LIMIT 1) AS user_agent
             FROM {$this->table} td
             WHERE td.user_id = ?
             ORDER BY td.last_seen DESC",
            [$userId]
        );
    }
}
