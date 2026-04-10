<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Accès aux données des documents légaux versionnés en BDD.
 *
 * Remplace le fichier statique `config/legal.php` comme source de vérité.
 */
class LegalDocumentModel extends Model
{
    protected string $table = 'legal_documents';

    /**
     * Retourne tous les documents indexés par slug.
     *
     * @param string            $where  Ignoré — clause WHERE non applicable ici
     * @param array<int, mixed> $params Ignoré — clause WHERE non applicable ici
     * @return array<string, array<string, mixed>>
     */
    public function findAll(string $where = '', array $params = []): array // NOSONAR — php:S1172 : signature imposée par Core\Model
    {
        $rows = $this->db->fetchAll(
            "SELECT slug, label, version, updated_at, requires_notification,
                    last_notified_at, notified_count, updated_by, created_at
             FROM {$this->table}
             ORDER BY slug ASC"
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['slug']] = $row;
        }
        return $indexed;
    }

    /**
     * Retourne un document par son slug, ou null s'il n'existe pas.
     *
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, slug, label, version, updated_at, requires_notification,
                    last_notified_at, notified_count, updated_by, created_at
             FROM {$this->table}
             WHERE slug = ?",
            [$slug]
        );
        return $row !== false ? $row : null;
    }

    /**
     * Met à jour la version et la date d'un document légal.
     *
     * @param string $slug      Identifiant unique du document
     * @param string $version   Nouveau numéro de version (format MAJEUR.MINEUR)
     * @param string $updatedAt Date de mise à jour (format Y-m-d)
     * @param int    $adminId   Identifiant de l'admin effectuant la modification
     * @return bool True si une ligne a été modifiée
     */
    public function update(string $slug, string $version, string $updatedAt, int $adminId): bool
    {
        $rows = $this->db->execute(
            "UPDATE {$this->table}
             SET version = ?, updated_at = ?, updated_by = ?
             WHERE slug = ?",
            [$version, $updatedAt, $adminId, $slug]
        );
        return $rows > 0;
    }

    /**
     * Retourne les comptes éligibles à la notification pour un document donné.
     *
     * - cgv : comptes avec au moins une commande
     * - politique_confidentialite : tous les comptes vérifiés actifs
     * - Autres : aucun (tableau vide)
     *
     * @param string $slug Identifiant du document légal
     * @return array<int, array<string, mixed>>
     */
    public function findEligibleAccounts(string $slug): array
    {
        if ($slug === 'cgv') {
            return $this->db->fetchAll(
                "SELECT a.id, a.email, a.lang,
                        COALESCE(TRIM(CONCAT(ai.firstname, ' ', ai.lastname)), a.email) AS display_name
                 FROM accounts a
                 LEFT JOIN account_individuals ai ON ai.account_id = a.id
                 WHERE a.email_verified_at IS NOT NULL
                   AND a.deleted_at IS NULL
                   AND EXISTS (SELECT 1 FROM orders WHERE orders.user_id = a.id)"
            );
        }

        if ($slug === 'politique_confidentialite') {
            return $this->db->fetchAll(
                "SELECT a.id, a.email, a.lang,
                        COALESCE(TRIM(CONCAT(ai.firstname, ' ', ai.lastname)), a.email) AS display_name
                 FROM accounts a
                 LEFT JOIN account_individuals ai ON ai.account_id = a.id
                 WHERE a.email_verified_at IS NOT NULL
                   AND a.deleted_at IS NULL"
            );
        }

        return [];
    }

    /**
     * Insère une entrée dans legal_notification_logs (idempotente via INSERT IGNORE).
     *
     * @param int    $docId     Identifiant du document légal
     * @param string $version   Version notifiée
     * @param int    $accountId Identifiant du compte notifié
     * @return void
     */
    public function logNotification(int $docId, string $version, int $accountId): void
    {
        $this->db->execute(
            "INSERT IGNORE INTO legal_notification_logs (legal_document_id, version, account_id)
             VALUES (?, ?, ?)",
            [$docId, $version, $accountId]
        );
    }

    /**
     * Met à jour les statistiques de notification sur le document (last_notified_at, notified_count).
     *
     * @param int $docId Identifiant du document légal
     * @param int $count Nombre d'emails envoyés avec succès
     * @return void
     */
    public function updateNotificationStats(int $docId, int $count): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET last_notified_at = NOW(), notified_count = notified_count + ?
             WHERE id = ?",
            [$count, $docId]
        );
    }

    /**
     * Insère une entrée dans legal_document_history à chaque mise à jour de version.
     *
     * @param int    $docId     Identifiant du document légal
     * @param string $version   Numéro de version archivé
     * @param string $updatedAt Date effective de la mise à jour (format Y-m-d)
     * @param int    $adminId   Identifiant de l'admin ayant effectué la MàJ
     * @return void
     */
    public function logHistory(int $docId, string $version, string $updatedAt, int $adminId): void
    {
        $this->db->execute(
            "INSERT INTO legal_document_history (legal_document_id, version, updated_at, updated_by)
             VALUES (?, ?, ?, ?)",
            [$docId, $version, $updatedAt, $adminId]
        );
    }

    /**
     * Retourne l'historique des versions d'un document, du plus récent au plus ancien.
     *
     * @param int $docId Identifiant du document légal
     * @return array<int, array<string, mixed>>
     */
    public function findHistoryByDocumentId(int $docId): array
    {
        return $this->db->fetchAll(
            "SELECT h.version, h.updated_at, h.created_at,
                    COALESCE(TRIM(CONCAT(ai.firstname, ' ', ai.lastname)), a.email) AS updated_by_name
             FROM legal_document_history h
             LEFT JOIN accounts a  ON a.id  = h.updated_by
             LEFT JOIN account_individuals ai ON ai.account_id = h.updated_by
             WHERE h.legal_document_id = ?
             ORDER BY h.created_at DESC",
            [$docId]
        );
    }
}
