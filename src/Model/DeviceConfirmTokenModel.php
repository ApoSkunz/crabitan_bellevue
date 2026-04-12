<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion des tokens de confirmation de nouvel appareil (MFA device).
 * Utilisé pour le flux double confirmation par email avant de faire confiance à un appareil.
 */
class DeviceConfirmTokenModel extends Model
{
    protected string $table = 'device_confirm_tokens';

    /**
     * Crée (ou remplace) un token de confirmation pour un couple user+device.
     * Expiration : 15 minutes.
     *
     * @param int         $userId      Identifiant du compte
     * @param string      $deviceToken Token de l'appareil à confirmer
     * @param string|null $deviceName  Nom de l'appareil (optionnel)
     * @param string      $token       Token brut de confirmation (64 hex chars)
     * @param string      $redirectUrl URL de redirection après confirmation
     * @param string      $lang        Langue de l'email de confirmation
     * @return void
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

    /**
     * Trouve un token de confirmation valide et non expiré.
     *
     * @param string $token Token brut de confirmation
     * @return array<string, mixed>|false
     */
    public function findByToken(string $token): array|false
    {
        return $this->db->fetchOne(
            "SELECT id, user_id, device_token, device_name, token, redirect_url, lang, expires_at, confirmed_at, created_at
             FROM {$this->table} WHERE token = ? AND expires_at > NOW() LIMIT 1",
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
            "SELECT id, user_id, device_token, device_name, token, redirect_url, lang, expires_at, confirmed_at, created_at
             FROM {$this->table}
             WHERE token = ? AND confirmed_at IS NOT NULL AND expires_at > NOW() LIMIT 1",
            [$token]
        );
    }

    /**
     * Supprime un token de confirmation après usage.
     *
     * @param string $token Token brut à supprimer
     * @return void
     */
    public function deleteByToken(string $token): void
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE token = ?",
            [$token]
        );
    }

    /**
     * Purge les tokens de confirmation expirés.
     *
     * @return int Nombre de tokens supprimés
     */
    public function purgeExpired(): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE expires_at < NOW()"
        );
    }
}
