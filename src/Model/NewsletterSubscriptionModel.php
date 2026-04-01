<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Accès aux données des abonnements newsletter (double opt-in RGPD Art. 7).
 *
 * Gère le cycle de vie d'un abonnement :
 *   inscription (pending) → confirmation (confirmed) → désabonnement.
 */
class NewsletterSubscriptionModel extends Model
{
    protected string $table = 'newsletter_subscriptions';

    /**
     * Recherche un abonnement en état pending par le hash SHA-256 du token.
     *
     * Retourne null si le token est inconnu ou si l'abonnement est déjà confirmé.
     * La vérification d'expiration est laissée à la couche service (hash_equals + TTL).
     *
     * @param string $tokenHash Hash SHA-256 du token brut (64 chars hex)
     * @return array<string, mixed>|null
     */
    public function findPendingByTokenHash(string $tokenHash): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, email, lang, newsletter_token_hash,
                    newsletter_token_expires_at, newsletter_confirmed
             FROM {$this->table}
             WHERE newsletter_token_hash = ?
               AND newsletter_confirmed = 0",
            [$tokenHash]
        );

        return $row !== false ? $row : null;
    }

    /**
     * Marque un abonnement comme confirmé et enregistre la preuve RGPD.
     *
     * @param string $tokenHash Hash SHA-256 du token validé
     * @param string $ip        Adresse IP lors du clic de confirmation
     * @return void
     */
    public function confirmByTokenHash(string $tokenHash, string $ip): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET newsletter_confirmed      = 1,
                 newsletter_consent_date   = NOW(),
                 newsletter_consent_ip     = ?,
                 newsletter_token_hash     = NULL,
                 newsletter_token_expires_at = NULL
             WHERE newsletter_token_hash = ?
               AND newsletter_confirmed = 0",
            [$ip, $tokenHash]
        );
    }

    /**
     * Insère ou met à jour un abonnement en état pending.
     *
     * Si l'email existe déjà en état pending, renouvelle le token et incrémente
     * le compteur d'envois. Si l'email est déjà confirmé, une exception
     * \RuntimeException('already_confirmed') est attendue côté service.
     *
     * @param string $email     Adresse email de l'abonné
     * @param string $tokenHash Hash SHA-256 du token brut
     * @param string $expiresAt Datetime d'expiration (NOW() + 48h)
     * @param string $ip        Adresse IP lors de la soumission du formulaire
     * @param string $lang      Langue ('fr' ou 'en')
     * @return void
     */
    public function upsertPending(
        string $email,
        string $tokenHash,
        string $expiresAt,
        string $ip,
        string $lang
    ): void {
        $this->db->execute(
            "INSERT INTO {$this->table}
             (email, newsletter_token_hash, newsletter_token_expires_at,
              newsletter_confirmed, consent_ip, lang, attempts_24h, last_attempt_at)
             VALUES (?, ?, ?, 0, ?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE
               newsletter_token_hash       = VALUES(newsletter_token_hash),
               newsletter_token_expires_at = VALUES(newsletter_token_expires_at),
               consent_ip                  = VALUES(consent_ip),
               lang                        = VALUES(lang),
               attempts_24h               = attempts_24h + 1,
               last_attempt_at            = NOW()",
            [$email, $tokenHash, $expiresAt, $ip, $lang]
        );
    }

    /**
     * Trouve un abonnement par email (confirmé ou pending).
     *
     * @param string $email Adresse email
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, email, lang, newsletter_confirmed,
                    attempts_24h, last_attempt_at
             FROM {$this->table}
             WHERE email = ?",
            [$email]
        );

        return $row !== false ? $row : null;
    }

    /**
     * Compte le nombre de tentatives d'envoi de confirmation dans les 24 dernières heures.
     *
     * @param string $email Adresse email
     * @return int Nombre de tentatives récentes
     */
    public function countRecentAttempts(string $email): int
    {
        $row = $this->db->fetchOne(
            "SELECT attempts_24h, last_attempt_at
             FROM {$this->table}
             WHERE email = ?",
            [$email]
        );

        if ($row === false || $row === null) {
            return 0;
        }

        // Réinitialiser le compteur si la dernière tentative remonte à plus de 24h
        if ($row['last_attempt_at'] !== null) {
            $lastAttempt = new \DateTimeImmutable($row['last_attempt_at']);
            $cutoff      = new \DateTimeImmutable('-24 hours');
            if ($lastAttempt < $cutoff) {
                $this->db->execute(
                    "UPDATE {$this->table}
                     SET attempts_24h = 0, last_attempt_at = NULL
                     WHERE email = ?",
                    [$email]
                );
                return 0;
            }
        }

        return (int) $row['attempts_24h'];
    }

    /**
     * Retourne la liste des abonnés confirmés pour l'envoi de campagnes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllConfirmed(): array
    {
        return $this->db->fetchAll(
            "SELECT id, email, lang
             FROM {$this->table}
             WHERE newsletter_confirmed = 1"
        );
    }

    /**
     * Supprime les abonnements pending dont le token est expiré depuis plus de 48h.
     *
     * À invoquer via un job de maintenance (CRON).
     *
     * @return int Nombre de lignes supprimées
     */
    public function purgeExpiredPending(): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table}
             WHERE newsletter_confirmed = 0
               AND newsletter_token_expires_at < NOW()"
        );
    }
}
