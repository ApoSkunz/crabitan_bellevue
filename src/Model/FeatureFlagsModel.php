<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Accès CRUD à la table feature_flags.
 * Lecture complète via FeatureFlags::getBool/getInt/getFloat (cache statique).
 * Ce modèle est utilisé uniquement par FeatureFlagsAdminController.
 */
class FeatureFlagsModel extends Model
{
    protected string $table = 'feature_flags';

    /**
     * Retourne tous les flags ordonnés par clé.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        return $this->db->fetchAll(
            'SELECT `key`, `value`, `type`, `label`, `description`, `updated_at`, `updated_by`
             FROM `feature_flags`
             ORDER BY `key`'
        );
    }

    /**
     * Retourne un flag par sa clé, ou null si absent.
     *
     * @param string $key
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT `key`, `value`, `type`, `label`, `description`, `updated_at`, `updated_by`
             FROM `feature_flags`
             WHERE `key` = ?',
            [$key]
        );
        return is_array($row) ? $row : null;
    }

    /**
     * Met à jour la valeur d'un flag et enregistre l'auteur de la modification.
     *
     * @param string $key       Clé du flag
     * @param string $value     Nouvelle valeur (chaîne sérialisée)
     * @param int    $updatedBy ID de l'administrateur ayant effectué la modification
     * @return void
     */
    public function updateValue(string $key, string $value, int $updatedBy): void
    {
        $this->db->query(
            'UPDATE `feature_flags` SET `value` = ?, `updated_by` = ? WHERE `key` = ?',
            [$value, $updatedBy, $key]
        );
    }
}
