<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Snapshot immuable d'une adresse associée à une commande.
 *
 * Indépendant des tables `accounts` et `addresses` : la suppression d'un compte
 * ou d'une adresse n'affecte jamais les données de commande (RGPD / L123-22 C.com.).
 */
class OrderAddressSnapshotModel extends Model
{
    protected string $table = 'order_address_snapshots';

    /**
     * Crée un snapshot à partir d'un tableau d'adresse et retourne l'ID inséré.
     *
     * Accepte les clés issues des tables `addresses` (`zip_code`) ou snapshot (`zip`).
     *
     * @param array<string, mixed> $address Données d'adresse (civility, firstname, lastname, street, city, zip_code|zip, country, phone)
     * @return int ID du snapshot créé
     */
    public function createFromAddress(array $address): int
    {
        $id = $this->db->insert(
            "INSERT INTO {$this->table} (civility, firstname, lastname, street, city, zip, country, phone)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $address['civility']  ?? null,
                $address['firstname'] ?? '',
                $address['lastname']  ?? '',
                $address['street']    ?? '',
                $address['city']      ?? '',
                $address['zip_code']  ?? ($address['zip'] ?? ''),
                $address['country']   ?? '',
                $address['phone']     ?? '',
            ]
        );
        return (int) $id;
    }
}
