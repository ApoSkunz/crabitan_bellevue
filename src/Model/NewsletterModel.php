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
     * @param string      $subject  Objet de la newsletter
     * @param string      $body     Corps HTML brut (avant nl2br)
     * @param string|null $imageUrl URL publique de l'image d'en-tête
     * @return int Identifiant de la campagne créée
     */
    public function create(string $subject, string $body, ?string $imageUrl): int
    {
        return (int) $this->db->insert(
            "INSERT INTO {$this->table} (subject, body, image_url)
             VALUES (?, ?, ?)",
            [$subject, $body, $imageUrl]
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
            "SELECT id, subject, image_url, sent_count, failed_count, sent_at
             FROM {$this->table}
             ORDER BY sent_at DESC
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
     * Détail d'une campagne avec ses pièces jointes.
     *
     * @return array<string, mixed>|null
     */
    public function findCampaignById(int $id): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, subject, body, image_url, sent_count, failed_count, sent_at
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
