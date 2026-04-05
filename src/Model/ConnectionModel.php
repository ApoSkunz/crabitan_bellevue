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
            "SELECT id, token, device_token, device_name, ip_address, auth_method, created_at, expired_at
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

    public function getLatestByDeviceToken(int $userId, string $deviceToken): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT device_token, device_name FROM {$this->table}
             WHERE user_id = ? AND device_token = ?
             ORDER BY created_at DESC LIMIT 1",
            [$userId, $deviceToken]
        );
        return $row ?: null;
    }

    /**
     * Retourne le device_token associé à un token de session JWT.
     * Utilisé pour révoquer le trusted device de la session à l'origine d'une demande.
     *
     * @param  string $token Token JWT de session
     * @return string|null   device_token ou null si introuvable
     */
    public function getDeviceTokenByToken(string $token): string|null
    {
        $row = $this->db->fetchOne(
            "SELECT device_token FROM {$this->table} WHERE token = ?",
            [$token]
        );
        return $row ? ($row['device_token'] ?? null) : null;
    }

    public function revokeById(int $id, int $userId): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET status = 'revoked' WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }

    /**
     * Vérifie si ce device_token a déjà été utilisé par cet utilisateur (toute session, tout statut).
     */
    public function isKnownDevice(int $userId, string $deviceToken): bool
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM {$this->table} WHERE user_id = ? AND device_token = ? LIMIT 1",
            [$userId, $deviceToken]
        );
        return (bool) $row;
    }

    public function hasAnyConnection(int $userId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM {$this->table} WHERE user_id = ? LIMIT 1",
            [$userId]
        );
        return (bool) $row;
    }

    /**
     * Retourne les connexions récentes (N derniers jours) pour l'export RGPD.
     * Le token JWT est volontairement exclu (minimisation des données, Art. 5 RGPD).
     *
     * @param int $userId Identifiant du compte
     * @param int $days   Fenêtre temporelle en jours (défaut : 90)
     * @return array<int, array<string, mixed>>
     */
    public function getRecentForUser(int $userId, int $days = 90): array
    {
        return $this->db->fetchAll(
            "SELECT device_name, ip_address, user_agent, auth_method, status, created_at, expired_at
             FROM {$this->table}
             WHERE user_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY created_at DESC",
            [$userId, $days]
        );
    }

    /**
     * Purge les connexions révoquées ou expirées.
     *
     * @return int Nombre de lignes supprimées
     */
    public function purgeExpired(): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE status = 'revoked' OR expired_at < NOW()"
        );
    }
}
