<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * File d'attente d'envoi des newsletters (nouveau vin + campagnes admin).
 * wine_id = NULL pour les campagnes admin, INT pour les newsletters nouveau vin.
 * Permet un envoi différé par cron pour respecter les limites SMTP IONOS.
 */
class NewsletterSendQueueModel extends Model
{
    protected string $table = 'newsletter_send_queue';

    /**
     * Enfile un destinataire pour une campagne.
     *
     * @param int      $newsletterId  ID de la campagne newsletters
     * @param int|null $wineId        ID du vin (NULL pour campagnes admin)
     * @param string   $email         Adresse du destinataire
     * @param string   $name          Nom affiché
     * @param string   $unsubToken    Token de désabonnement
     * @param string   $lang          Langue (fr|en)
     * @return void
     */
    public function enqueue(
        int $newsletterId,
        ?int $wineId,
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
     * Enregistre un envoi réussi : incrémente sent_count sur la campagne et supprime la ligne queue.
     * Suppression immédiate = conformité RGPD (pas d'emails en table après traitement).
     *
     * @param int $id           Identifiant de la ligne queue
     * @param int $newsletterId ID de la campagne (pour incrément atomique)
     * @return void
     */
    public function markSent(int $id, int $newsletterId): void
    {
        $this->db->execute(
            'UPDATE newsletters SET sent_count = sent_count + 1 WHERE id = :nl_id',
            [':nl_id' => $newsletterId]
        );
        $this->db->execute(
            'DELETE FROM newsletter_send_queue WHERE id = :id',
            [':id' => $id]
        );
    }

    /**
     * Enregistre un échec d'envoi : incrémente failed_count sur la campagne et supprime la ligne queue.
     *
     * @param int $id           Identifiant de la ligne queue
     * @param int $newsletterId ID de la campagne (pour incrément atomique)
     * @return void
     */
    public function markFailed(int $id, int $newsletterId): void
    {
        $this->db->execute(
            'UPDATE newsletters SET failed_count = failed_count + 1 WHERE id = :nl_id',
            [':nl_id' => $newsletterId]
        );
        $this->db->execute(
            'DELETE FROM newsletter_send_queue WHERE id = :id',
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
}
