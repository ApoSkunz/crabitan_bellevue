<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de journalisation et monitoring des emails envoyés.
 * Rétention automatique : 25 heures (purge après chaque INSERT).
 */
class EmailRateLogModel extends Model
{
    protected string $table = 'email_rate_log';

    /**
     * Enregistre un envoi d'email et purge les entrées de plus de 25h.
     *
     * @param string      $fromAddress  Adresse expéditrice (MAIL_USER du canal)
     * @param string      $toAddress    Adresse destinataire
     * @param string      $subject      Sujet de l'email
     * @param bool        $success      true si envoi réussi, false sinon
     * @param string|null $errorMessage Message d'erreur en cas d'échec
     * @param string      $type         Catégorie d'email (contact, register, reset_password, cron_*, other)
     * @return void
     */
    public function log(
        string $fromAddress,
        string $toAddress,
        string $subject,
        bool $success,
        ?string $errorMessage,
        string $type = 'other'
    ): void {
        $this->db->execute(
            'INSERT INTO email_rate_log (from_address, to_address, subject, type, success, error_message)
             VALUES (:from, :to, :subject, :type, :success, :error)',
            [
                ':from'    => $fromAddress,
                ':to'      => $toAddress,
                ':subject' => mb_substr($subject, 0, 500),
                ':type'    => mb_substr($type, 0, 40),
                ':success' => $success ? 1 : 0,
                ':error'   => $errorMessage !== null ? mb_substr($errorMessage, 0, 500) : null,
            ]
        );

        // Purge auto — entrées de plus de 25h
        $this->db->execute('DELETE FROM email_rate_log WHERE sent_at < DATE_SUB(NOW(), INTERVAL 25 HOUR)');
    }

    /**
     * Retourne un agrégat par adresse expéditrice sur les 25 dernières heures.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSummary(): array
    {
        return $this->db->fetchAll(
            'SELECT from_address,
                    COUNT(*) AS total_sent,
                    SUM(success=1) AS success_count,
                    SUM(success=0) AS error_count,
                    MIN(sent_at) AS first_at,
                    MAX(sent_at) AS last_at
             FROM email_rate_log
             WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 25 HOUR)
             GROUP BY from_address
             ORDER BY total_sent DESC'
        );
    }

    /**
     * Retourne le nombre total de logs (pour pagination).
     */
    public function countAll(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM email_rate_log');
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Retourne une page de logs triés par date décroissante.
     *
     * @param int $limit  Nombre d'entrées par page
     * @param int $offset Décalage
     * @return array<int, array<string, mixed>>
     */
    public function getPage(int $limit, int $offset): array
    {
        $safeLimit  = max(1, $limit);
        $safeOffset = max(0, $offset);

        return $this->db->fetchAll(
            "SELECT * FROM email_rate_log ORDER BY sent_at DESC LIMIT {$safeLimit} OFFSET {$safeOffset}"
        );
    }

    /**
     * Retourne les N derniers logs triés par date décroissante.
     *
     * @param int $limit Nombre maximum d'entrées (défaut : 100)
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLogs(int $limit = 100): array
    {
        return $this->getPage($limit, 0);
    }
}
