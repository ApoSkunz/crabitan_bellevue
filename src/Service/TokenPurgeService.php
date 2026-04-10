<?php

declare(strict_types=1);

namespace Service;

use Core\Database;

/**
 * Service de nettoyage des tokens expirés.
 *
 * Purge quatre zones de la base :
 *   - password_reset       : tokens expirés depuis > GRACE_SHORT jours (défaut 7)
 *   - device_confirm_tokens: idem
 *   - connections          : sessions terminées depuis > GRACE_LONG jours (défaut 30)
 *   - accounts             : token e-mail de vérification expiré et non utilisé
 *
 * Tolérance (période de grâce) : évite de supprimer un token qu'un process
 * concurrent lirait encore dans la même fenêtre de temps.
 *
 * @see bin/clean_expired_tokens.php
 */
class TokenPurgeService
{
    /** Jours de grâce pour les tokens courts (reset, device, email). */
    private const GRACE_SHORT = 7;

    /** Jours de grâce pour les sessions inactives. */
    private const GRACE_LONG = 30;

    private Database $db;

    /**
     * @param Database $db Instance de base de données
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Lance la purge des quatre zones et retourne un résumé.
     *
     * @return array{
     *   password_reset: int,
     *   device_confirm_tokens: int,
     *   connections: int,
     *   email_verification: int
     * }
     */
    public function purgeAll(): array
    {
        return [
            'password_reset'        => $this->purgePasswordReset(),
            'device_confirm_tokens' => $this->purgeDeviceConfirmTokens(),
            'connections'           => $this->purgeConnections(),
            'email_verification'    => $this->purgeEmailVerificationTokens(),
        ];
    }

    /**
     * Supprime les tokens de réinitialisation de mot de passe expirés (RGPD Art. 5 §1e).
     * Période de grâce : GRACE_SHORT jours après expiration.
     *
     * @return int Nombre de lignes supprimées
     */
    public function purgePasswordReset(): int
    {
        return $this->db->execute(
            'DELETE FROM `password_reset`
             WHERE `expires_at` < NOW() - INTERVAL :days DAY',
            [':days' => self::GRACE_SHORT]
        );
    }

    /**
     * Supprime les tokens de confirmation d'appareil expirés.
     * Période de grâce : GRACE_SHORT jours après expiration.
     *
     * @return int Nombre de lignes supprimées
     */
    public function purgeDeviceConfirmTokens(): int
    {
        return $this->db->execute(
            'DELETE FROM `device_confirm_tokens`
             WHERE `expires_at` < NOW() - INTERVAL :days DAY',
            [':days' => self::GRACE_SHORT]
        );
    }

    /**
     * Supprime les sessions (connections) inactives expirées depuis > GRACE_LONG jours.
     * Les sessions au statut « active » sont exclues quelle que soit la date.
     *
     * @return int Nombre de lignes supprimées
     */
    public function purgeConnections(): int
    {
        return $this->db->execute(
            'DELETE FROM `connections`
             WHERE `status` != :active
               AND `expired_at` < NOW() - INTERVAL :days DAY',
            [':active' => 'active', ':days' => self::GRACE_LONG]
        );
    }

    /**
     * Anonymise les tokens de vérification d'email expirés sur les comptes non vérifiés.
     * Ne touche pas aux comptes dont `email_verified_at` est déjà renseigné.
     * Période de grâce : GRACE_SHORT jours après expiration.
     *
     * @return int Nombre de comptes mis à jour
     */
    public function purgeEmailVerificationTokens(): int
    {
        return $this->db->execute(
            'UPDATE `accounts`
             SET `email_verification_token`          = NULL,
                 `email_verification_token_expires_at` = NULL
             WHERE `email_verified_at` IS NULL
               AND `email_verification_token_expires_at` < NOW() - INTERVAL :days DAY',
            [':days' => self::GRACE_SHORT]
        );
    }
}
