<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion des tokens de réinitialisation de mot de passe (TTL 1h).
 */
class PasswordResetModel extends Model
{
    protected string $table = 'password_reset';

    /**
     * Crée ou remplace un token de réinitialisation pour un utilisateur.
     *
     * @param int    $userId Identifiant du compte
     * @param string $token  Token brut de réinitialisation
     * @return void
     */
    public function create(int $userId, string $token): void
    {
        $this->db->execute("DELETE FROM {$this->table} WHERE user_id = ?", [$userId]);
        $this->db->insert(
            "INSERT INTO {$this->table} (user_id, token, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)",
            [$userId, $token]
        );
    }

    /**
     * Trouve un token de réinitialisation valide et non expiré.
     *
     * @param string $token Token brut de réinitialisation
     * @return array<string, mixed>|false
     */
    public function findByToken(string $token): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE token = ? AND expires_at > NOW()",
            [$token]
        );
    }

    /**
     * Supprime le token de réinitialisation d'un utilisateur.
     *
     * @param int $userId Identifiant du compte
     * @return void
     */
    public function deleteByUserId(int $userId): void
    {
        $this->db->execute("DELETE FROM {$this->table} WHERE user_id = ?", [$userId]);
    }
}
