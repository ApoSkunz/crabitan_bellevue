<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Accès aux données des campagnes newsletter et leurs pièces jointes.
 */
class NewsletterModel extends Model
{
    protected string $table = 'newsletters';

    private const ATTACHMENT_TABLE = 'newsletter_attachments';

    /**
     * Enregistre une nouvelle campagne et retourne son identifiant.
     *
     * @param string      $subject      Objet de la newsletter
     * @param string      $body         Corps HTML brut (avant nl2br)
     * @param string|null $imageUrl     URL publique de l'image d'en-tête
     * @param string|null $segmentLabel Segment ciblé (NULL = tous les abonnés)
     * @return int Identifiant de la campagne créée
     */
    /**
     * @param string      $subject      Objet FR
     * @param string|null $title        Titre FR optionnel
     * @param string      $body         Corps FR brut
     * @param string|null $imageUrl     URL image d'en-tête
     * @param string|null $segmentLabel Segment ciblé
     * @param string      $subjectEn    Objet EN (vide = même que FR)
     * @param string      $titleEn      Titre EN (vide = même que FR)
     * @param string      $bodyEn       Corps EN brut (vide = même que FR)
     * @return int Identifiant de la campagne créée
     */
    public function create(
        string $subject,
        ?string $title,
        string $body,
        ?string $imageUrl,
        ?string $segmentLabel = null,
        string $subjectEn = '',
        string $titleEn = '',
        string $bodyEn = ''
    ): int {
        return (int) $this->db->insert(
            "INSERT INTO {$this->table}
             (subject, title, body, image_url, segment_label, subject_en, title_en, body_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $subject,
                $title !== '' ? $title : null,
                $body,
                $imageUrl,
                $segmentLabel,
                $subjectEn !== '' ? $subjectEn : null,
                $titleEn   !== '' ? $titleEn   : null,
                $bodyEn    !== '' ? $bodyEn     : null,
            ]
        );
    }

    /**
     * Enregistre le nombre d'abonnés mis en file d'attente pour cette campagne.
     *
     * @param int $id    Identifiant de la campagne
     * @param int $count Nombre de destinataires enfilés
     */
    public function setQueuedCount(int $id, int $count): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET queued_count = ? WHERE id = ?",
            [$count, $id]
        );
    }

    /**
     * Met à jour les compteurs d'envoi après la campagne.
     *
     * @param int $id          Identifiant de la campagne
     * @param int $sentCount   Nombre d'emails envoyés avec succès
     * @param int $failedCount Nombre d'échecs
     */
    public function updateStats(int $id, int $sentCount, int $failedCount): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET sent_count = ?, failed_count = ? WHERE id = ?",
            [$sentCount, $failedCount, $id]
        );
    }

    /**
     * Enregistre une pièce jointe liée à une campagne.
     *
     * @param int    $newsletterId Identifiant de la campagne
     * @param string $originalName Nom original du fichier uploadé
     * @param string $storedPath   Chemin relatif de stockage permanent
     */
    public function saveAttachment(int $newsletterId, string $originalName, string $storedPath): void
    {
        $this->db->execute(
            "INSERT INTO " . self::ATTACHMENT_TABLE . " (newsletter_id, original_name, stored_path)
             VALUES (?, ?, ?)",
            [$newsletterId, $originalName, $storedPath]
        );
    }

    /**
     * Retourne la liste paginée des campagnes, de la plus récente à la plus ancienne.
     *
     * @param  int $limit  Nombre de lignes
     * @param  int $offset Décalage
     * @return array<int, array<string, mixed>>
     */
    public function getAll(int $limit, int $offset): array
    {
        return $this->db->fetchAll(
            "SELECT nl.id, nl.subject, nl.image_url, nl.sent_count, nl.failed_count,
                    nl.queued_count, nl.sent_at, nl.segment_label,
                    (SELECT COUNT(*) FROM newsletter_opens WHERE newsletter_id = nl.id) AS opens_count
             FROM {$this->table} nl
             ORDER BY nl.sent_at DESC
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Nombre total de campagnes.
     */
    public function count(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM {$this->table}"
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Retourne les stats d'abonnés newsletter : actifs / désabonnés / total.
     *
     * @return array{active: int, unsubscribed: int, total: int}
     */
    public function getStats(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                SUM(CASE WHEN newsletter = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN newsletter = 0 THEN 1 ELSE 0 END) AS unsubscribed,
                COUNT(*) AS total
             FROM accounts
             WHERE deleted_at IS NULL"
        );
        $row = $rows[0] ?? [];
        return [
            'active'       => (int) ($row['active']       ?? 0),
            'unsubscribed' => (int) ($row['unsubscribed'] ?? 0),
            'total'        => (int) ($row['total']        ?? 0),
        ];
    }

    /**
     * Détail d'une campagne avec ses pièces jointes.
     *
     * @return array<string, mixed>|null
     */
    public function findCampaignById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, subject, title, body, image_url, sent_count, failed_count,
                    queued_count, sent_at, segment_label,
                    subject_en, title_en, body_en
             FROM {$this->table}
             WHERE id = ?",
            [$id]
        );
        if (!$row) {
            return null;
        }

        $row['attachments'] = $this->db->fetchAll(
            "SELECT id, original_name, stored_path
             FROM " . self::ATTACHMENT_TABLE . "
             WHERE newsletter_id = ?",
            [$id]
        );

        return $row;
    }
}
