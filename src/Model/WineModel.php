<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion du catalogue de vins (consultation, filtrage, pagination, admin).
 */
class WineModel extends Model // NOSONAR — php:S1448 : 33 méthodes cohérentes au domaine vin — découpage artificiel sans valeur
{
    protected string $table = 'wines';

    private const COND_AVAILABLE   = 'available = 1';
    private const COND_OUT         = 'available = 0';
    private const COND_COLOR       = 'wine_color = ?';
    private const COND_CUVEE       = 'is_cuvee_speciale = 1';
    private const ORDER_DEFAULT    = 'wine_color DESC, vintage DESC';
    private const SQL_WHERE = 'WHERE ';
    private const SQL_AND          = ' AND ';
    private const COND_IMAGE       = "image_path IS NOT NULL AND image_path != ''";

    /** @param string[] $conditions */
    private function buildWhereClause(array $conditions): string
    {
        return $conditions !== [] ? self::SQL_WHERE . implode(self::SQL_AND, $conditions) : '';
    }

    /**
     * Retourne le fragment SQL commun aux requêtes de liste : colonnes bilingues,
     * COALESCE likes_count, FROM et LEFT JOIN favorites agrégé.
     *
     * @return string Fragment SQL insérable après les colonnes fixes du SELECT
     */
    private function wineSelectFromFavorites(): string
    {
        return "w.oenological_comment_fr, w.oenological_comment_en,
                       w.award_fr, w.award_en,
                       w.extra_comment_fr, w.extra_comment_en, w.is_cuvee_speciale,
                       COALESCE(lc.cnt, 0) AS likes_count
                FROM {$this->table} w
                LEFT JOIN (SELECT wine_id, COUNT(*) AS cnt FROM favorites GROUP BY wine_id) lc ON lc.wine_id = w.id";
    }

    /**
     * Retourne les valeurs des champs bilingues (_fr/_en) pour INSERT ou UPDATE.
     *
     * @param array<string, mixed> $data
     * @return list<string>
     */
    private function bilingualParams(array $data): array
    {
        return [
            $data['oenological_comment_fr'] ?? '',
            $data['oenological_comment_en'] ?? '',
            $data['soil_fr'] ?? '',
            $data['soil_en'] ?? '',
            $data['pruning_fr'] ?? '',
            $data['pruning_en'] ?? '',
            $data['harvest_fr'] ?? '',
            $data['harvest_en'] ?? '',
            $data['vinification_fr'] ?? '',
            $data['vinification_en'] ?? '',
            $data['barrel_fermentation_fr'] ?? '',
            $data['barrel_fermentation_en'] ?? '',
            $data['award_fr'] ?? '',
            $data['award_en'] ?? '',
            $data['extra_comment_fr'] ?? '',
            $data['extra_comment_en'] ?? '',
        ];
    }

    /**
     * Retourne un ID aléatoire parmi les lignes correspondant à la clause WHERE donnée.
     *
     * Évite ORDER BY RAND() (full table sort O(n log n)) en récupérant uniquement les IDs
     * filtrés puis en sélectionnant aléatoirement via PHP — O(n) en mémoire, acceptable pour
     * un catalogue de < 1 000 vins.
     *
     * @param string   $where  Clause WHERE complète (ex. "WHERE available = 1 AND wine_color = ?")
     * @param mixed[]  $params Paramètres liés à la clause WHERE
     * @return int|null ID aléatoire, ou null si aucune ligne ne correspond
     */
    private function randomPickId(string $where, array $params = []): ?int
    {
        $rows = $this->db->fetchAll(
            "SELECT id FROM {$this->table} {$where}",
            $params
        );
        if ($rows === []) {
            return null;
        }
        return (int) $rows[array_rand($rows)]['id'];
    }

    /**
     * Ajoute la condition cuvée spéciale au tableau de conditions si le filtre est actif.
     *
     * @param string[]  $where
     * @param bool|null $cuveSpeciale
     */
    private function applyCuveeFilter(array &$where, ?bool $cuveSpeciale): void
    {
        if ($cuveSpeciale === true) {
            $where[] = self::COND_CUVEE;
        }
    }

    /**
     * Retourne les vins disponibles avec filtres, tri et pagination optionnels.
     *
     * @param string|null $color         Couleur (red|white|rosé|sweet)
     * @param string      $sort          Tri : 'default'|'price_asc'|'price_desc'|'vintage_asc'|'vintage_desc'|'rating_desc'
     * @param int         $limit         Nombre de résultats (0 = pas de limite)
     * @param int         $offset        Décalage pour la pagination
     * @param bool|null   $cuveSpeciale  true = cuvée spéciale uniquement, null = tous
     * @return array<int, array<string, mixed>>
     */
    public function getAll(
        ?string $color = null,
        string $sort = 'default',
        int $limit = 0,
        int $offset = 0,
        ?bool $cuveSpeciale = null
    ): array {
        $where  = [];
        $params = [];

        $where[] = self::COND_AVAILABLE;

        $validColors = ['red', 'white', 'rosé', 'sweet'];
        if ($color !== null && in_array($color, $validColors, true)) {
            $where[]  = self::COND_COLOR;
            $params[] = $color;
        }

        $this->applyCuveeFilter($where, $cuveSpeciale);

        $useRatingJoin = $sort === 'rating_desc';
        $ratingJoin    = $useRatingJoin
            ? "LEFT JOIN (SELECT wine_id, AVG(rating) AS avg_rating FROM wine_ratings
                         GROUP BY wine_id HAVING COUNT(*) >= 2) wr ON w.id = wr.wine_id"
            : '';

        $orderBy = match ($sort) {
            'price_asc'    => 'price ASC, vintage DESC',
            'price_desc'   => 'price DESC, vintage DESC',
            'vintage_asc'  => 'vintage ASC',
            'vintage_desc' => 'vintage DESC',
            'rating_desc'  => 'COALESCE(wr.avg_rating, 0) DESC, vintage DESC',
            default        => self::ORDER_DEFAULT,
        };

        $limitClause = '';
        if ($limit > 0) {
            $limitClause  = ' LIMIT ? OFFSET ?';
            $params[]     = $limit;
            $params[]     = $offset;
        }

        $sql = "SELECT w.id, w.label_name, w.wine_color, w.format, w.vintage, w.price,
                       w.production_quantity, w.stock_quantity,
                       w.available, w.certification_label, w.image_path, w.slug,
                       " . $this->wineSelectFromFavorites() . "
                {$ratingJoin}
                " . $this->buildWhereClause($where) . "
                ORDER BY {$orderBy}{$limitClause}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Compte les vins disponibles pour la pagination (mêmes filtres que getAll).
     *
     * @param bool|null $cuveSpeciale true = cuvée spéciale uniquement, null = tous
     */
    public function countAll(?string $color = null, ?bool $cuveSpeciale = null): int
    {
        $where  = [self::COND_AVAILABLE];
        $params = [];

        $validColors = ['red', 'white', 'rosé', 'sweet'];
        if ($color !== null && in_array($color, $validColors, true)) {
            $where[]  = self::COND_COLOR;
            $params[] = $color;
        }

        $this->applyCuveeFilter($where, $cuveSpeciale);

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
     * @param string      $sort    Tri : 'default'|'vintage_asc'|'vintage_desc'|'rating_desc'
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
        int $offset = 0,
        ?bool $cuveSpeciale = null
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

        $this->applyCuveeFilter($where, $cuveSpeciale);

        $useRatingJoin2 = $sort === 'rating_desc';
        $ratingJoin2    = $useRatingJoin2
            ? "LEFT JOIN (SELECT wine_id, AVG(rating) AS avg_rating FROM wine_ratings
                         GROUP BY wine_id HAVING COUNT(*) >= 2) wr ON w.id = wr.wine_id"
            : '';

        $orderBy = match ($sort) {
            'vintage_asc'  => 'wine_color DESC, vintage ASC',
            'vintage_desc' => 'wine_color DESC, vintage DESC',
            'rating_desc'  => 'COALESCE(wr.avg_rating, 0) DESC, wine_color DESC, vintage DESC',
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
            "SELECT w.id, w.label_name, w.wine_color, w.vintage, w.price,
                    w.production_quantity, w.stock_quantity,
                    w.available, w.certification_label, w.image_path, w.slug,
                    " . $this->wineSelectFromFavorites() . "
             {$ratingJoin2}
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
    /**
     * @param bool|null $cuveSpeciale true = cuvée spéciale uniquement, null = tous
     */
    public function countAllByColor(?string $color = null, ?string $avail = null, ?bool $cuveSpeciale = null): int
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

        $this->applyCuveeFilter($where, $cuveSpeciale);

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
            "SELECT id, label_name, wine_color, format, vintage, price,
                    production_quantity, stock_quantity,
                    available, certification_label, area, city, variety_of_vine,
                    age_of_vineyard,
                    oenological_comment_fr, oenological_comment_en,
                    soil_fr, soil_en,
                    pruning_fr, pruning_en,
                    harvest_fr, harvest_en,
                    vinification_fr, vinification_en,
                    barrel_fermentation_fr, barrel_fermentation_en,
                    award_fr, award_en,
                    extra_comment_fr, extra_comment_en,
                    is_cuvee_speciale, image_path, slug,
                    alcohol_degree, aging_potential
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
            "SELECT id, label_name, wine_color, vintage, price, production_quantity, stock_quantity,
                    available, image_path, slug,
                    oenological_comment_fr, oenological_comment_en
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

        $id = $this->randomPickId(
            self::SQL_WHERE . self::COND_AVAILABLE . self::SQL_AND . self::COND_COLOR . self::SQL_AND . self::COND_IMAGE,
            [$color]
        );
        if ($id === null) {
            return null;
        }
        $row = $this->db->fetchOne(
            "SELECT image_path, slug FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $row ?: null;
    }

    /**
     * Retourne un vin aléatoire disponible avec les champs nécessaires pour une carte produit.
     *
     * @return array<string, mixed>|null
     */
    public function getRandomForCart(): ?array
    {
        $id = $this->randomPickId(
            self::SQL_WHERE . self::COND_AVAILABLE . self::SQL_AND . self::COND_IMAGE
        );
        if ($id === null) {
            return null;
        }
        $row = $this->db->fetchOne(
            "SELECT id, label_name, image_path, slug, price FROM {$this->table} WHERE id = ?",
            [$id]
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
        $id = $this->randomPickId(
            self::SQL_WHERE . self::COND_AVAILABLE . self::SQL_AND . self::COND_IMAGE
        );
        if ($id === null) {
            return null;
        }
        $row = $this->db->fetchOne(
            "SELECT image_path, slug FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $row ?: null;
    }

    /**
     * Retourne $limit vins aléatoires disponibles ayant une image (jeu mémoire).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRandomForMemo(int $limit = 14): array
    {
        $ids = $this->db->fetchAll(
            "SELECT id FROM {$this->table} WHERE " . self::COND_IMAGE,
            []
        );
        if ($ids === []) {
            return [];
        }
        shuffle($ids);
        $pickedIds = array_column(array_slice($ids, 0, $limit), 'id');
        if ($pickedIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($pickedIds), '?'));
        return $this->db->fetchAll(
            "SELECT slug, image_path, label_name FROM {$this->table} WHERE id IN ({$placeholders})",
            $pickedIds
        );
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
            "SELECT id, label_name, wine_color, format, vintage, price,
                    production_quantity, stock_quantity, available, certification_label,
                    area, city, variety_of_vine, age_of_vineyard,
                    oenological_comment_fr, oenological_comment_en,
                    soil_fr, soil_en, pruning_fr, pruning_en,
                    harvest_fr, harvest_en, vinification_fr, vinification_en,
                    barrel_fermentation_fr, barrel_fermentation_en,
                    award_fr, award_en, extra_comment_fr, extra_comment_en,
                    is_cuvee_speciale, featured, alcohol_degree, aging_potential,
                    image_path, slug, low_stock_notified_at, created_at, updated_at
             FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $row ?: null;
    }

    /**
     * Tous les vins pour le back-office (disponibles + indisponibles), paginés.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getForAdmin(
        ?string $color,
        ?string $available,
        int $limit,
        int $offset,
        ?int $vintage = null,
        ?string $labelName = null,
        ?bool $cuvee = null
    ): array {
        [$where, $params] = $this->buildAdminWineFilters($color, $available, $vintage, $labelName, $cuvee);
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll(
            "SELECT id, label_name, wine_color, format, vintage, price,
                    production_quantity, stock_quantity, available, image_path, slug, is_cuvee_speciale, featured
             FROM {$this->table}
             {$this->buildWhereClause($where)}
             ORDER BY available DESC, featured DESC, id DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countForAdmin(
        ?string $color,
        ?string $available = null,
        ?int $vintage = null,
        ?string $labelName = null,
        ?bool $cuvee = null
    ): int {
        [$where, $params] = $this->buildAdminWineFilters($color, $available, $vintage, $labelName, $cuvee);

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} {$this->buildWhereClause($where)}",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Retourne les millésimes distincts (ordre décroissant) pour les filtres admin.
     *
     * @return int[]
     */
    public function getAdminVintages(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT vintage FROM {$this->table} ORDER BY vintage DESC"
        );
        return array_map(static fn(array $r) => (int) $r['vintage'], $rows);
    }

    /**
     * Retourne les appellations distinctes (ordre alphabétique) pour les filtres admin.
     *
     * @return string[]
     */
    public function getAdminLabelNames(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT label_name FROM {$this->table} ORDER BY label_name ASC"
        );
        return array_map(static fn(array $r) => (string) $r['label_name'], $rows);
    }

    /**
     * @return array{0: string[], 1: mixed[]}
     */
    private function buildAdminWineFilters(?string $color, ?string $available, ?int $vintage, ?string $labelName, ?bool $cuvee): array
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

        if ($vintage !== null && $vintage > 1900 && $vintage <= (int) date('Y')) {
            $where[]  = 'vintage = ?';
            $params[] = $vintage;
        }

        if ($labelName !== null && $labelName !== '') {
            $where[]  = 'label_name LIKE ?';
            $params[] = '%' . $labelName . '%';
        }

        if ($cuvee !== null) {
            $where[]  = 'is_cuvee_speciale = ?';
            $params[] = $cuvee ? 1 : 0;
        }

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return (int) $this->db->insert(
            "INSERT INTO {$this->table}
             (label_name, wine_color, format, vintage, price, production_quantity, stock_quantity, available,
              certification_label, area, city, variety_of_vine, age_of_vineyard,
              oenological_comment_fr, oenological_comment_en,
              soil_fr, soil_en,
              pruning_fr, pruning_en,
              harvest_fr, harvest_en,
              vinification_fr, vinification_en,
              barrel_fermentation_fr, barrel_fermentation_en,
              award_fr, award_en,
              extra_comment_fr, extra_comment_en,
              is_cuvee_speciale, image_path, slug,
              alcohol_degree, aging_potential)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $data['label_name'],
                $data['wine_color'],
                $data['format'],
                $data['vintage'],
                $data['price'],
                $data['production_quantity'],
                $data['stock_quantity'] ?? 0,
                $data['available'],
                $data['certification_label'] ?: null,
                $data['area'],
                $data['city'],
                $data['variety_of_vine'],
                $data['age_of_vineyard'],
                ...$this->bilingualParams($data),
                $data['is_cuvee_speciale'],
                $data['image_path'],
                $data['slug'],
                $data['alcohol_degree'] ?? null,
                $data['aging_potential'] ?? null,
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
                 production_quantity = ?, stock_quantity = ?, available = ?, certification_label = ?,
                 area = ?, city = ?, variety_of_vine = ?, age_of_vineyard = ?,
                 oenological_comment_fr = ?, oenological_comment_en = ?,
                 soil_fr = ?, soil_en = ?,
                 pruning_fr = ?, pruning_en = ?,
                 harvest_fr = ?, harvest_en = ?,
                 vinification_fr = ?, vinification_en = ?,
                 barrel_fermentation_fr = ?, barrel_fermentation_en = ?,
                 award_fr = ?, award_en = ?,
                 extra_comment_fr = ?, extra_comment_en = ?,
                 is_cuvee_speciale = ?, image_path = ?, slug = ?,
                 alcohol_degree = ?, aging_potential = ?
             WHERE id = ?",
            [
                $data['label_name'],
                $data['wine_color'],
                $data['format'],
                $data['vintage'],
                $data['price'],
                $data['production_quantity'],
                $data['stock_quantity'] ?? 0,
                $data['available'],
                $data['certification_label'] ?: null,
                $data['area'],
                $data['city'],
                $data['variety_of_vine'],
                $data['age_of_vineyard'],
                ...$this->bilingualParams($data),
                $data['is_cuvee_speciale'],
                $data['image_path'],
                $data['slug'],
                $data['alcohol_degree'] ?? null,
                $data['aging_potential'] ?? null,
                $id,
            ]
        );
    }

    /**
     * Décrémente le stock d'un vin d'une quantité donnée (min 0).
     *
     * @param int $id  Identifiant du vin
     * @param int $qty Quantité vendue
     * @return void
     */
    public function decrementStock(int $id, int $qty): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET stock_quantity = GREATEST(0, stock_quantity - ?)
             WHERE id = ?",
            [$qty, $id]
        );
    }

    /**
     * Enregistre l'horodatage de la dernière notification stock bas pour un vin.
     *
     * @param int $id Identifiant du vin
     * @return void
     */
    public function setLowStockNotifiedAt(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET low_stock_notified_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Réinitialise la date de notification stock bas (permet une nouvelle alerte).
     *
     * À appeler quand le stock remonte au-dessus du seuil, afin que la prochaine
     * descente sous le seuil déclenche à nouveau une notification.
     *
     * @param int $id Identifiant du vin
     * @return void
     */
    public function resetLowStockNotified(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET low_stock_notified_at = NULL WHERE id = ?",
            [$id]
        );
    }

    /**
     * Marque un vin comme indisponible (available = 0).
     *
     * @param int $id Identifiant du vin
     * @return void
     */
    public function setUnavailable(int $id): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET available = 0 WHERE id = ?",
            [$id]
        );
    }

    /**
     * Retourne les utilisateurs (email, prénom, lang) ayant le vin dans leur panier BDD.
     *
     * @param int $wineId Identifiant du vin
     * @return array<int, array<string, mixed>>
     */
    public function getCartUsersWithWine(int $wineId): array
    {
        // JSON_OBJECT('wine_id', ?) lie le paramètre en string sur MariaDB/PDO —
        // on utilise LIKE avec deux patterns pour éviter les faux positifs (ex. id=2 vs id=21).
        $pattern1 = '%"wine_id":' . $wineId . ',%';
        $pattern2 = '%"wine_id":' . $wineId . '}%';
        return $this->db->fetchAll(
            "SELECT a.id, a.email, a.lang,
                    COALESCE(ai.firstname, a.email) AS first_name,
                    a.newsletter_unsubscribe_token
             FROM carts c
             JOIN accounts a ON a.id = c.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             WHERE a.deleted_at IS NULL
               AND (c.content LIKE ? OR c.content LIKE ?)",
            [$pattern1, $pattern2]
        );
    }

    /**
     * Retourne les abonnés newsletter actifs (email + lang + unsubscribe_token).
     *
     * Inclut les abonnés via le compte (newsletter=1) ET via le formulaire public (newsletter_subscriptions).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveNewsletterSubscribers(): array
    {
        // Abonnés via compte
        $fromAccounts = $this->db->fetchAll(
            "SELECT a.email, a.lang,
                    COALESCE(ai.first_name, a.email) AS first_name,
                    a.newsletter_unsubscribe_token AS unsub_token
             FROM accounts a
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             WHERE a.newsletter = 1
               AND a.deleted_at IS NULL
               AND a.role = 'customer'"
        );

        // Abonnés via formulaire public (double opt-in confirmé)
        $fromSubscriptions = $this->db->fetchAll(
            "SELECT email, lang, email AS first_name, newsletter_token_hash AS unsub_token
             FROM newsletter_subscriptions
             WHERE newsletter_confirmed = 1"
        );

        // Fusionner en dédupliquant par email
        $merged = [];
        foreach (array_merge($fromAccounts, $fromSubscriptions) as $row) {
            $email = strtolower((string) ($row['email'] ?? ''));
            if ($email !== '' && !isset($merged[$email])) {
                $merged[$email] = $row;
            }
        }

        return array_values($merged);
    }

    /**
     * Bascule la disponibilité d'un vin (available 0↔1).
     *
     * @param int $id Identifiant du vin
     * @return bool Nouvelle valeur de available
     */
    public function toggleAvailable(int $id): bool
    {
        $this->db->execute(
            "UPDATE {$this->table} SET available = 1 - available WHERE id = ?",
            [$id]
        );
        $row = $this->db->fetchOne(
            "SELECT available FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return (bool) ($row['available'] ?? false);
    }

    /**
     * Retourne le vin précédent et le vin suivant par ordre d'id (tous les vins — mode collection).
     *
     * @param  int $id Identifiant du vin courant
     * @return array{prev: array{slug:string,label_name:string}|null, next: array{slug:string,label_name:string}|null}
     */
    public function getAdjacentSlugs(int $id): array
    {
        $prev = $this->db->fetchOne(
            "SELECT slug, label_name, vintage, is_cuvee_speciale FROM {$this->table} WHERE id < ? ORDER BY id DESC LIMIT 1",
            [$id]
        ) ?: null;

        $next = $this->db->fetchOne(
            "SELECT slug, label_name, vintage, is_cuvee_speciale FROM {$this->table} WHERE id > ? ORDER BY id ASC LIMIT 1",
            [$id]
        ) ?: null;

        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * Retourne le vin précédent et le vin suivant parmi les vins disponibles (mode boutique).
     *
     * @param  int $id Identifiant du vin courant
     * @return array{prev: array{slug:string,label_name:string}|null, next: array{slug:string,label_name:string}|null}
     */
    public function getAdjacentAvailableSlugs(int $id): array
    {
        $prev = $this->db->fetchOne(
            "SELECT slug, label_name, vintage, is_cuvee_speciale FROM {$this->table}
             WHERE id < ? AND " . self::COND_AVAILABLE . " ORDER BY id DESC LIMIT 1",
            [$id]
        ) ?: null;

        $next = $this->db->fetchOne(
            "SELECT slug, label_name, vintage, is_cuvee_speciale FROM {$this->table}
             WHERE id > ? AND " . self::COND_AVAILABLE . " ORDER BY id ASC LIMIT 1",
            [$id]
        ) ?: null;

        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * Retourne tous les slugs des vins disponibles (pour sitemap.xml).
     *
     * @return array<int, array{slug: string}>
     */
    public function getAllSlugs(): array
    {
        return $this->db->fetchAll(
            "SELECT slug FROM {$this->table} ORDER BY slug"
        );
    }

    /**
     * Vins disponibles à la vente pour sitemap produits.
     * Filtre : available = 1 AND stock > 0.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableForSitemap(): array
    {
        return $this->db->fetchAll(
            "SELECT slug, label_name, vintage, price, updated_at
             FROM {$this->table}
             WHERE " . self::COND_AVAILABLE . " AND stock_quantity > 0
             ORDER BY updated_at DESC"
        );
    }

    /**
     * Retourne les vins disponibles à la vente pour le fichier llms.txt.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableForLlms(): array
    {
        return $this->db->fetchAll(
            "SELECT slug, label_name, vintage, price, wine_color
             FROM {$this->table}
             WHERE " . self::COND_AVAILABLE . " AND stock_quantity > 0
             ORDER BY wine_color DESC, vintage DESC"
        );
    }

    /**
     * Retourne slug, label_name, image_path et vintage pour le sitemap media.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllForMediaSitemap(): array
    {
        return $this->db->fetchAll(
            "SELECT slug, label_name, image_path, vintage
             FROM {$this->table}
             WHERE " . self::COND_IMAGE . "
             ORDER BY slug"
        );
    }

    /**
     * Retourne le vin mis en avant pour la homepage (featured=1 disponible),
     * avec fallback sur cuvée spéciale disponible, puis vin disponible le plus récent.
     *
     * @return array<string, mixed>|null
     */
    public function getFeatured(): ?array
    {
        $cols = 'id, label_name, certification_label, wine_color, vintage, price, image_path, slug, is_cuvee_speciale, featured, available';

        // 1. Vin explicitement mis en avant et disponible
        $row = $this->db->fetchOne(
            "SELECT {$cols} FROM {$this->table} WHERE featured = 1 AND available = 1 LIMIT 1"
        );
        if ($row) {
            return $row;
        }

        // 2. Fallback : cuvée spéciale disponible
        $row = $this->db->fetchOne(
            "SELECT {$cols} FROM {$this->table} WHERE is_cuvee_speciale = 1 AND available = 1 ORDER BY vintage DESC LIMIT 1"
        );
        if ($row) {
            return $row;
        }

        // 3. Fallback : vin disponible le plus récent
        $row = $this->db->fetchOne(
            "SELECT {$cols} FROM {$this->table} WHERE available = 1 ORDER BY id DESC LIMIT 1"
        );
        return $row ?: null;
    }

    /**
     * Définit un vin comme mis en avant (un seul à la fois).
     * Remet tous les autres à featured=0 avant de mettre le vin cible à 1.
     *
     * @param int $id Identifiant du vin à mettre en avant
     * @return void
     */
    public function setFeatured(int $id): void
    {
        $this->db->execute("UPDATE {$this->table} SET featured = 0");
        $this->db->execute("UPDATE {$this->table} SET featured = 1 WHERE id = ?", [$id]);
    }

    /**
     * Retire la mise en avant du vin (featured=0).
     *
     * @param int $id Identifiant du vin
     * @return void
     */
    public function unsetFeatured(int $id): void
    {
        $this->db->execute("UPDATE {$this->table} SET featured = 0 WHERE id = ?", [$id]);
    }

    /**
     * Retourne le vin actuellement featured=1, ou null.
     *
     * @return array<string, mixed>|null
     */
    public function getCurrentFeatured(): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, label_name, vintage FROM {$this->table} WHERE featured = 1 LIMIT 1"
        );
        return $row ?: null;
    }

    /**
     * Retourne les vins similaires à un vin donné.
     * Même couleur en priorité (prix ±30%), complète avec d'autres couleurs si besoin.
     *
     * @param int    $wineId Identifiant du vin de référence
     * @param string $color  Couleur du vin de référence
     * @param float  $price  Prix du vin de référence
     * @param int    $limit  Nombre maximum de résultats
     * @return array<int, array<string, mixed>>
     */
    /** Colonnes minimales pour les vues carte vin (cross-sell, similar, cart). */
    private const CARD_COLS = 'id, label_name, wine_color, vintage, slug, price, image_path, stock_quantity, is_cuvee_speciale';

    public function getSimilar(int $wineId, string $color, float $price, int $limit = 3): array
    {
        $cols = self::CARD_COLS;

        $sameColor = $this->db->fetchAll(
            "SELECT {$cols}
             FROM {$this->table}
             WHERE id != ? AND wine_color = ? AND available = 1
             ORDER BY ABS(price - ?) ASC
             LIMIT ?",
            [$wineId, $color, $price, $limit]
        );

        if (count($sameColor) >= $limit) {
            return $sameColor;
        }

        $remaining = $limit - count($sameColor);
        $foundIds  = array_map(fn(array $r): int => (int) $r['id'], $sameColor);
        $foundIds[] = $wineId;

        $placeholders = implode(',', array_fill(0, count($foundIds), '?'));

        $otherIds = $this->db->fetchAll(
            "SELECT id FROM {$this->table}
             WHERE wine_color != ? AND available = 1 AND id NOT IN ({$placeholders})",
            array_merge([$color], $foundIds)
        );
        shuffle($otherIds);
        $pickedOtherIds = array_column(array_slice($otherIds, 0, $remaining), 'id');

        $others = [];
        if ($pickedOtherIds !== []) {
            $phOther = implode(',', array_fill(0, count($pickedOtherIds), '?'));
            $others  = $this->db->fetchAll(
                "SELECT {$cols} FROM {$this->table} WHERE id IN ({$phOther})",
                $pickedOtherIds
            );
        }

        return array_merge($sameColor, $others);
    }

    /**
     * Retourne N vins disponibles pour le cross-sell panier, en excluant les IDs donnés.
     *
     * @param int[] $excludeIds IDs des vins déjà dans le panier
     * @param int   $limit      Nombre maximum de résultats
     * @return array<int, array<string, mixed>>
     */
    public function getForCartCrossSell(array $excludeIds, int $limit = 4): array
    {
        $cols = self::CARD_COLS;

        $where  = "WHERE available = 1";
        $params = [];
        if ($excludeIds !== []) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $where  .= " AND id NOT IN ({$placeholders})";
            $params  = $excludeIds;
        }

        $ids = $this->db->fetchAll(
            "SELECT id FROM {$this->table} {$where}",
            $params
        );
        if ($ids === []) {
            return [];
        }
        shuffle($ids);
        $pickedIds = array_column(array_slice($ids, 0, $limit), 'id');
        $ph = implode(',', array_fill(0, count($pickedIds), '?'));
        return $this->db->fetchAll(
            "SELECT {$cols} FROM {$this->table} WHERE id IN ({$ph})",
            $pickedIds
        );
    }

    /**
     * Retourne une cuvée spéciale disponible pour une couleur donnée, en excluant les IDs précisés.
     * Utilisée pour garantir au moins une cuvée spéciale dans le cross-sell panier.
     *
     * @param string $color      Couleur souhaitée
     * @param int[]  $excludeIds IDs à exclure
     * @return array<string, mixed>|null
     */
    public function getCuveeSpecialeByColor(string $color, array $excludeIds = []): ?array
    {
        $cols   = self::CARD_COLS;
        $where  = "WHERE available = 1 AND is_cuvee_speciale = 1 AND wine_color = ?";
        $params = [$color];

        if ($excludeIds !== []) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $where  .= " AND id NOT IN ({$placeholders})";
            $params  = array_merge($params, $excludeIds);
        }

        $id = $this->randomPickId($where, $params);
        if ($id === null) {
            return null;
        }
        $row = $this->db->fetchOne(
            "SELECT {$cols} FROM {$this->table} WHERE id = ?",
            [$id]
        );
        return $row ?: null;
    }
}
