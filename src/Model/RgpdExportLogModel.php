<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle pour le journal des exports RGPD Art. 20 déclenchés par les utilisateurs.
 *
 * Table append-only — aucune suppression autorisée (accountability Art. 5.2 RGPD).
 * Rétention 3 ans (délai de prescription administrative CNIL).
 */
class RgpdExportLogModel extends Model
{
    protected string $table = 'rgpd_export_logs';

    /** Durée de rétention en secondes (3 ans). */
    public const RETENTION_SECONDS = 3 * 365 * 24 * 3600;

    // ----------------------------------------------------------------
    // Écriture
    // ----------------------------------------------------------------

    /**
     * Enregistre un export RGPD dans le journal.
     *
     * @param string   $email    Email du compte ayant demandé l'export
     * @param string   $ip       Adresse IP de la requête
     * @param string   $filename Nom du fichier généré (ex : mes-donnees-2026-04-08.zip)
     * @param int|null $userId   ID du compte (null si compte supprimé entre-temps)
     * @return void
     */
    public function log(string $email, string $ip, string $filename, ?int $userId = null): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + self::RETENTION_SECONDS);

        $this->db->insert(
            "INSERT INTO {$this->table} (user_id, email, ip, filename, requested_at, expires_at)
             VALUES (?, ?, ?, ?, NOW(), ?)",
            [$userId, $email, $ip, $filename, $expiresAt]
        );
    }

    // ----------------------------------------------------------------
    // Lecture
    // ----------------------------------------------------------------

    /**
     * Retourne la liste paginée des exports avec filtres optionnels.
     *
     * @param int         $limit     Résultats par page
     * @param int         $offset    Décalage pagination
     * @param string|null $email     Filtre par email (LIKE %email%)
     * @param string|null $dateFrom  Filtre date début (Y-m-d)
     * @param string|null $dateTo    Filtre date fin (Y-m-d)
     * @return array<int, array<string, mixed>>
     */
    public function getAll(
        int $limit = 20,
        int $offset = 0,
        ?string $email = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        [$where, $params] = $this->buildFilters($email, $dateFrom, $dateTo);

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $params[]    = $limit;
        $params[]    = $offset;

        $rows = $this->db->fetchAll(
            "SELECT * FROM {$this->table}
             {$whereClause}
             ORDER BY requested_at DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return $rows ?: [];
    }

    /**
     * Compte les exports selon les filtres actifs.
     *
     * @param string|null $email    Filtre par email
     * @param string|null $dateFrom Filtre date début
     * @param string|null $dateTo   Filtre date fin
     * @return int
     */
    public function count(
        ?string $email = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int {
        [$where, $params] = $this->buildFilters($email, $dateFrom, $dateTo);

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} {$whereClause}",
            $params
        );

        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Purge les entrées dont la date d'expiration est dépassée (cron accountability).
     *
     * @return int Nombre de lignes supprimées
     */
    public function purgeExpired(): int
    {
        return (int) $this->db->execute(
            "DELETE FROM {$this->table} WHERE expires_at < NOW()"
        );
    }

    // ----------------------------------------------------------------
    // Helper privé
    // ----------------------------------------------------------------

    /**
     * Construit les clauses WHERE et les paramètres selon les filtres.
     *
     * @param string|null $email
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private function buildFilters(
        ?string $email,
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        $where  = [];
        $params = [];

        if ($email !== null && $email !== '') {
            $where[]  = 'email LIKE ?';
            $params[] = '%' . $email . '%';
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $where[]  = 'DATE(requested_at) >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo !== null && $dateTo !== '') {
            $where[]  = 'DATE(requested_at) <= ?';
            $params[] = $dateTo;
        }

        return [$where, $params];
    }
}
