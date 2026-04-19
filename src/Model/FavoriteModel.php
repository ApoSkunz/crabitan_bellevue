<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion des vins favoris (likes) d'un utilisateur.
 */
class FavoriteModel extends Model
{
    protected string $table = 'favorites';

    /**
     * Retourne les vins favoris d'un utilisateur, avec détails du vin.
     *
     * @param int $userId Identifiant du compte
     * @return array<int, array<string, mixed>>
     */
    public function getByUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT f.wine_id, w.label_name AS name, w.slug, w.wine_color AS color,
                    w.vintage, w.price, w.image_path,
                    w.available, w.stock_quantity, w.is_cuvee_speciale
             FROM {$this->table} f
             JOIN wines w ON w.id = f.wine_id
             WHERE f.user_id = ?
             ORDER BY f.created_at DESC",
            [$userId]
        );
        return $rows;
    }

    /**
     * Indique si un vin est marqué comme favori par un utilisateur.
     *
     * @param int $userId Identifiant du compte
     * @param int $wineId Identifiant du vin
     * @return bool
     */
    public function isLiked(int $userId, int $wineId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT 1 FROM {$this->table} WHERE user_id = ? AND wine_id = ?",
            [$userId, $wineId]
        );
        return (bool) $row;
    }

    /**
     * Ajoute si absent, retire si présent.
     * Retourne true si ajouté, false si retiré.
     *
     * @param int $userId Identifiant du compte
     * @param int $wineId Identifiant du vin
     * @return bool true si ajouté, false si retiré
     */
    public function toggle(int $userId, int $wineId): bool
    {
        if ($this->isLiked($userId, $wineId)) {
            $this->db->execute(
                "DELETE FROM {$this->table} WHERE user_id = ? AND wine_id = ?",
                [$userId, $wineId]
            );
            return false;
        }

        $this->db->insert(
            "INSERT INTO {$this->table} (user_id, wine_id) VALUES (?, ?)",
            [$userId, $wineId]
        );
        return true;
    }

    /**
     * Retourne le nombre de vins favoris d'un utilisateur.
     *
     * @param int $userId Identifiant du compte
     * @return int
     */
    public function countForUser(int $userId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} WHERE user_id = ?",
            [$userId]
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Retourne un tableau indexé par wine_id pour lookup O(1).
     * @return array<int, true>
     */
    public function getLikedIds(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT wine_id FROM {$this->table} WHERE user_id = ?",
            [$userId]
        );
        $ids = [];
        foreach ($rows as $row) {
            $ids[(int) $row['wine_id']] = true;
        }
        return $ids;
    }
}
