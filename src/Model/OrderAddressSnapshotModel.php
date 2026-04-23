<?php

declare(strict_types=1);

namespace Model;

use Core\Model;
use Service\EncryptionService;

/**
 * Snapshot immuable d'une adresse associée à une commande.
 *
 * Indépendant des tables `accounts` et `addresses` : la suppression d'un compte
 * ou d'une adresse n'affecte jamais les données de commande (RGPD / L123-22 C.com.).
 *
 * Déduplication : si une commande réutilise la même adresse sans modification,
 * le snapshot existant est retourné plutôt qu'en créer un doublon.
 */
class OrderAddressSnapshotModel extends Model
{
    protected string $table = 'order_address_snapshots';

    private EncryptionService $encryption;

    /**
     * @param EncryptionService|null $encryption Service de chiffrement (null = construit depuis l'environnement)
     */
    public function __construct(?EncryptionService $encryption = null)
    {
        parent::__construct();
        $this->encryption = $encryption ?? EncryptionService::fromEnv();
    }

    /**
     * Retourne un snapshot existant ou en crée un nouveau (find-or-create).
     *
     * Si `$sourceAddressId` est fourni et qu'un snapshot identique existe déjà
     * (même source_address_id ET même contenu), l'ID existant est retourné.
     * Si l'adresse a été modifiée depuis le dernier snapshot, un nouveau est créé.
     *
     * Accepte les clés issues de `addresses` (`zip_code`) ou snapshot (`zip`).
     *
     * @param array<string, mixed> $address         Données d'adresse (civility, firstname, lastname, street, city, zip_code|zip, country, phone)
     * @param int|null             $sourceAddressId ID de l'adresse source dans la table `addresses` (null si inconnue)
     * @return int ID du snapshot (existant ou nouveau)
     */
    public function createFromAddress(array $address, ?int $sourceAddressId = null): int
    {
        $civility  = $address['civility']  ?? null;
        $firstname = $address['firstname'] ?? '';
        $lastname  = $address['lastname']  ?? '';
        $street    = $address['street']    ?? '';
        $city      = $address['city']      ?? '';
        $zip       = $address['zip_code']  ?? ($address['zip'] ?? '');
        $country   = $address['country']   ?? '';
        $phone     = $address['phone']     ?? '';

        if ($sourceAddressId !== null) {
            $existing = $this->db->fetchOne(
                "SELECT id, street FROM {$this->table}
                 WHERE source_address_id = ?
                   AND firstname = ? AND lastname = ?
                   AND city = ? AND zip = ? AND country = ?
                 ORDER BY id DESC LIMIT 1",
                [$sourceAddressId, $firstname, $lastname, $city, $zip, $country]
            );
            if (is_array($existing) && isset($existing['street'], $existing['id'])) {
                $rawStreet    = (string) $existing['street'];
                $storedStreet = $this->encryption->decrypt($rawStreet) ?? $rawStreet;
                if ($storedStreet === $street) {
                    return (int) $existing['id'];
                }
            }
        }

        $id = $this->db->insert(
            "INSERT INTO {$this->table}
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $sourceAddressId, $civility, $firstname, $lastname,
                $this->encryption->encrypt($street),
                $city, $zip, $country,
                $this->encryption->encrypt($phone),
            ]
        );
        return (int) $id;
    }

    /**
     * Retourne les snapshots d'adresse liés aux commandes d'un utilisateur.
     *
     * Utilisé pour l'export RGPD Art. 20 — portabilité des données personnelles.
     * Exclut les snapshots pseudonymisés (données effacées après 10 ans, L123-22 C.com.).
     *
     * @param int $userId Identifiant du compte
     * @return array<int, array<string, mixed>>
     */
    public function getForUserExport(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT oas.civility, oas.firstname, oas.lastname, oas.street,
                    oas.city, oas.zip, oas.country, oas.phone,
                    o.order_reference, o.ordered_at, 'facturation' AS address_type
             FROM {$this->table} oas
             JOIN orders o ON o.id_billing_snapshot = oas.id
             WHERE o.user_id = ? AND oas.pseudonymized_at IS NULL

             UNION

             SELECT oas.civility, oas.firstname, oas.lastname, oas.street,
                    oas.city, oas.zip, oas.country, oas.phone,
                    o.order_reference, o.ordered_at, 'livraison' AS address_type
             FROM {$this->table} oas
             JOIN orders o ON o.id_delivery_snapshot = oas.id
             WHERE o.user_id = ? AND oas.pseudonymized_at IS NULL

             ORDER BY ordered_at DESC",
            [$userId, $userId]
        );

        return array_map(function (array $row): array {
            if (isset($row['street']) && is_string($row['street'])) {
                $row['street'] = $this->encryption->decrypt($row['street']) ?? $row['street'];
            }
            if (isset($row['phone']) && is_string($row['phone'])) {
                $row['phone'] = $this->encryption->decrypt($row['phone']) ?? $row['phone'];
            }
            return $row;
        }, $rows);
    }
}
