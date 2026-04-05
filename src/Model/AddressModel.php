<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Gestion des adresses de facturation et de livraison d'un compte utilisateur.
 *
 * Implémente la logique de soft-delete différenciée :
 * - adresse liée à une commande (snapshot) → deleted_at = NOW()
 * - adresse jamais utilisée dans une commande → suppression physique
 */
class AddressModel extends Model
{
    protected string $table = 'addresses';

    private const VALID_TYPES = ['billing', 'delivery'];

    /**
     * Retourne les adresses sauvegardées et non supprimées de l'utilisateur.
     *
     * Les adresses soft-deleted (deleted_at IS NOT NULL) sont exclues.
     *
     * @param int $userId Identifiant de l'utilisateur
     * @return array<int, array<string, mixed>>
     */
    public function getByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table}
             WHERE user_id = ? AND saved = 1 AND deleted_at IS NULL
             ORDER BY type ASC, id ASC",
            [$userId]
        );
    }

    /**
     * Retourne une adresse active par son identifiant et son propriétaire.
     *
     * Les adresses soft-deleted (deleted_at IS NOT NULL) sont exclues :
     * elles ne doivent plus être utilisables dans de nouvelles commandes.
     *
     * @param int $id     Identifiant de l'adresse
     * @param int $userId Identifiant de l'utilisateur
     * @return array<string, mixed>|null
     */
    public function findByIdForUser(int $id, int $userId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ? AND deleted_at IS NULL",
            [$id, $userId]
        );
        return $row ?: null;
    }

    /**
     * Vérifie si une adresse est référencée dans un snapshot de commande.
     *
     * @param int $id Identifiant de l'adresse
     * @return bool   true si au moins un snapshot existe pour cette adresse
     */
    public function hasOrderSnapshot(int $id): bool
    {
        $row = $this->db->fetchOne(
            "SELECT 1 FROM order_address_snapshots WHERE source_address_id = ? LIMIT 1",
            [$id]
        );
        return $row !== false;
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

    /**
     * Met à jour les données d'une adresse appartenant à l'utilisateur.
     *
     * @param int    $id        Identifiant de l'adresse
     * @param int    $userId    Identifiant du propriétaire (contrôle d'accès)
     * @param string $firstname Prénom
     * @param string $lastname  Nom
     * @param string $civility  'M', 'F' ou 'other'
     * @param string $street    Rue
     * @param string $city      Ville
     * @param string $zipCode   Code postal
     * @param string $country   Pays
     * @param string $phone     Téléphone
     * @return void
     */
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

    /**
<<<<<<< HEAD
     * Supprime une adresse pour un utilisateur.
     *
     * - Hard-delete si l'adresse n'a jamais été utilisée dans une commande (aucun snapshot).
     * - Soft-delete (deleted_at + saved=0) si un snapshot existe : préserve la traçabilité
     *   des commandes historiques (L123-22 C.com.) sans exposer les données à l'utilisateur.
     *
     * @param int $id     Identifiant de l'adresse
     * @param int $userId Identifiant du propriétaire (sécurité IDOR)
=======
     * Vérifie si une adresse a été utilisée dans une commande via un snapshot.
     *
     * Consulte `order_address_snapshots.source_address_id` pour déterminer
     * si cette adresse a déjà été snapshotée (commande passée, active ou archivée).
     *
     * @param int $id Identifiant de l'adresse
     * @return bool true si l'adresse est référencée dans au moins un snapshot
     */
    public function hasOrderSnapshot(int $id): bool
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM order_address_snapshots WHERE source_address_id = ? LIMIT 1",
            [$id]
        );
        return $row !== false;
    }

    /**
     * Supprime ou soft-delete une adresse appartenant à l'utilisateur.
     *
     * Règle métier :
     * - Si l'adresse a été utilisée dans une commande (snapshot existant) →
     *   soft-delete via deleted_at = NOW() pour préserver l'historique.
     * - Si l'adresse n'a jamais été utilisée dans une commande →
     *   suppression physique (DELETE).
     *
     * @param int $id     Identifiant de l'adresse
     * @param int $userId Identifiant du propriétaire (contrôle d'accès)
     * @return void
>>>>>>> eb62025 (fix(phpstan): AddressModel — supprimer comparaison !== null toujours vraie (fetchOne retourne array|false))
     */
    public function deleteForUser(int $id, int $userId): void
    {
        if ($this->hasOrderSnapshot($id)) {
            $this->db->execute(
<<<<<<< HEAD
                "UPDATE {$this->table} SET saved = 0, deleted_at = NOW() WHERE id = ? AND user_id = ?",
=======
                "UPDATE {$this->table} SET deleted_at = NOW(), saved = 0 WHERE id = ? AND user_id = ?",
>>>>>>> eb62025 (fix(phpstan): AddressModel — supprimer comparaison !== null toujours vraie (fetchOne retourne array|false))
                [$id, $userId]
            );
        } else {
            $this->db->execute(
                "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?",
                [$id, $userId]
            );
        }
    }
}
