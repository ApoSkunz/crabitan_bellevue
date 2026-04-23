<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de journalisation des exécutions cron.
 * Rétention automatique : 30 jours.
 */
class CronRunModel extends Model
{
    protected string $table = 'cron_runs';

    /**
     * Démarre un enregistrement de run cron.
     *
     * @param string $jobName Nom du job (ex: 'purge_accounts', 'send_newsletter_queue')
     * @param bool   $dryRun  Exécution à blanc ou réelle
     * @return int ID du run créé
     */
    public function start(string $jobName, bool $dryRun = false): int
    {
        $id = $this->db->insert(
            'INSERT INTO cron_runs (job_name, started_at, status, dry_run)
             VALUES (:job, NOW(), :status, :dry)',
            [':job' => $jobName, ':status' => 'running', ':dry' => $dryRun ? 1 : 0]
        );

        // Purge entrées > 30 jours
        $this->db->execute('DELETE FROM cron_runs WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');

        return (int) $id;
    }

    /**
     * Termine un run avec succès.
     *
     * @param int    $runId        ID du run
     * @param int    $actionsCount Nombre d'actions effectuées
     * @param string $logOutput    Extrait du log
     */
    public function finishSuccess(int $runId, int $actionsCount, string $logOutput): void
    {
        $this->db->execute(
            'UPDATE cron_runs
             SET finished_at = NOW(),
                 duration_ms = TIMESTAMPDIFF(MICROSECOND, started_at, NOW()) DIV 1000,
                 status = :status,
                 actions_count = :count,
                 log_output = :log
             WHERE id = :id',
            [
                ':status' => 'success',
                ':count'  => max(0, $actionsCount),
                ':log'    => mb_substr($logOutput, 0, 8000),
                ':id'     => $runId,
            ]
        );
    }

    /**
     * Termine un run en erreur.
     */
    public function finishError(int $runId, string $errorMessage, string $logOutput = ''): void
    {
        $this->db->execute(
            'UPDATE cron_runs
             SET finished_at = NOW(),
                 duration_ms = TIMESTAMPDIFF(MICROSECOND, started_at, NOW()) DIV 1000,
                 status = :status,
                 log_output = :log,
                 error_message = :err
             WHERE id = :id',
            [
                ':status' => 'error',
                ':log'    => mb_substr($logOutput, 0, 8000),
                ':err'    => mb_substr($errorMessage, 0, 500),
                ':id'     => $runId,
            ]
        );
    }

    /**
     * Retourne le total pour pagination.
     */
    public function countAll(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM cron_runs');
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Retourne une page de runs triée par date décroissante.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPage(int $limit, int $offset): array
    {
        $safeLimit  = max(1, $limit);
        $safeOffset = max(0, $offset);

        return $this->db->fetchAll(
            "SELECT * FROM cron_runs ORDER BY started_at DESC LIMIT {$safeLimit} OFFSET {$safeOffset}"
        );
    }

    /**
     * Résumé par job sur les 24 dernières heures.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSummary24h(): array
    {
        return $this->db->fetchAll(
            "SELECT job_name,
                    COUNT(*) AS runs,
                    SUM(status='success') AS success,
                    SUM(status='error') AS errors,
                    SUM(actions_count) AS actions_total,
                    AVG(duration_ms) AS avg_duration_ms,
                    MAX(started_at) AS last_run
             FROM cron_runs
             WHERE started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY job_name
             ORDER BY last_run DESC"
        );
    }
}
