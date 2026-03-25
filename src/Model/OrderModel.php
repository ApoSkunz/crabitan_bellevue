<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class OrderModel extends Model
{
    protected string $table = 'orders';

    private const VALID_STATUSES = [
        'pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForAdmin(int $page, int $perPage, ?string $status, ?string $search): array
    {
        [$where, $params] = $this->buildAdminFilters($status, $search);
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

    public function countForAdmin(?string $status, ?string $search): int
    {
        [$where, $params] = $this->buildAdminFilters($status, $search);
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

    /** @return array{string, array<int, mixed>} */
    private function buildAdminFilters(?string $status, ?string $search): array
    {
        $conds  = [];
        $params = [];

        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $conds[]  = 'o.status = ?';
            $params[] = $status;
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
