<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class DeviceConfirmTokenModel extends Model
{
    protected string $table = 'device_confirm_tokens';

    /**
     * Crée (ou remplace) un token de confirmation pour un couple user+device.
     * Expiration : 15 minutes.
     */
    public function create(
        int $userId,
        string $deviceToken,
        ?string $deviceName,
        string $token,
        string $redirectUrl,
        string $lang
    ): void {
        // Supprime tout token en attente pour ce user+device (idempotent)
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE user_id = ? AND device_token = ?",
            [$userId, $deviceToken]
        );
        $this->db->insert(
            "INSERT INTO {$this->table}
             (user_id, device_token, device_name, token, redirect_url, lang, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))",
            [$userId, $deviceToken, $deviceName, $token, $redirectUrl, $lang]
        );
    }

    public function findByToken(string $token): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE token = ? AND expires_at > NOW() LIMIT 1",
            [$token]
        );
    }

    /**
     * Marque le token comme confirmé (lien email cliqué).
     * Le JWT sera émis par l'endpoint de polling sur la page d'attente.
     */
    public function confirm(string $token): bool
    {
        $rows = $this->db->execute(
            "UPDATE {$this->table}
             SET confirmed_at = NOW()
             WHERE token = ? AND expires_at > NOW() AND confirmed_at IS NULL",
            [$token]
        );
        return $rows > 0;
    }

    /**
     * Retourne le token uniquement s'il a été confirmé et n'est pas expiré.
     */
    public function findConfirmedByToken(string $token): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table}
             WHERE token = ? AND confirmed_at IS NOT NULL AND expires_at > NOW() LIMIT 1",
            [$token]
        );
    }

    public function deleteByToken(string $token): void
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE token = ?",
            [$token]
        );
    }

    public function purgeExpired(): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE expires_at < NOW()"
        );
    }
}
