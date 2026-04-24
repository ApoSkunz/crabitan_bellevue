<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion des connexions utilisateur (sessions actives, devices, historique).
 */
class ConnectionModel extends Model
{
    protected string $table = 'connections';

    private const DATE_FORMAT_MYSQL = 'Y-m-d H:i:s';

    /**
     * Enregistre une nouvelle connexion en base.
     *
     * @param int         $userId      Identifiant du compte
     * @param string      $token       JWT de session
     * @param string|null $deviceToken Token d'appareil
     * @param string|null $ipAddress   Adresse IP
     * @param string|null $userAgent   User-Agent
     * @param string|null $deviceName  Nom de l'appareil
     * @param string      $authMethod  Méthode d'authentification ('password', 'google'…)
     * @param int         $expiry      Durée de vie en secondes
     * @return void
     */
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
                date(self::DATE_FORMAT_MYSQL, time() + $expiry),
            ]
        );
    }

    /**
     * Révoque une connexion par son token JWT.
     *
     * @param string $token JWT de session à révoquer
     * @return void
     */
    public function revoke(string $token): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET status = 'revoked' WHERE token = ?",
            [$token]
        );
    }

    /**
     * Retourne les connexions actives et non expirées d'un utilisateur.
     *
     * @param int $userId Identifiant du compte
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

    /**
     * Retourne le token JWT d'une connexion par son identifiant et l'utilisateur propriétaire.
     *
     * @param int $id     Identifiant de la connexion
     * @param int $userId Identifiant du compte propriétaire
     * @return string|null Token JWT ou null si introuvable
     */
    public function getTokenById(int $id, int $userId): ?string
    {
        $row = $this->db->fetchOne(
            "SELECT token FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        return $row ? (string) $row['token'] : null;
    }

    /**
     * Retourne la dernière connexion connue pour un user+device (nom de l'appareil inclus).
     *
     * @param int    $userId      Identifiant du compte
     * @param string $deviceToken Token de l'appareil
     * @return array<string, mixed>|null
     */
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

    /**
     * Révoque une connexion par son identifiant, limité au compte propriétaire.
     *
     * @param int $id     Identifiant de la connexion
     * @param int $userId Identifiant du compte propriétaire
     * @return void
     */
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

    /**
     * Indique si un utilisateur a au moins une connexion enregistrée (tout statut).
     *
     * @param int $userId Identifiant du compte
     * @return bool
     */
    public function hasAnyConnection(int $userId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM {$this->table} WHERE user_id = ? LIMIT 1",
            [$userId]
        );
        return (bool) $row;
    }

    /**
     * Prolonge expired_at d'une session active et non expirée.
     *
     * Ne touche que les lignes status = 'active' et expired_at > NOW()
     * pour éviter de ressusciter une session déjà révoquée ou expirée.
     *
     * @param string $token  JWT de session à prolonger
     * @param int    $expiry Nouvelle durée en secondes à partir de maintenant
     * @return bool          true si une ligne a été mise à jour
     */
    public function refreshExpiry(string $token, int $expiry): bool
    {
        $affected = $this->db->execute(
            "UPDATE {$this->table}
             SET expired_at = ?
             WHERE token = ? AND status = 'active' AND expired_at > NOW()",
            [date(self::DATE_FORMAT_MYSQL, time() + $expiry), $token]
        );
        return $affected > 0;
    }

    /**
     * Remplace le token JWT et prolonge expired_at atomiquement (sliding refresh).
     *
     * Utilisé lors du refresh standard : le JWT est régénéré avec un nouveau claim exp,
     * l'ancien token est remplacé en base pour maintenir la cohérence session ↔ cookie.
     *
     * @param string $oldToken JWT expirant
     * @param string $newToken Nouveau JWT généré
     * @param int    $expiry   Durée en secondes à partir de maintenant
     * @return bool            true si la ligne a été mise à jour
     */
    public function refreshToken(string $oldToken, string $newToken, int $expiry): bool
    {
        $affected = $this->db->execute(
            "UPDATE {$this->table}
             SET token = ?, expired_at = ?
             WHERE token = ? AND status = 'active' AND expired_at > NOW()",
            [$newToken, date(self::DATE_FORMAT_MYSQL, time() + $expiry), $oldToken]
        );
        return $affected > 0;
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
     * Vérifie qu'un token JWT correspond à une connexion active et non expirée.
     *
     * Utilisé pour valider la révocation côté serveur : un JWT structurellement
     * valide peut avoir été révoqué via revokeById() ou revokeAllForUser().
     *
     * @param string $token JWT de session
     * @return bool true si la connexion est active et non expirée
     */
    public function isActiveByToken(string $token): bool
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM {$this->table}
             WHERE token = ? AND status = 'active' AND expired_at > NOW()
             LIMIT 1",
            [$token]
        );
        return (bool) $row;
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
