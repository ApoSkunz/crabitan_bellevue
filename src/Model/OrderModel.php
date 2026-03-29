<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class OrderModel extends Model // NOSONAR php:S1448 — regroupement intentionnel ; découpage prévu à l'audit génie logiciel
{
    protected string $table = 'orders';

    private const VALID_STATUSES = [
        'pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'return_requested',
    ];

    public const VALID_PAYMENT_METHODS = [
        'card', 'virement', 'cheque',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForAdmin(int $page, int $perPage, ?string $status, ?string $search, ?string $payment = null): array
    {
        [$where, $params] = $this->buildAdminFilters($status, $search, $payment);
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        return $this->db->fetchAll(
            "SELECT o.id, o.order_reference, o.status, o.price,
                    o.payment_method, o.ordered_at,
                    a.email,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname,
                    COALESCE(ai.lastname, '', '') AS lastname
             FROM {$this->table} o
             JOIN accounts a ON a.id = o.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             {$where}
             ORDER BY o.ordered_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countForAdmin(?string $status, ?string $search, ?string $payment = null): int
    {
        [$where, $params] = $this->buildAdminFilters($status, $search, $payment);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total
             FROM {$this->table} o
             JOIN accounts a ON a.id = o.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             {$where}",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdForAdmin(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT o.*,
                    a.email,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname,
                    COALESCE(ai.lastname, '', '') AS lastname,
                    b.firstname  AS bill_firstname, b.lastname  AS bill_lastname,
                    b.street     AS bill_street,    b.city      AS bill_city,
                    b.zip_code   AS bill_zip,       b.country   AS bill_country,
                    b.phone      AS bill_phone,
                    d.firstname  AS del_firstname,  d.lastname  AS del_lastname,
                    d.street     AS del_street,     d.city      AS del_city,
                    d.zip_code   AS del_zip,        d.country   AS del_country
             FROM {$this->table} o
             JOIN accounts a ON a.id = o.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             LEFT JOIN addresses b ON b.id = o.id_billing_address
             LEFT JOIN addresses d ON d.id = o.id_delivery_address
             WHERE o.id = ?",
            [$id]
        );
        return $row ?: null;
    }

    public function updateInvoice(int $id, string $path): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET path_invoice = ? WHERE id = ?",
            [$path, $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdForUser(int $orderId, int $userId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, order_reference, path_invoice, user_id
             FROM {$this->table}
             WHERE id = ? AND user_id = ?",
            [$orderId, $userId]
        );
        return $row ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return;
        }
        $this->db->execute(
            "UPDATE {$this->table} SET status = ? WHERE id = ?",
            [$status, $id]
        );
    }

    /**
     * @return array<string, int>  ex. ['pending' => 3, 'paid' => 12, ...]
     */
    public function countByStatus(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM {$this->table} GROUP BY status"
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }
        return $result;
    }

    /**
     * Chiffre d'affaires des N derniers jours (commandes non annulées/remboursées).
     */
    public function getRevenue(int $days = 30): float
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(price), 0) AS total
             FROM {$this->table}
             WHERE status NOT IN ('cancelled', 'refunded')
               AND ordered_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        return (float) ($row['total'] ?? 0);
    }

    /**
     * Chiffre d'affaires pour une année civile donnée (commandes non annulées/remboursées).
     */
    public function getRevenueByYear(int $year): float
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(price), 0) AS total
             FROM {$this->table}
             WHERE status NOT IN ('cancelled', 'refunded')
               AND YEAR(ordered_at) = ?",
            [$year]
        );
        return (float) ($row['total'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 8): array
    {
        return $this->db->fetchAll(
            "SELECT o.id, o.order_reference, o.status, o.price, o.ordered_at,
                    a.email,
                    COALESCE(ai.firstname, ac.company_name, '') AS firstname
             FROM {$this->table} o
             JOIN accounts a ON a.id = o.user_id
             LEFT JOIN account_individuals ai ON ai.account_id = a.id
             LEFT JOIN account_companies   ac ON ac.account_id = a.id
             ORDER BY o.ordered_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForUser(int $userId, int $page, int $perPage, ?string $period = null, ?int $year = null, ?string $status = null): array
    {
        [$where, $params] = $this->buildUserFilters($userId, $period, $year, $status);
        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;
        return $this->db->fetchAll(
            "SELECT id, order_reference, status, price, payment_method, ordered_at, path_invoice
             FROM {$this->table}
             {$where}
             ORDER BY ordered_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    public function countForUser(int $userId, ?string $period = null, ?int $year = null, ?string $status = null): int
    {
        [$where, $params] = $this->buildUserFilters($userId, $period, $year, $status);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table} {$where}",
            $params
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findDetailForUser(int $orderId, int $userId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT o.*,
                    b.civility  AS bill_civility,  b.firstname AS bill_firstname, b.lastname AS bill_lastname,
                    b.street    AS bill_street,     b.city      AS bill_city,
                    b.zip_code  AS bill_zip,        b.country   AS bill_country,  b.phone AS bill_phone,
                    d.civility  AS del_civility,    d.firstname AS del_firstname,  d.lastname AS del_lastname,
                    d.street    AS del_street,      d.city      AS del_city,
                    d.zip_code  AS del_zip,         d.country   AS del_country
             FROM {$this->table} o
             LEFT JOIN addresses b ON b.id = o.id_billing_address
             LEFT JOIN addresses d ON d.id = o.id_delivery_address
             WHERE o.id = ? AND o.user_id = ?",
            [$orderId, $userId]
        );
        return $row ?: null;
    }

    public function cancelForUser(int $orderId, int $userId): bool
    {
        $cancellable = ['pending'];
        $row = $this->db->fetchOne(
            "SELECT status FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$orderId, $userId]
        );
        if (!$row || !in_array($row['status'], $cancellable, true)) {
            return false;
        }
        $this->db->execute(
            "UPDATE {$this->table} SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ?",
            [$orderId, $userId]
        );
        return true;
    }

    public function hasActiveOrdersForUser(int $userId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table}
             WHERE user_id = ? AND status IN ('pending','paid','processing','shipped')",
            [$userId]
        );
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    /**
     * Retourne les IDs d'adresses parmi celles fournies qui sont liées à une commande active.
     *
     * @param  array<int, int>  $addressIds
     * @return array<int, int>
     */
    public function getAddressIdsWithActiveOrders(array $addressIds): array
    {
        if ($addressIds === []) {
            return [];
        }
        $ph   = implode(',', array_fill(0, count($addressIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT id_billing_address AS addr_id FROM {$this->table}
             WHERE id_billing_address IN ({$ph})
               AND status IN ('pending','paid','processing','shipped')
             UNION
             SELECT DISTINCT id_delivery_address FROM {$this->table}
             WHERE id_delivery_address IN ({$ph})
               AND status IN ('pending','paid','processing','shipped')",
            array_merge($addressIds, $addressIds)
        );
        return array_map('intval', array_column($rows, 'addr_id'));
    }

    public function hasActiveOrderForAddress(int $addressId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table}
             WHERE (id_billing_address = ? OR id_delivery_address = ?)
               AND status IN ('pending','paid','processing','shipped')",
            [$addressId, $addressId]
        );
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    // ----------------------------------------------------------------
    // Statistiques CA (admin)
    // ----------------------------------------------------------------

    /**
     * Années distinctes présentes dans les commandes (hors annulées/remboursées).
     *
     * @return array<int, int>
     */
    public function getAvailableYears(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT YEAR(ordered_at) AS yr
             FROM {$this->table}
             WHERE status NOT IN ('cancelled', 'refunded')
             ORDER BY yr DESC"
        );
        return array_map('intval', array_column($rows, 'yr'));
    }

    /**
     * CA total + nombre de commandes pour une période.
     * $from/$to au format 'Y-m-d' ; null = pas de borne.
     *
     * @return array{ca: float, count: int, avg: float}
     */
    public function getStatsForPeriod(?string $from, ?string $to): array
    {
        [$where, $params] = $this->buildStatsPeriodFilter($from, $to);
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(price), 0) AS ca, COUNT(*) AS cnt
             FROM {$this->table}
             {$where}",
            $params
        );
        $ca    = (float) ($row['ca']  ?? 0);
        $count = (int)   ($row['cnt'] ?? 0);
        return [
            'ca'    => $ca,
            'count' => $count,
            'avg'   => $count > 0 ? round($ca / $count, 2) : 0.0,
        ];
    }

    /**
     * Données de graphe agrégées par jour / mois / année.
     * Retourne toujours des points consécutifs (gaps remplis à 0).
     *
     * @return array<int, array{label: string, ca: float, count: int}>
     */
    public function getChartData(?string $from, ?string $to, string $granularity): array
    {
        [$where, $params] = $this->buildStatsPeriodFilter($from, $to);

        $groupExpr = match ($granularity) {
            'daily'  => "DATE(ordered_at)",
            'yearly' => "YEAR(ordered_at)",
            default  => "DATE_FORMAT(ordered_at, '%Y-%m')",
        };

        $rows = $this->db->fetchAll(
            "SELECT {$groupExpr} AS label,
                    COALESCE(SUM(price), 0) AS ca,
                    COUNT(*) AS count
             FROM {$this->table}
             {$where}
             GROUP BY {$groupExpr}
             ORDER BY {$groupExpr}",
            $params
        );

        // Normalisation des types
        $indexed = [];
        foreach ($rows as $r) {
            $indexed[(string) $r['label']] = [
                'label' => (string) $r['label'],
                'ca'    => (float)  $r['ca'],
                'count' => (int)    $r['count'],
            ];
        }

        // Remplissage des gaps
        return $this->fillChartGaps($indexed, $from, $to, $granularity);
    }

    /**
     * @param  array<string, array{label: string, ca: float, count: int}>  $indexed
     * @return array<int, array{label: string, ca: float, count: int}>
     */
    private function fillChartGaps(array $indexed, ?string $from, ?string $to, string $granularity): array
    {
        $empty = ['ca' => 0.0, 'count' => 0];

        if ($granularity === 'yearly') {
            return $this->fillChartGapsYearly($indexed, $empty);
        }

        if ($from === null || $to === null) {
            return array_values($indexed);
        }

        return $this->fillChartGapsByDate($indexed, $empty, $from, $to, $granularity);
    }

    /**
     * @param  array<string, array{label: string, ca: float, count: int}>  $indexed
     * @param  array{ca: float, count: int}                                 $empty
     * @return array<int, array{label: string, ca: float, count: int}>
     */
    private function fillChartGapsYearly(array $indexed, array $empty): array
    {
        $years = $this->getAvailableYears();
        if ($years === []) {
            return [];
        }

        $result = [];
        $min    = min($years);
        $max    = max($years);
        for ($y = $min; $y <= $max; $y++) {
            $label    = (string) $y;
            $result[] = array_merge(['label' => $label], $indexed[$label] ?? $empty);
        }
        return $result;
    }

    /**
     * @param  array<string, array{label: string, ca: float, count: int}>  $indexed
     * @param  array{ca: float, count: int}                                 $empty
     * @return array<int, array{label: string, ca: float, count: int}>
     */
    private function fillChartGapsByDate(array $indexed, array $empty, string $from, string $to, string $granularity): array
    {
        $result   = [];
        $start    = new \DateTime($from);
        $end      = new \DateTime($to);

        if ($granularity === 'daily') {
            $cur = clone $start;
            while ($cur <= $end) {
                $label    = $cur->format('Y-m-d');
                $result[] = array_merge(['label' => $label], $indexed[$label] ?? $empty);
                $cur->modify('+1 day');
            }
        } else {
            // monthly
            $cur      = new \DateTime($start->format('Y-m-01'));
            $endMonth = new \DateTime($end->format('Y-m-01'));
            while ($cur <= $endMonth) {
                $label    = $cur->format('Y-m');
                $result[] = array_merge(['label' => $label], $indexed[$label] ?? $empty);
                $cur->modify('+1 month');
            }
        }

        return $result;
    }

    /** @return array{string, array<int, mixed>} */
    private function buildStatsPeriodFilter(?string $from, ?string $to): array
    {
        $conds  = ["status NOT IN ('cancelled', 'refunded')"];
        $params = [];
        if ($from !== null) {
            $conds[]  = 'ordered_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $conds[]  = 'ordered_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        return ['WHERE ' . implode(' AND ', $conds), $params]; // NOSONAR php:S1192 — littéraux SQL naturels, constantes sans valeur ajoutée
    }

    /**
     * @return array<int, int>  Liste des années distinctes pour un utilisateur
     */
    public function getAvailableYearsForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT YEAR(ordered_at) AS yr FROM {$this->table}
             WHERE user_id = ? ORDER BY yr DESC",
            [$userId]
        );
        return array_column($rows, 'yr');
    }

    /** @return array{string, array<int, mixed>} */
    private function buildUserFilters(int $userId, ?string $period, ?int $year, ?string $status = null): array
    {
        $conds  = ['user_id = ?'];
        $params = [$userId];

        if ($period === '3months') {
            $conds[]  = 'ordered_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)';
        } elseif ($period === 'year' && $year !== null) {
            $conds[]  = 'YEAR(ordered_at) = ?';
            $params[] = $year;
        }

        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $conds[]  = 'status = ?';
            $params[] = $status;
        }

        return ['WHERE ' . implode(' AND ', $conds), $params];
    }

    /** @return array{string, array<int, mixed>} */
    private function buildAdminFilters(?string $status, ?string $search, ?string $payment = null): array
    {
        $conds  = [];
        $params = [];

        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $conds[]  = 'o.status = ?';
            $params[] = $status;
        }

        if ($payment !== null && in_array($payment, self::VALID_PAYMENT_METHODS, true)) {
            $conds[]  = 'o.payment_method = ?';
            $params[] = $payment;
        }

        if ($search !== null && $search !== '') {
            $conds[]  = '(a.email LIKE ? OR o.order_reference LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where = $conds !== [] ? 'WHERE ' . implode(' AND ', $conds) : '';
        return [$where, $params];
    }
}
