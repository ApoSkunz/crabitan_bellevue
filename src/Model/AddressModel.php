<?php

declare(strict_types=1);

namespace Model;

use Core\Model;
use Service\EncryptionService;

/**
 * Gestion des adresses de facturation et de livraison d'un compte utilisateur.
 *
 * Implémente la logique de soft-delete différenciée :
 * - adresse liée à une commande (snapshot) → deleted_at = NOW()
 * - adresse jamais utilisée dans une commande → suppression physique
 *
 * Les colonnes `street` et `phone` sont chiffrées en AES-256-GCM (RGPD Art. 32).
 */
class AddressModel extends Model
{
    protected string $table = 'addresses';

    private const VALID_TYPES = ['billing', 'delivery'];

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
     * Retourne les adresses sauvegardées et non supprimées de l'utilisateur.
     *
     * Les adresses soft-deleted (deleted_at IS NOT NULL) sont exclues.
     *
     * @param int $userId Identifiant de l'utilisateur
     * @return array<int, array<string, mixed>>
     */
    public function getByUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id, user_id, type, firstname, lastname, civility,
                    street, city, zip_code, country, phone, saved,
                    deleted_at, preserved_for_order, created_at, updated_at
             FROM {$this->table}
             WHERE user_id = ? AND saved = 1 AND deleted_at IS NULL
             ORDER BY type ASC, id ASC",
            [$userId]
        );
        return array_map(fn(array $row) => $this->decryptRow($row), $rows);
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
            "SELECT id, user_id, type, firstname, lastname, civility,
                    street, city, zip_code, country, phone, saved,
                    deleted_at, preserved_for_order, created_at, updated_at
             FROM {$this->table} WHERE id = ? AND user_id = ? AND deleted_at IS NULL",
            [$id, $userId]
        );
        return $row ? $this->decryptRow($row) : null;
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
            [
                $userId, $type, $firstname, $lastname, $civility,
                $this->encryption->encrypt($street),
                $city, $zipCode, $country,
                $this->encryption->encrypt($phone),
            ]
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
            [
                $firstname, $lastname, $civility,
                $this->encryption->encrypt($street),
                $city, $zipCode, $country,
                $this->encryption->encrypt($phone),
                $id, $userId,
            ]
        );
    }

    /**
     * Déchiffre les colonnes sensibles d'une ligne d'adresse.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decryptRow(array $row): array
    {
        if (isset($row['street']) && is_string($row['street'])) {
            $row['street'] = $this->encryption->decrypt($row['street']) ?? $row['street'];
        }
        if (isset($row['phone']) && is_string($row['phone'])) {
            $row['phone'] = $this->encryption->decrypt($row['phone']) ?? $row['phone'];
        }
        return $row;
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
     */
    public function deleteForUser(int $id, int $userId): void
    {
        if ($this->hasOrderSnapshot($id)) {
            $this->db->execute(
                "UPDATE {$this->table} SET saved = 0, deleted_at = NOW() WHERE id = ? AND user_id = ?",
                [$id, $userId]
            );
        } else {
            $this->db->execute(
                "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?",
                [$id, $userId]
            );
        }
    }

    /**
     * Retourne les adresses orphelines éligibles à la purge définitive.
     *
     * Éligible si : preserved_for_order = 1 ET aucune commande active (pending/processing)
     * ne référence cette adresse via un snapshot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOrphanedAddresses(): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.user_id
             FROM {$this->table} a
             WHERE a.preserved_for_order = 1
               AND NOT EXISTS (
                   SELECT 1 FROM order_address_snapshots oas
                   JOIN orders o ON (o.id_billing_snapshot = oas.id OR o.id_delivery_snapshot = oas.id)
                   WHERE oas.source_address_id = a.id
                     AND o.status IN ('pending', 'processing')
               )"
        );
    }

    /**
     * Supprime définitivement les adresses orphelines éligibles.
     *
     * @return int Nombre d'adresses supprimées
     */
    public function purgeOrphaned(): int
    {
        return $this->db->execute(
            "DELETE a FROM {$this->table} a
             WHERE a.preserved_for_order = 1
               AND NOT EXISTS (
                   SELECT 1 FROM order_address_snapshots oas
                   JOIN orders o ON (o.id_billing_snapshot = oas.id OR o.id_delivery_snapshot = oas.id)
                   WHERE oas.source_address_id = a.id
                     AND o.status IN ('pending', 'processing')
               )"
        );
    }
}
