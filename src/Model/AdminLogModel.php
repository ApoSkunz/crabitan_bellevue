<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de persistance du journal d'audit admin.
 *
 * Journal immuable — aucune méthode de suppression exposée.
 * Rétention légale recommandée : 3 ans (Art. 5.2 RGPD accountability).
 */
class AdminLogModel extends Model
{
    protected string $table = 'admin_logs';

    /**
     * Insère une nouvelle entrée dans le journal.
     *
     * @param int                  $adminId    Identifiant de l'admin ayant réalisé l'action
     * @param string               $action     Identifiant d'action (ex: order.status_updated)
     * @param string               $targetType Type de la cible (ex: order, wine, account)
     * @param int|null             $targetId   ID de la ressource ciblée (null si non applicable)
     * @param array<string, mixed> $details    Données contextuelles JSON (avant/après, etc.)
     * @param string               $ipAddress  IP de l'admin au moment de l'action
     * @return int ID de l'entrée insérée
     */
    public function insert(
        int $adminId,
        string $action,
        string $targetType,
        ?int $targetId,
        array $details,
        string $ipAddress
    ): int {
        return (int) $this->db->insert(
            "INSERT INTO {$this->table}
             (admin_id, action, target_type, target_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $adminId,
                $action,
                $targetType,
                $targetId,
                $details !== [] ? json_encode($details) : null,
                $ipAddress,
            ]
        );
    }

    /**
     * Retourne les entrées du journal avec filtres optionnels, triées par date décroissante.
     *
     * @param array{admin_id?: int, action?: string, date_from?: string, date_to?: string} $filters
     * @param int $limit  Nombre d'entrées à retourner
     * @param int $offset Décalage pour la pagination
     * @return array<int, array<string, mixed>>
     */
    public function fetchFiltered(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->buildWhere($filters);

        // LIMIT / OFFSET injectés après cast (int) : PDO + ATTR_EMULATE_PREPARES=false
        // refuse les entiers bindés en string pour ces clauses côté MySQL.
        $limit  = max(1, $limit);
        $offset = max(0, $offset);

        return $this->db->fetchAll(
            "SELECT al.*, a.email AS admin_email,
                    COALESCE(ai.firstname, ac.company_name, a.email) AS admin_name
             FROM {$this->table} al
             LEFT JOIN accounts a  ON a.id = al.admin_id
             LEFT JOIN account_individuals ai ON ai.account_id = al.admin_id
             LEFT JOIN account_companies   ac ON ac.account_id = al.admin_id
             {$where}
             ORDER BY al.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    /**
     * Compte les entrées du journal selon les filtres actifs.
     *
     * @param array{admin_id?: int, action?: string, date_from?: string, date_to?: string} $filters
     * @return int
     */
    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM {$this->table} al {$where}",
            $params
        );
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Exporte toutes les entrées filtrées au format CSV (string).
     *
     * @param array{admin_id?: int, action?: string, date_from?: string, date_to?: string} $filters
     * @return string Contenu CSV UTF-8 BOM
     */
    public function exportCsv(array $filters = []): string
    {
        [$where, $params] = $this->buildWhere($filters);

        $rows = $this->db->fetchAll(
            "SELECT al.id, al.created_at, al.admin_id,
                    COALESCE(ai.firstname, ac.company_name, a.email) AS admin_name,
                    a.email AS admin_email,
                    al.action, al.target_type, al.target_id,
                    al.details, al.ip_address
             FROM {$this->table} al
             LEFT JOIN accounts a  ON a.id = al.admin_id
             LEFT JOIN account_individuals ai ON ai.account_id = al.admin_id
             LEFT JOIN account_companies   ac ON ac.account_id = al.admin_id
             {$where}
             ORDER BY al.created_at DESC",
            $params
        );

        $handle = fopen('php://memory', 'w+');
        if ($handle === false) {
            return '';
        }

        // BOM UTF-8 pour compatibilité Excel
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['ID', 'Date', 'Admin ID', 'Admin', 'Email admin', 'Action', 'Cible', 'ID cible', 'Détails', 'IP'], ';', '"', ''); // phpcs:ignore Generic.Files.LineLength

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['id'],
                $row['created_at'],
                $row['admin_id'],
                $row['admin_name'] ?? '',
                $row['admin_email'] ?? '',
                $row['action'],
                $row['target_type'],
                $row['target_id'] ?? '',
                $row['details'] ?? '',
                $row['ip_address'],
            ], ';', '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Construit la clause WHERE et le tableau de paramètres depuis les filtres.
     *
     * @param array{admin_id?: int, action?: string, date_from?: string, date_to?: string} $filters
     * @return array{string, array<int, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['admin_id'])) {
            $conditions[] = 'al.admin_id = ?';
            $params[]     = (int) $filters['admin_id'];
        }
        if (!empty($filters['action'])) {
            $conditions[] = 'al.action = ?';
            $params[]     = (string) $filters['action'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'al.created_at >= ?';
            $params[]     = (string) $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'al.created_at <= ?';
            $params[]     = (string) $filters['date_to'] . ' 23:59:59';
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}
