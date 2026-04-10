<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion des bons de commande PDF (tarifs annuels).
 */
class OrderFormModel extends Model
{
    protected string $table = 'order_forms';

    /**
     * Retourne tous les bons de commande triés par année décroissante.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} ORDER BY year DESC, uploaded_at DESC"
        );
    }

    /**
     * Retourne le nombre total de bons de commande.
     *
     * @return int
     */
    public function countAll(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS total FROM {$this->table}");
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Retourne une page de bons de commande.
     *
     * @param int $page    Numéro de page (commence à 1)
     * @param int $perPage Nombre d'éléments par page
     * @return array<int, array<string, mixed>>
     */
    public function getPaginated(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} ORDER BY year DESC, uploaded_at DESC LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );
    }

    /**
     * Trouve un bon de commande par son identifiant.
     *
     * @param int $id Identifiant du bon de commande
     * @return array<string, mixed>|false
     */
    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }

    /**
     * Trouve un bon de commande par son identifiant, ou retourne null si absent.
     *
     * @param int $id Identifiant du bon de commande
     * @return array<string, mixed>|null
     */
    public function findByIdOrNull(int $id): ?array
    {
        $row = $this->findById($id);
        return $row ?: null;
    }

    /**
     * Retourne le bon de commande le plus récent (dernier uploaded_at).
     *
     * @return array<string, mixed>|null
     */
    public function getLatest(): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM {$this->table} ORDER BY uploaded_at DESC, id DESC LIMIT 1"
        );
        return $row ?: null;
    }

    /**
     * Crée un nouveau bon de commande.
     *
     * @param int         $year     Millésime / année tarifaire
     * @param string|null $label    Étiquette descriptive optionnelle
     * @param string      $filename Nom du fichier PDF stocké
     * @return int Identifiant du bon créé
     */
    public function create(int $year, ?string $label, string $filename): int
    {
        return (int) $this->db->insert(
            "INSERT INTO {$this->table} (year, label, filename) VALUES (?, ?, ?)",
            [$year, $label, $filename]
        );
    }

    /**
     * Supprime un bon de commande par son identifiant.
     *
     * @param int $id Identifiant du bon de commande
     * @return int Identifiant supprimé
     */
    public function delete(int $id): int
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $id;
    }
}
