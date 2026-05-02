<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion des avis clients globaux (expérience d'achat).
 *
 * Un compte ne peut laisser qu'un seul avis (UNIQUE KEY uq_account).
 * L'accès se fait via un token de commande (order_rating_tokens) valide et non expiré.
 */
class ReviewModel extends Model
{
    protected string $table = 'customer_reviews';

    /**
     * Retourne l'avis d'un compte ou null s'il n'en a pas encore laissé.
     *
     * @param int $accountId Identifiant du compte
     * @return array<string, mixed>|null
     */
    public function findByAccountId(int $accountId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, account_id, order_id, rating, comment, display_name, location, created_at
             FROM {$this->table}
             WHERE account_id = ?",
            [$accountId]
        );

        return $row ?: null;
    }

    /**
     * Crée un avis client et retourne l'identifiant inséré.
     *
     * @param int         $accountId   Identifiant du compte
     * @param int|null    $orderId     Identifiant de la commande (peut être null)
     * @param int         $rating      Note entre 1 et 5
     * @param string      $comment     Commentaire libre (déjà sanitisé)
     * @param string      $displayName Prénom + initiale nom (ex. « Sophie D. »)
     * @param string      $location    Ville / région de l'auteur
     * @return int Identifiant de la ligne insérée
     */
    public function create(
        int $accountId,
        ?int $orderId,
        int $rating,
        string $comment,
        string $displayName,
        string $location
    ): int {
        $this->db->execute(
            "INSERT INTO {$this->table}
                (account_id, order_id, rating, comment, display_name, location)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$accountId, $orderId, $rating, $comment, $displayName, $location]
        );

        $row = $this->db->fetchOne('SELECT LAST_INSERT_ID() AS id', []);

        return (int) ($row['id'] ?? 0);
    }

    /**
     * Retourne $count avis aléatoires dont la note est >= $minRating.
     *
     * @param int $count     Nombre d'avis souhaités
     * @param int $minRating Note minimum (1–5)
     * @return array<int, array<string, mixed>>
     */
    /**
     * Met à jour l'avis d'un compte (note, commentaire, ville).
     *
     * @param int    $accountId Identifiant du compte
     * @param int    $rating    Note entre 1 et 5
     * @param string $comment   Commentaire libre
     * @param string $location  Ville / région
     * @return void
     */
    public function update(int $accountId, int $rating, string $comment, string $location): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
                SET rating = ?, comment = ?, location = ?
              WHERE account_id = ?",
            [$rating, $comment, $location, $accountId]
        );
    }

    /**
     * Crée ou met à jour l'avis d'un compte depuis l'espace client (sans token).
     *
     * INSERT si aucun avis existant, UPDATE sinon.
     *
     * @param int    $accountId   Identifiant du compte
     * @param int    $rating      Note entre 1 et 5
     * @param string $comment     Commentaire libre
     * @param string $displayName Prénom + initiale nom
     * @param string $location    Ville / région
     * @return void
     */
    public function upsert(int $accountId, int $rating, string $comment, string $displayName, string $location): void
    {
        $this->db->execute(
            "INSERT INTO {$this->table} (account_id, rating, comment, display_name, location)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                rating = VALUES(rating),
                comment = VALUES(comment),
                location = VALUES(location)",
            [$accountId, $rating, $comment, $displayName, $location]
        );
    }

    public function getRandom(int $count = 3, int $minRating = 4): array
    {
        return $this->db->fetchAll(
            "SELECT id, rating, comment, display_name, location, created_at
             FROM {$this->table}
             WHERE rating >= ?
               AND TRIM(COALESCE(comment, '')) != ''
             ORDER BY RAND()
             LIMIT ?",
            [$minRating, $count]
        );
    }

    /**
     * Retourne la note moyenne et le nombre total d'avis >= minRating.
     *
     * @param int $minRating Note minimum à comptabiliser
     * @return array{avg: float, count: int}|null null si aucun avis
     */
    public function getStats(int $minRating = 1): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT ROUND(AVG(rating), 1) AS avg, COUNT(*) AS cnt
             FROM {$this->table}
             WHERE rating >= ?",
            [$minRating]
        );

        if (!$row || (int) $row['cnt'] === 0) {
            return null;
        }

        return ['avg' => (float) $row['avg'], 'count' => (int) $row['cnt']];
    }

    /**
     * Nombre total d'avis en base.
     *
     * @return int
     */
    public function countAll(): int
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM {$this->table}", []);
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Liste paginée de tous les avis, avec l'email du compte associé.
     *
     * @param int $page    Numéro de page (commence à 1)
     * @param int $perPage Nombre de résultats par page
     * @return array<int, array<string, mixed>>
     */
    public function findPaginated(int $page, int $perPage): array
    {
        $offset = (int) (($page - 1) * $perPage);
        return $this->db->fetchAll(
            "SELECT r.id, r.account_id, r.rating, r.comment, r.display_name, r.location, r.created_at,
                    a.email
               FROM {$this->table} r
               LEFT JOIN accounts a ON a.id = r.account_id
              ORDER BY r.created_at DESC
              LIMIT " . (int) $perPage . " OFFSET " . $offset,
            []
        );
    }
}
