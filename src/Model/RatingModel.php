<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion des avis et notes des vins.
 *
 * La table wine_ratings stocke les notes individuelles (1–5).
 * Une note n'est affichée que si le vin totalise au moins 2 avis.
 */
class RatingModel extends Model
{
    protected string $table = 'wine_ratings';

    /** Nombre minimum d'avis pour afficher une note moyenne. */
    private const MIN_REVIEWS = 2;

    /**
     * Retourne la note moyenne et le nombre d'avis pour un vin donné.
     * Retourne null si le vin a moins de MIN_REVIEWS avis.
     *
     * @param  int                              $wineId Identifiant du vin
     * @return array{avg: float, count: int}|null
     */
    public function getAvg(int $wineId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT AVG(rating) AS avg, COUNT(*) AS cnt
             FROM {$this->table}
             WHERE wine_id = ?",
            [$wineId]
        );

        if (!$row || (int) $row['cnt'] < self::MIN_REVIEWS) {
            return null;
        }

        return ['avg' => (float) $row['avg'], 'count' => (int) $row['cnt']];
    }

    /**
     * Retourne les notes moyennes pour un ensemble de vins en une seule requête.
     * Les vins ayant moins de MIN_REVIEWS avis reçoivent null.
     *
     * @param  int[]                    $wineIds Liste des identifiants de vins
     * @return array<int, float|null>            Tableau [wine_id => avg|null]
     */
    public function getAvgBulk(array $wineIds): array
    {
        if ($wineIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($wineIds), '?'));

        $rows = $this->db->fetchAll(
            "SELECT wine_id, AVG(rating) AS avg, COUNT(*) AS cnt
             FROM {$this->table}
             WHERE wine_id IN ({$placeholders})
             GROUP BY wine_id",
            $wineIds
        );

        // Initialise tout à null
        $result = array_fill_keys($wineIds, null);

        foreach ($rows as $row) {
            if ((int) $row['cnt'] >= self::MIN_REVIEWS) {
                $result[(int) $row['wine_id']] = (float) $row['avg'];
            }
        }

        return $result;
    }
}
