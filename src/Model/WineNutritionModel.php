<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Gère les valeurs nutritionnelles et informations complémentaires d'un vin.
 * Relation 1:1 avec la table wines via wine_id (UNIQUE).
 */
class WineNutritionModel extends Model
{
    protected string $table = 'wine_nutrition';

    /** @var string[] Tous les champs gérés (hors wine_id / id / updated_at) */
    public const ALL_FIELDS = [
        'energy_kj', 'energy_kcal', 'fat_g', 'saturated_fat_g',
        'carbohydrate_g', 'sugars_g', 'protein_g', 'salt_g',
        'alcohol_degree', 'aging_potential', 'ingredients',
    ];

    /**
     * Retourne la ligne nutritionnelle d'un vin, ou false si absente.
     *
     * @param int $wineId
     * @return array<string, mixed>|false
     */
    public function findByWineId(int $wineId): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE wine_id = ?",
            [$wineId]
        );
    }

    /**
     * Insère ou met à jour les données nutritionnelles d'un vin.
     * N'exécute rien si tous les champs POST sont vides.
     *
     * @param int                  $wineId
     * @param array<string, mixed> $data   Champs issus du POST
     * @return void
     */
    public function upsert(int $wineId, array $data): void
    {
        $fields = $this->filterFields($data);

        if ($fields === []) {
            return;
        }

        $cols      = array_keys($fields);
        $colList   = implode(', ', array_map(fn(string $c) => "`{$c}`", $cols));
        $valList   = implode(', ', array_fill(0, count($cols), '?'));
        $updateSet = implode(', ', array_map(fn(string $c) => "`{$c}` = VALUES(`{$c}`)", $cols));

        $sql = "INSERT INTO {$this->table} (wine_id, {$colList})
                VALUES (?, {$valList})
                ON DUPLICATE KEY UPDATE {$updateSet}";

        $params = [$wineId, ...array_values($fields)];

        $this->db->execute($sql, $params);
    }

    /**
     * Filtre uniquement les colonnes connues depuis un tableau POST.
     * N'upsert que si au moins une valeur non nulle est présente.
     *
     * @param  array<string, mixed> $data
     * @return array<string, string|null>
     */
    private function filterFields(array $data): array
    {
        $result = [];
        foreach (self::ALL_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $result[$field] = $data[$field] !== '' ? trim((string) $data[$field]) : null;
            }
        }

        $hasValue = array_filter($result, fn($v) => $v !== null);
        return $hasValue !== [] ? $result : [];
    }
}
