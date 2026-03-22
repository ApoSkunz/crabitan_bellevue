<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class WineModel extends Model
{
    protected string $table = 'wines';

    /**
     * Retourne tous les vins disponibles, avec filtres optionnels.
     *
     * @param string|null $color  Couleur (red|white|rosé|champagne|sparkling|sweet)
     * @param string      $sort   Tri : 'default'|'price_asc'|'price_desc'|'vintage_asc'|'vintage_desc'
     * @return array<int, array<string, mixed>>
     */
    public function getAll(?string $color = null, string $sort = 'default'): array
    {
        $where  = [];
        $params = [];

        $where[] = 'available = 1';

        $validColors = ['red', 'white', 'rosé', 'champagne', 'sparkling', 'sweet'];
        if ($color !== null && in_array($color, $validColors, true)) {
            $where[]  = 'wine_color = ?';
            $params[] = $color;
        }

        $orderBy = match ($sort) {
            'price_asc'    => 'price ASC, vintage DESC',
            'price_desc'   => 'price DESC, vintage DESC',
            'vintage_asc'  => 'vintage ASC',
            'vintage_desc' => 'vintage DESC',
            default        => 'wine_color DESC, vintage DESC', // sweet first, then white, red
        };

        $sql = "SELECT id, label_name, wine_color, format, vintage, price, quantity,
                       available, certification_label, image_path, slug,
                       oenological_comment, award, award_path
                FROM {$this->table}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$orderBy}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Retourne les vins groupés par couleur (pour la page collection).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getAllByColor(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id, label_name, wine_color, vintage, price, quantity,
                    available, image_path, slug, oenological_comment, award
             FROM {$this->table}
             WHERE available = 1
             ORDER BY wine_color DESC, vintage DESC"
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['wine_color']][] = $row;
        }

        return $grouped;
    }

    /**
     * Retourne un vin complet par son slug.
     *
     * @return array<string, mixed>|null
     */
    public function getBySlug(string $slug): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, label_name, wine_color, format, vintage, price, quantity,
                    available, certification_label, area, city, variety_of_vine,
                    age_of_vineyard, oenological_comment, soil, pruning, harvest,
                    vinification, barrel_fermentation, award, award_path,
                    extra_comment, technical_form_path, image_path, slug
             FROM {$this->table}
             WHERE slug = ?",
            [$slug]
        );

        return $row ?: null;
    }

    /**
     * Retourne les N derniers vins disponibles (pour la homepage).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLatest(int $limit = 3): array
    {
        return $this->db->fetchAll(
            "SELECT id, label_name, wine_color, vintage, price, quantity,
                    available, image_path, slug, oenological_comment
             FROM {$this->table}
             WHERE available = 1
             ORDER BY id DESC
             LIMIT ?",
            [$limit]
        );
    }
}
