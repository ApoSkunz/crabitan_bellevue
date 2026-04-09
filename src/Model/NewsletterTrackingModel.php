<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Accès aux données de tracking d'ouverture et de clic pour les campagnes newsletter.
 */
class NewsletterTrackingModel extends Model
{
    protected string $table = 'newsletter_links';

    /**
     * Enregistre un lien extrait du corps d'une campagne et retourne son identifiant.
     *
     * @param int    $newsletterId Identifiant de la campagne
     * @param string $url          URL originale du lien
     * @return int Identifiant du lien créé
     */
    public function saveLink(int $newsletterId, string $url): int
    {
        return (int) $this->db->insert(
            'INSERT INTO newsletter_links (newsletter_id, url) VALUES (?, ?)',
            [$newsletterId, $url]
        );
    }

    /**
     * Retrouve un lien par son identifiant et la campagne associée.
     *
     * @param  int                    $linkId       Identifiant du lien
     * @param  int                    $newsletterId Identifiant de la campagne
     * @return array<string, mixed>|null
     */
    public function findLink(int $linkId, int $newsletterId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT id, newsletter_id, url FROM newsletter_links WHERE id = ? AND newsletter_id = ?',
            [$linkId, $newsletterId]
        );
        return $row !== false ? $row : null;
    }

    /**
     * Enregistre une ouverture unique (INSERT IGNORE sur la contrainte d'unicité).
     *
     * @param int $newsletterId Identifiant de la campagne
     * @param int $subscriberId Identifiant du compte abonné
     * @return void
     */
    public function recordOpen(int $newsletterId, int $subscriberId): void
    {
        $this->db->execute(
            'INSERT IGNORE INTO newsletter_opens (newsletter_id, subscriber_id) VALUES (?, ?)',
            [$newsletterId, $subscriberId]
        );
    }

    /**
     * Enregistre un clic sur un lien de campagne.
     *
     * @param int      $newsletterId Identifiant de la campagne
     * @param int      $linkId       Identifiant du lien cliqué
     * @param int|null $subscriberId Identifiant de l'abonné (null si token inconnu)
     * @return void
     */
    public function recordClick(int $newsletterId, int $linkId, ?int $subscriberId): void
    {
        $this->db->execute(
            'INSERT INTO newsletter_clicks (newsletter_id, link_id, subscriber_id) VALUES (?, ?, ?)',
            [$newsletterId, $linkId, $subscriberId]
        );
    }

    /**
     * Retourne les statistiques d'ouverture et de clic pour une campagne.
     *
     * @param  int                $newsletterId Identifiant de la campagne
     * @return array<string, int> opens_count, clicks_count
     */
    public function getStats(int $newsletterId): array
    {
        $row = $this->db->fetchOne(
            'SELECT
                (SELECT COUNT(*) FROM newsletter_opens  WHERE newsletter_id = ?) AS opens_count,
                (SELECT COUNT(*) FROM newsletter_clicks WHERE newsletter_id = ?) AS clicks_count',
            [$newsletterId, $newsletterId]
        );
        return $row !== false ? $row : ['opens_count' => 0, 'clicks_count' => 0];
    }

    /**
     * Supprime les données de tracking antérieures à 13 mois (conformité RGPD).
     *
     * @return int Nombre de lignes supprimées
     */
    public function purgeOlderThan13Months(): int
    {
        $cutoff  = date('Y-m-d H:i:s', strtotime('-13 months'));
        $deleted = $this->db->execute(
            'DELETE FROM newsletter_opens WHERE opened_at < ?',
            [$cutoff]
        );
        $deleted += $this->db->execute(
            'DELETE FROM newsletter_clicks WHERE clicked_at < ?',
            [$cutoff]
        );
        return $deleted;
    }
}
