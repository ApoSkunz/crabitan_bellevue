<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Gestion des tokens de double confirmation pour le rattachement compte ↔ Google OAuth.
 * Le token original n'est jamais stocké : seul le SHA-256 est persisté (comparaison constant-time côté lecture).
 * TTL 15 minutes.
 */
class GoogleLinkTokenModel extends Model
{
    protected string $table = 'google_link_tokens';

    /** Durée de validité d'un token en minutes. */
    public const TTL_MINUTES = 15;

    /**
     * Crée un token lié au rattachement d'un compte à un google_id.
     *
     * @param int    $accountId Identifiant du compte classique existant
     * @param string $googleId  Identifiant Google à rattacher
     * @param string $lang      Langue UX (fr|en)
     * @return string Token clair à envoyer par email
     */
    public function create(int $accountId, string $googleId, string $lang): string
    {
        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);

        $this->db->execute(
            "DELETE FROM {$this->table} WHERE account_id = ? AND consumed_at IS NULL",
            [$accountId]
        );

        $this->db->insert(
            "INSERT INTO {$this->table} (account_id, google_id, token_hash, lang, expires_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))",
            [$accountId, $googleId, $hash, $lang, self::TTL_MINUTES]
        );

        return $token;
    }

    /**
     * Recherche un token actif (non consommé, non expiré) par sa valeur claire.
     *
     * @param string $token Token clair reçu dans l'URL
     * @return array<string, mixed>|false
     */
    public function findActive(string $token): array|false
    {
        $hash = hash('sha256', $token);
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table}
             WHERE token_hash = ? AND consumed_at IS NULL AND expires_at > NOW()
             LIMIT 1",
            [$hash]
        );
    }

    /**
     * Marque un token comme consommé (usage unique).
     *
     * @param int $id Identifiant du token
     * @return void
     */
    public function consume(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET consumed_at = NOW() WHERE id = ?",
            [$id]
        );
    }
}
