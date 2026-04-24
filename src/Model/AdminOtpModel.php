<?php

declare(strict_types=1);

namespace Model;

use Core\Database;

/**
 * Gestion des OTP admin à usage unique en base de données.
 *
 * Chaque OTP est lié à un admin (admin_id) et un contexte (purpose).
 * Un seul OTP actif par couple (admin_id, purpose) — contrainte UNIQUE.
 * Le code brut n'est jamais stocké : seul son SHA-256 est persisté.
 */
class AdminOtpModel
{
    private const TABLE = 'admin_otps';

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crée ou remplace un OTP pour un admin et un contexte donnés.
     *
     * @param int         $adminId   Identifiant du compte admin
     * @param string      $purpose   Contexte (ex : 'ff_batch')
     * @param string      $otpHash   SHA-256 du code brut
     * @param string|null $payload   JSON optionnel (changements en attente)
     * @param int         $ttlSecs   Durée de validité en secondes
     * @return void
     */
    public function create(int $adminId, string $purpose, string $otpHash, ?string $payload, int $ttlSecs): void
    {
        $this->db->execute(
            "INSERT INTO `" . self::TABLE . "`
                (admin_id, purpose, otp_hash, payload, attempts, expires_at)
             VALUES (?, ?, ?, ?, 0, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
                otp_hash   = VALUES(otp_hash),
                payload    = VALUES(payload),
                attempts   = 0,
                expires_at = VALUES(expires_at),
                created_at = NOW()",
            [$adminId, $purpose, $otpHash, $payload, $ttlSecs]
        );
    }

    /**
     * Récupère un OTP non expiré pour un admin et un contexte.
     *
     * @param int    $adminId
     * @param string $purpose
     * @return array<string, mixed>|null  Ligne ou null si absente/expirée
     */
    public function find(int $adminId, string $purpose): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM `" . self::TABLE . "`
             WHERE admin_id = ? AND purpose = ? AND expires_at > NOW()",
            [$adminId, $purpose]
        );

        return $row ?: null;
    }

    /**
     * Incrémente le compteur de tentatives échouées.
     *
     * @param int $id Identifiant de la ligne admin_otps
     * @return void
     */
    public function incrementAttempts(int $id): void
    {
        $this->db->execute(
            "UPDATE `" . self::TABLE . "` SET attempts = attempts + 1 WHERE id = ?",
            [$id]
        );
    }

    /**
     * Supprime un OTP (après succès ou dépassement de tentatives).
     *
     * @param int    $adminId
     * @param string $purpose
     * @return void
     */
    public function delete(int $adminId, string $purpose): void
    {
        $this->db->execute(
            "DELETE FROM `" . self::TABLE . "` WHERE admin_id = ? AND purpose = ?",
            [$adminId, $purpose]
        );
    }

    /**
     * Purge les OTP expirés (maintenance, appelable depuis un cron).
     *
     * @return int Nombre de lignes supprimées
     */
    public function purgeExpired(): int
    {
        return $this->db->execute(
            "DELETE FROM `" . self::TABLE . "` WHERE expires_at <= NOW()",
            []
        );
    }
}
