<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * File d'attente d'envoi des newsletters (nouveau vin).
 * Permet un envoi différé par cron pour respecter les limites SMTP IONOS.
 */
class NewsletterSendQueueModel extends Model
{
    protected string $table = 'newsletter_send_queue';

    /**
     * Enfile un destinataire pour une campagne.
     *
     * @param int    $newsletterId  ID de la campagne newsletters
     * @param int    $wineId        ID du vin concerné
     * @param string $email         Adresse du destinataire
     * @param string $name          Nom affiché
     * @param string $unsubToken    Token de désabonnement
     * @param string $lang          Langue (fr|en)
     * @return void
     */
    public function enqueue(
        int $newsletterId,
        int $wineId,
        string $email,
        string $name,
        string $unsubToken,
        string $lang
    ): void {
        $this->db->execute(
            'INSERT INTO newsletter_send_queue
             (newsletter_id, wine_id, recipient_email, recipient_name, unsub_token, lang)
             VALUES (:newsletter_id, :wine_id, :email, :name, :unsub_token, :lang)',
            [
                ':newsletter_id' => $newsletterId,
                ':wine_id'       => $wineId,
                ':email'         => $email,
                ':name'          => $name,
                ':unsub_token'   => $unsubToken,
                ':lang'          => $lang,
            ]
        );
    }

    /**
     * Retourne les N prochains destinataires en attente (FIFO).
     *
     * @param int $limit Nombre maximum de lignes à retourner
     * @return array<int, array<string, mixed>>
     */
    public function getPending(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT id, newsletter_id, wine_id, recipient_email, recipient_name, unsub_token, lang
             FROM newsletter_send_queue
             WHERE status = \'pending\'
             ORDER BY id ASC
             LIMIT ' . $limit
        );
    }

    /**
     * Marque un envoi comme réussi.
     *
     * @param int $id Identifiant de la ligne queue
     * @return void
     */
    public function markSent(int $id): void
    {
        $this->db->execute(
            'UPDATE newsletter_send_queue SET status = \'sent\', sent_at = NOW() WHERE id = :id',
            [':id' => $id]
        );
    }

    /**
     * Marque un envoi comme échoué.
     *
     * @param int $id Identifiant de la ligne queue
     * @return void
     */
    public function markFailed(int $id): void
    {
        $this->db->execute(
            'UPDATE newsletter_send_queue SET status = \'failed\' WHERE id = :id',
            [':id' => $id]
        );
    }

    /**
     * Retourne le nombre de destinataires en attente.
     *
     * @return int
     */
    public function countPending(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM newsletter_send_queue WHERE status = \'pending\'');
        return $row !== false ? (int) $row['cnt'] : 0;
    }

    /**
     * Met à jour les stats sent_count/failed_count de la campagne depuis la queue.
     *
     * @param int $newsletterId
     * @return void
     */
    public function refreshCampaignStats(int $newsletterId): void
    {
        $this->db->execute(
            'UPDATE newsletters
             SET sent_count   = (SELECT COUNT(*) FROM newsletter_send_queue
                                 WHERE newsletter_id = :id1 AND status = \'sent\'),
                 failed_count = (SELECT COUNT(*) FROM newsletter_send_queue
                                 WHERE newsletter_id = :id2 AND status = \'failed\')
             WHERE id = :id3',
            [':id1' => $newsletterId, ':id2' => $newsletterId, ':id3' => $newsletterId]
        );
    }
}
