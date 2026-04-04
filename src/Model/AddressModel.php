<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class AddressModel extends Model
{
    protected string $table = 'addresses';

    private const VALID_TYPES = ['billing', 'delivery'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE user_id = ? AND saved = 1 ORDER BY type ASC, id ASC",
            [$userId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdForUser(int $id, int $userId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        return $row ?: null;
    }

    /**
     * Crée une adresse et retourne son identifiant en base de données.
     *
     * @param int    $userId    Identifiant de l'utilisateur
     * @param string $type      'billing' ou 'delivery'
     * @param string $firstname Prénom
     * @param string $lastname  Nom
     * @param string $civility  'M', 'F' ou 'other'
     * @param string $street    Rue
     * @param string $city      Ville
     * @param string $zipCode   Code postal
     * @param string $country   Pays
     * @param string $phone     Téléphone
     * @return int Identifiant de l'adresse créée, 0 si type invalide
     */
    public function create( // NOSONAR php:S107 — paramètres atomiques requis par le schéma BDD ; DTO prévu à l'audit architecture
        int $userId,
        string $type,
        string $firstname,
        string $lastname,
        string $civility,
        string $street,
        string $city,
        string $zipCode,
        string $country,
        string $phone
    ): int {
        if (!in_array($type, self::VALID_TYPES, true)) {
            return 0;
        }
        $id = $this->db->insert(
            "INSERT INTO {$this->table}
             (user_id, type, firstname, lastname, civility, street, city, zip_code, country, phone, saved)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [$userId, $type, $firstname, $lastname, $civility, $street, $city, $zipCode, $country, $phone]
        );
        return (int) $id;
    }

    public function update( // NOSONAR php:S107 — paramètres atomiques requis par le schéma BDD ; DTO prévu à l'audit architecture
        int $id,
        int $userId,
        string $firstname,
        string $lastname,
        string $civility,
        string $street,
        string $city,
        string $zipCode,
        string $country,
        string $phone
    ): void {
        $this->db->execute(
            "UPDATE {$this->table}
             SET firstname = ?, lastname = ?, civility = ?,
                 street = ?, city = ?, zip_code = ?, country = ?, phone = ?
             WHERE id = ? AND user_id = ?",
            [$firstname, $lastname, $civility, $street, $city, $zipCode, $country, $phone, $id, $userId]
        );
    }

    public function deleteForUser(int $id, int $userId): void
    {
        // Soft-delete : on masque l'adresse (saved=0) pour préserver les FK des commandes historiques
        $this->db->execute(
            "UPDATE {$this->table} SET saved = 0 WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
    }
}
