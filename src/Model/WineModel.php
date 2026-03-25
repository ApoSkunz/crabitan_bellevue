<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class WineModel extends Model
{
    protected string $table = 'wines';

    private const COND_AVAILABLE  = 'available = 1';
    private const COND_OUT        = 'available = 0';
    private const COND_COLOR      = 'wine_color = ?';
    private const ORDER_DEFAULT   = 'wine_color DESC, vintage DESC';
    private const SQL_WHERE       = 'WHERE ';
    private const SQL_AND         = ' AND ';

    /** @param string[] $conditions */
    private function buildWhereClause(array $conditions): string
    {
        return $conditions !== [] ? self::SQL_WHERE . implode(self::SQL_AND, $conditions) : '';
    }

    /**
     * Retourne les vins disponibles avec filtres, tri et pagination optionnels.
     *
     * @param string|null $color   Couleur (red|white|rosé|sweet)
     * @param string      $sort    Tri : 'default'|'likes_desc'|'price_asc'|'price_desc'|'vintage_asc'|'vintage_desc'
     * @param int         $limit   Nombre de résultats (0 = pas de limite)
     * @param int         $offset  Décalage pour la pagination
     * @return array<int, array<string, mixed>>
     */
    public function getAll(?string $color = null, string $sort = 'default', int $limit = 0, int $offset = 0): array
    {
        $where  = [];
        $params = [];

        $where[] = self::COND_AVAILABLE;

        $validColors = ['red', 'white', 'rosé', 'sweet'];
        if ($color !== null && in_array($color, $validColors, true)) {
            $where[]  = self::COND_COLOR;
            $params[] = $color;
        }

        $orderBy = match ($sort) {
            'likes_desc'   => 'likes_count DESC, vintage DESC',
            'price_asc'    => 'price ASC, vintage DESC',
            'price_desc'   => 'price DESC, vintage DESC',
            'vintage_asc'  => 'vintage ASC',
            'vintage_desc' => 'vintage DESC',
            default        => self::ORDER_DEFAULT,
        };

        $limitClause = '';
        if ($limit > 0) {
            $limitClause  = ' LIMIT ? OFFSET ?';
            $params[]     = $limit;
            $params[]     = $offset;
        }

        // likes_count sera remplacé par un subquery réel dans feat/account (table favorites)
        $sql = "SELECT id, label_name, wine_color, format, vintage, price, quantity,
                       available, certification_label, image_path, slug,
                       oenological_comment, award, extra_comment, is_cuvee_speciale,
                       0 AS likes_count
                FROM {$this->table}
                " . $this->buildWhereClause($where) . "
                ORDER BY {$orderBy}{$limitClause}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Compte les vins disponibles pour la pagination (mêmes filtres que getAll).
     */
    public function countAll(?string $color = null): int
    {
        $where  = [self::COND_AVAILABLE];
        $params = [];

        $validColors = ['red', 'white', 'rosé', 'sweet'];
        if ($color !== null && in_array($color, $validColors, true)) {
            $where[]  = self::COND_COLOR;
            $params[] = $color;
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} " . $this->buildWhereClause($where),
            $params
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Retourne les vins groupés par couleur (pour la page collection).
     * Inclut tous les vins (disponibles ET indisponibles).
     *
     * @param string|null $color   Filtre couleur optionnel
     * @param string      $sort    Tri : 'default'|'likes_desc'|'vintage_asc'|'vintage_desc'
     * @param string|null $avail   Filtre dispo : null = tous | 'available' | 'out'
     * @param int         $perPage Limite totale (0 = pas de limite)
     * @param int         $offset  Décalage pour la pagination
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getAllByColor(
        ?string $color = null,
        string $sort = 'default',
        ?string $avail = null,
        int $perPage = 0,
        int $offset = 0
    ): array {
        $where  = [];
        $params = [];

        $validColors = ['red', 'white', 'rosé', 'sweet'];
        if ($color !== null && in_array($color, $validColors, true)) {
            $where[]  = self::COND_COLOR;
            $params[] = $color;
        }

        if ($avail === 'available') {
            $where[] = self::COND_AVAILABLE;
        } elseif ($avail === 'out') {
            $where[] = self::COND_OUT;
        }

        $orderBy = match ($sort) {
            'likes_desc'   => 'wine_color DESC, likes_count DESC, vintage DESC',
            'vintage_asc'  => 'wine_color DESC, vintage ASC',
            'vintage_desc' => 'wine_color DESC, vintage DESC',
            default        => self::ORDER_DEFAULT,
        };

        $whereClause  = $this->buildWhereClause($where);
        $limitClause  = '';
        if ($perPage > 0) {
            $limitClause  = ' LIMIT ? OFFSET ?';
            $params[]     = $perPage;
            $params[]     = $offset;
        }

        $rows = $this->db->fetchAll(
            "SELECT id, label_name, wine_color, vintage, price, quantity,
                    available, certification_label, image_path, slug,
                    oenological_comment, award, extra_comment, is_cuvee_speciale,
                    0 AS likes_count
             FROM {$this->table}
             {$whereClause}
             ORDER BY {$orderBy}{$limitClause}",
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['wine_color']][] = $row;
        }

        return $grouped;
    }

    /**
     * Compte les vins pour la pagination de la collection (mêmes filtres que getAllByColor).
     */
    public function countAllByColor(?string $color = null, ?string $avail = null): int
    {
        $where  = [];
        $params = [];

        $validColors = ['red', 'white', 'rosé', 'sweet'];
        if ($color !== null && in_array($color, $validColors, true)) {
            $where[]  = self::COND_COLOR;
            $params[] = $color;
        }

        if ($avail === 'available') {
            $where[] = self::COND_AVAILABLE;
        } elseif ($avail === 'out') {
            $where[] = self::COND_OUT;
        }

        $whereClause = $this->buildWhereClause($where);

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} {$whereClause}",
            $params
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Retourne la page (1-based) où chaque couleur apparaît en premier dans la collection paginée.
     * Basé sur ORDER BY wine_color DESC (tri primaire de getAllByColor), sans filtre couleur.
     *
     * @return array<string, int>  ex. ['white' => 1, 'sweet' => 1, 'rosé' => 2, 'red' => 3]
     */
    public function getColorFirstPages(?string $avail, int $perPage): array
    {
        if ($perPage <= 0) {
            return [];
        }

        $where  = [];
        $params = [];

        if ($avail === 'available') {
            $where[] = self::COND_AVAILABLE;
        } elseif ($avail === 'out') {
            $where[] = self::COND_OUT;
        }

        $whereClause = $this->buildWhereClause($where);

        $rows = $this->db->fetchAll(
            "SELECT wine_color, COUNT(*) AS cnt
             FROM {$this->table}
             {$whereClause}
             GROUP BY wine_color
             ORDER BY wine_color DESC",
            $params
        );

        $result   = [];
        $position = 1;
        foreach ($rows as $row) {
            $result[$row['wine_color']] = (int) ceil($position / $perPage);
            $position += (int) $row['cnt'];
        }

        return $result;
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
                    vinification, barrel_fermentation, award,
                    extra_comment, is_cuvee_speciale, image_path, slug
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
             WHERE " . self::COND_AVAILABLE . "
             ORDER BY id DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Retourne un vin aléatoire disponible pour une couleur donnée (plan du site).
     *
     * @return array<string, mixed>|null
     */
    public function getRandomByColor(string $color): ?array
    {
        $validColors = ['red', 'white', 'rosé', 'sweet'];
        if (!in_array($color, $validColors, true)) {
            return null;
        }

        $row = $this->db->fetchOne(
            "SELECT image_path, slug FROM {$this->table}
             WHERE " . self::COND_AVAILABLE . "
             AND " . self::COND_COLOR . "
             AND image_path IS NOT NULL AND image_path != ''
             ORDER BY RAND() LIMIT 1",
            [$color]
        );

        return $row ?: null;
    }

    /**
     * Retourne un vin aléatoire disponible toutes couleurs (plan du site — collection).
     *
     * @return array<string, mixed>|null
     */
    public function getRandom(): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT image_path, slug FROM {$this->table}
             WHERE " . self::COND_AVAILABLE . "
             AND image_path IS NOT NULL AND image_path != ''
             ORDER BY RAND() LIMIT 1"
        );

        return $row ?: null;
    }

    // ----------------------------------------------------------------
    // Méthodes admin
    // ----------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $row ?: null;
    }

    /**
     * Tous les vins pour le back-office (disponibles + indisponibles), paginés.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getForAdmin(?string $color, ?string $available, int $limit, int $offset): array
    {
        $where  = [];
        $params = [];

        $validColors = ['red', 'white', 'rosé', 'sweet'];
        if ($color !== null && in_array($color, $validColors, true)) {
            $where[]  = self::COND_COLOR;
            $params[] = $color;
        }

        if ($available === 'available') {
            $where[] = self::COND_AVAILABLE;
        } elseif ($available === 'out') {
            $where[] = self::COND_OUT;
        }

        $whereClause = $this->buildWhereClause($where);
        $params[]    = $limit;
        $params[]    = $offset;

        return $this->db->fetchAll(
            "SELECT id, label_name, wine_color, format, vintage, price,
                    quantity, available, image_path, slug, is_cuvee_speciale
             FROM {$this->table}
             {$whereClause}
             ORDER BY available DESC, id DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countForAdmin(?string $color, ?string $available = null): int
    {
        $where  = [];
        $params = [];

        $validColors = ['red', 'white', 'rosé', 'sweet'];
        if ($color !== null && in_array($color, $validColors, true)) {
            $where[]  = self::COND_COLOR;
            $params[] = $color;
        }

        if ($available === 'available') {
            $where[] = self::COND_AVAILABLE;
        } elseif ($available === 'out') {
            $where[] = self::COND_OUT;
        }

        $whereClause = $this->buildWhereClause($where);

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} {$whereClause}",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO {$this->table}
             (label_name, wine_color, format, vintage, price, quantity, available,
              certification_label, area, city, variety_of_vine, age_of_vineyard,
              oenological_comment, soil, pruning, harvest, vinification,
              barrel_fermentation, award, extra_comment,
              is_cuvee_speciale, image_path, slug)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $data['label_name'],
                $data['wine_color'],
                $data['format'],
                $data['vintage'],
                $data['price'],
                $data['quantity'],
                $data['available'],
                $data['certification_label'] ?: null,
                $data['area'],
                $data['city'],
                $data['variety_of_vine'],
                $data['age_of_vineyard'],
                $data['oenological_comment'],
                $data['soil'],
                $data['pruning'],
                $data['harvest'],
                $data['vinification'],
                $data['barrel_fermentation'],
                $data['award'],
                $data['extra_comment'],
                $data['is_cuvee_speciale'],
                $data['image_path'],
                $data['slug'],
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET label_name = ?, wine_color = ?, format = ?, vintage = ?, price = ?,
                 quantity = ?, available = ?, certification_label = ?,
                 area = ?, city = ?, variety_of_vine = ?, age_of_vineyard = ?,
                 oenological_comment = ?, soil = ?, pruning = ?, harvest = ?,
                 vinification = ?, barrel_fermentation = ?, award = ?, extra_comment = ?,
                 is_cuvee_speciale = ?, image_path = ?, slug = ?
             WHERE id = ?",
            [
                $data['label_name'],
                $data['wine_color'],
                $data['format'],
                $data['vintage'],
                $data['price'],
                $data['quantity'],
                $data['available'],
                $data['certification_label'] ?: null,
                $data['area'],
                $data['city'],
                $data['variety_of_vine'],
                $data['age_of_vineyard'],
                $data['oenological_comment'],
                $data['soil'],
                $data['pruning'],
                $data['harvest'],
                $data['vinification'],
                $data['barrel_fermentation'],
                $data['award'],
                $data['extra_comment'],
                $data['is_cuvee_speciale'],
                $data['image_path'],
                $data['slug'],
                $id,
            ]
        );
    }
}
