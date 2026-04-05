<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

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
                "SELECT id FROM {$this->table}
                 WHERE source_address_id = ?
                   AND firstname = ? AND lastname = ?
                   AND street = ? AND city = ? AND zip = ? AND country = ?
                 ORDER BY id DESC LIMIT 1",
                [$sourceAddressId, $firstname, $lastname, $street, $city, $zip, $country]
            );
            if ($existing !== false) {
                return (int) $existing['id'];
            }
        }

        $id = $this->db->insert(
            "INSERT INTO {$this->table}
             (source_address_id, civility, firstname, lastname, street, city, zip, country, phone)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$sourceAddressId, $civility, $firstname, $lastname, $street, $city, $zip, $country, $phone]
        );
        return (int) $id;
    }
}
