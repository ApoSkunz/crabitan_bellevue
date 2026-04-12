<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle pour la gestion des demandes RGPD (Art. 15-21 RGPD).
 *
 * Gère le CRUD des demandes d'exercice de droits :
 * accès (Art. 15), rectification (Art. 16), effacement (Art. 17),
 * portabilité (Art. 20), opposition (Art. 21).
 */
class RgpdRequestModel extends Model
{
    protected string $table = 'rgpd_requests';

    /** Types de droits RGPD (Art. 15 à 21). */
    public const VALID_TYPES = ['acces', 'rectification', 'effacement', 'portabilite', 'opposition'];

    /** Statuts du workflow de traitement. */
    public const VALID_STATUSES = ['nouvelle', 'en_cours', 'traitee', 'refusee'];

    /** Délai légal de traitement CNIL : 1 mois (30 jours). Alerte J-5 = 25 jours. */
    public const ALERT_DAYS = 25;

    /** Label lisible par type. */
    public const TYPE_LABELS = [
        'acces'         => 'Accès (Art. 15)',
        'rectification' => 'Rectification (Art. 16)',
        'effacement'    => 'Effacement (Art. 17)',
        'portabilite'   => 'Portabilité (Art. 20)',
        'opposition'    => 'Opposition (Art. 21)',
    ];

    /** Label lisible par statut. */
    public const STATUS_LABELS = [
        'nouvelle'  => 'Nouvelle',
        'en_cours'  => 'En cours',
        'traitee'   => 'Traitée',
        'refusee'   => 'Refusée',
    ];

    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    /**
     * Crée une nouvelle demande RGPD et retourne son ID.
     *
     * @param string      $type            Type de droit exercé (acces|rectification|effacement|portabilite|opposition)
     * @param string      $requesterEmail  Email du demandeur
     * @param string      $requesterName   Nom complet du demandeur
     * @param string|null $receivedAt      Date de réception au format 'Y-m-d H:i:s' (null = NOW())
     * @return int ID de la demande créée
     */
    public function create(
        string $type,
        string $requesterEmail,
        string $requesterName,
        ?string $receivedAt = null
    ): int {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Type RGPD invalide : {$type}");
        }

        $received = $receivedAt ?? date('Y-m-d H:i:s');

        return (int) $this->db->insert(
            "INSERT INTO {$this->table}
             (type, requester_email, requester_name, status, received_at)
             VALUES (?, ?, ?, 'nouvelle', ?)",
            [$type, $requesterEmail, $requesterName, $received]
        );
    }

    /**
     * Retourne une demande RGPD par son identifiant.
     *
     * @param int $id Identifiant de la demande
     * @return array<string, mixed>|false
     */
    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            "SELECT r.id, r.type, r.status, r.requester_email, r.requester_name,
                    r.received_at, r.treated_at, r.treated_by, r.notes,
                    a.email AS treated_by_email,
                    ai.firstname AS treated_by_firstname, ai.lastname AS treated_by_lastname,
                    ac.company_name AS treated_by_company
             FROM {$this->table} r
             LEFT JOIN accounts a ON a.id = r.treated_by
             LEFT JOIN account_individuals ai ON ai.account_id = r.treated_by
             LEFT JOIN account_companies   ac ON ac.account_id = r.treated_by
             WHERE r.id = ?",
            [$id]
        );
    }

    /**
     * Retourne la liste paginée des demandes RGPD avec filtres optionnels.
     *
     * @param int         $limit   Nombre de résultats par page
     * @param int         $offset  Décalage pagination
     * @param string|null $status  Filtre par statut
     * @param string|null $type    Filtre par type de droit
     * @return array<int, array<string, mixed>>
     */
    public function getAll(int $limit = 20, int $offset = 0, ?string $status = null, ?string $type = null): array
    {
        $where  = [];
        $params = [];

        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $where[]  = 'r.status = ?';
            $params[] = $status;
        }

        if ($type !== null && in_array($type, self::VALID_TYPES, true)) {
            $where[]  = 'r.type = ?';
            $params[] = $type;
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->db->fetchAll(
            "SELECT r.id, r.type, r.status, r.requester_email, r.requester_name,
                    r.received_at, r.treated_at, r.treated_by, r.notes,
                    ai.firstname AS treated_by_firstname, ai.lastname AS treated_by_lastname,
                    ac.company_name AS treated_by_company,
                    DATEDIFF(NOW(), r.received_at) AS days_since_received
             FROM {$this->table} r
             LEFT JOIN accounts a ON a.id = r.treated_by
             LEFT JOIN account_individuals ai ON ai.account_id = r.treated_by
             LEFT JOIN account_companies   ac ON ac.account_id = r.treated_by
             {$whereClause}
             ORDER BY r.received_at DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return $rows ?: [];
    }

    /**
     * Compte le nombre total de demandes RGPD (filtres optionnels).
     *
     * @param string|null $status Filtre par statut
     * @param string|null $type   Filtre par type
     * @return int
     */
    public function count(?string $status = null, ?string $type = null): int
    {
        $where  = [];
        $params = [];

        if ($status !== null && in_array($status, self::VALID_STATUSES, true)) {
            $where[]  = 'status = ?';
            $params[] = $status;
        }

        if ($type !== null && in_array($type, self::VALID_TYPES, true)) {
            $where[]  = 'type = ?';
            $params[] = $type;
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} {$whereClause}",
            $params
        );

        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Met à jour le statut d'une demande RGPD et enregistre le traitant.
     *
     * @param int         $id           Identifiant de la demande
     * @param string      $status       Nouveau statut (nouvelle|en_cours|traitee|refusee)
     * @param int         $treatedBy    ID admin ayant traité la demande
     * @param string|null $responseNote Note de réponse (optionnel)
     * @return bool True si la mise à jour a affecté au moins 1 ligne
     */
    public function updateStatus(int $id, string $status, int $treatedBy, ?string $responseNote = null): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return false;
        }

        $treatedAt = in_array($status, ['traitee', 'refusee'], true) ? date('Y-m-d H:i:s') : null;

        $rows = $this->db->execute(
            "UPDATE {$this->table}
             SET status = ?, treated_by = ?, treated_at = ?, response_note = ?
             WHERE id = ?",
            [$status, $treatedBy, $treatedAt, $responseNote, $id]
        );

        return $rows > 0;
    }

    /**
     * Supprime physiquement une demande RGPD (accès réservé super_admin).
     *
     * @param int $id Identifiant de la demande à supprimer
     * @return int Nombre de lignes supprimées (0 ou 1)
     */
    public function delete(int $id): int
    {
        return (int) $this->db->execute(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$id]
        );
    }

    // ----------------------------------------------------------------
    // Méthodes métier
    // ----------------------------------------------------------------

    /**
     * Retourne les demandes en alerte (> ALERT_DAYS jours sans traitement).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOverdueRequests(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id, type, requester_email, requester_name, status,
                    received_at, treated_at, treated_by, response_note, created_at,
                    DATEDIFF(NOW(), received_at) AS days_since_received
             FROM {$this->table}
             WHERE status IN ('nouvelle', 'en_cours')
               AND DATEDIFF(NOW(), received_at) >= ?
             ORDER BY received_at ASC",
            [self::ALERT_DAYS]
        );

        return $rows ?: [];
    }

    /**
     * Compte les demandes en alerte (> ALERT_DAYS jours sans traitement).
     *
     * @return int
     */
    public function countOverdue(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM {$this->table}
             WHERE status IN ('nouvelle', 'en_cours')
               AND DATEDIFF(NOW(), received_at) >= ?",
            [self::ALERT_DAYS]
        );

        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Indique si une demande dépasse le seuil d'alerte J-5 (25 jours).
     *
     * @param array<string, mixed> $request Ligne demande depuis la BDD (doit contenir received_at et status)
     * @return bool
     */
    public function isOverdue(array $request): bool
    {
        if (in_array($request['status'] ?? '', ['traitee', 'refusee'], true)) {
            return false;
        }

        $receivedAt = $request['received_at'] ?? null;
        if ($receivedAt === null) {
            return false;
        }

        $days = (int) floor((time() - strtotime((string) $receivedAt)) / 86400);

        return $days >= self::ALERT_DAYS;
    }
}
