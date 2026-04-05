<?php

declare(strict_types=1);

namespace Model;

use Core\Database;

/**
 * Gestion des intentions de paiement CA Up2pay.
 *
 * Stocke le snapshot checkout (panier, adresses, montant…) avant la redirection
 * vers CA e-Transactions. Permet à l'IPN server-to-server de retrouver les données
 * sans accès à la session PHP du navigateur client.
 * TTL : 1 heure. Purgé après création de commande ou à expiration.
 */
class PaymentIntentModel
{
    private Database $db;

    /**
     * Initialise la connexion BDD.
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Persiste un snapshot de paiement en BDD.
     *
     * @param string               $reference Référence commande (WEB-CB-XXXX-YYYY)
     * @param int                  $userId    Identifiant du client
     * @param array<string, mixed> $snapshot  Données checkout complètes
     * @return void
     */
    public function save(string $reference, int $userId, array $snapshot): void
    {
        $this->db->execute(
            'INSERT INTO `payment_intents` (`reference`, `user_id`, `snapshot`, `expires_at`)
             VALUES (:ref, :uid, :snap, DATE_ADD(NOW(), INTERVAL 1 HOUR))
             ON DUPLICATE KEY UPDATE `snapshot` = VALUES(`snapshot`), `expires_at` = VALUES(`expires_at`)',
            [
                ':ref'  => $reference,
                ':uid'  => $userId,
                ':snap' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            ]
        );
    }

    /**
     * Récupère un snapshot non expiré par référence.
     *
     * @param string $reference Référence commande
     * @return array<string, mixed>|null Snapshot décodé ou null si absent/expiré
     */
    public function findByReference(string $reference): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT `snapshot` FROM `payment_intents`
             WHERE `reference` = :ref AND `expires_at` > NOW()
             LIMIT 1',
            [':ref' => $reference]
        );

        if ($row === false || !isset($row['snapshot'])) {
            return null;
        }

        $decoded = json_decode((string) $row['snapshot'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Supprime un intent après utilisation.
     *
     * @param string $reference Référence commande
     * @return void
     */
    public function delete(string $reference): void
    {
        $this->db->execute(
            'DELETE FROM `payment_intents` WHERE `reference` = :ref',
            [':ref' => $reference]
        );
    }

    /**
     * Purge les intents expirés (appelé de façon opportuniste à chaque IPN).
     *
     * @return void
     */
    public function purgeExpired(): void
    {
        $this->db->execute('DELETE FROM `payment_intents` WHERE `expires_at` <= NOW()');
    }
}
