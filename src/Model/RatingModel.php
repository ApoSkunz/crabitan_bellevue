<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Modèle de gestion des avis et notes des vins.
 *
 * La table wine_ratings stocke les notes individuelles (1–5).
 * Une note n'est affichée que si le vin totalise au moins 2 avis.
 */
class RatingModel extends Model
{
    protected string $table = 'wine_ratings';

    /** Nombre minimum d'avis pour afficher une note moyenne. */
    private const MIN_REVIEWS = 2;

    /**
     * Insère ou met à jour la note d'un client pour un vin.
     * Contrainte UNIQUE sur (account_id, wine_id) — INSERT ON DUPLICATE KEY UPDATE.
     *
     * @param int $accountId Identifiant du compte
     * @param int $wineId    Identifiant du vin
     * @param int $orderId   Identifiant de la commande
     * @param int $rating    Note entre 1 et 5
     * @return void
     */
    public function upsert(int $accountId, int $wineId, int $orderId, int $rating): void
    {
        $this->db->execute(
            "INSERT INTO {$this->table} (account_id, wine_id, order_id, rating)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               rating    = VALUES(rating),
               order_id  = VALUES(order_id),
               updated_at = NOW()",
            [$accountId, $wineId, $orderId, $rating]
        );
    }

    /**
     * Retourne la note moyenne d'un vin (null si moins de 2 avis).
     *
     * @param int $wineId Identifiant du vin
     * @return float|null Moyenne ou null si moins de 2 avis
     */
    public function getAvgByWine(int $wineId): ?float
    {
        $row = $this->db->fetchOne(
            "SELECT AVG(rating) AS avg, COUNT(*) AS cnt
             FROM {$this->table}
             WHERE wine_id = ? AND rating > 0",
            [$wineId]
        );

        if (!$row || (int) $row['cnt'] < self::MIN_REVIEWS) {
            return null;
        }

        return round((float) $row['avg'], 2);
    }

    /**
     * Retourne le nombre total de notes pour un vin.
     *
     * @param int $wineId Identifiant du vin
     * @return int
     */
    public function countByWine(int $wineId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} WHERE wine_id = ? AND rating > 0",
            [$wineId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Retourne la note d'un compte pour un vin, ou null.
     *
     * @param int $accountId Identifiant du compte
     * @param int $wineId    Identifiant du vin
     * @return array<string, mixed>|null
     */
    public function getByAccountAndWine(int $accountId, int $wineId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, account_id, wine_id, order_id, rating, token, token_used_at, created_at, updated_at
             FROM {$this->table} WHERE account_id = ? AND wine_id = ?",
            [$accountId, $wineId]
        );
        return $row ?: null;
    }

    /**
     * Cherche un enregistrement par token email (usage unique).
     * Retourne null si token inconnu ou déjà utilisé.
     *
     * @param string $token Token email
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        // VULN-008 : token valide 90 jours après génération (token_created_at)
        $row = $this->db->fetchOne(
            "SELECT id, account_id, wine_id, order_id, rating, token, token_used_at, created_at, updated_at
             FROM {$this->table}
             WHERE token = ?
               AND token_used_at IS NULL
               AND token_created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)",
            [$token]
        );
        return $row ?: null;
    }

    /**
     * Marque un token email comme utilisé.
     *
     * @param string $token Token email
     * @return void
     */
    public function markTokenUsed(string $token): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET token_used_at = NOW() WHERE token = ?",
            [$token]
        );
    }

    /**
     * Génère et stocke un token email pour (account_id, wine_id, order_id).
     * Crée la ligne en BDD si elle n'existe pas, met à jour le token si elle existe.
     * Retourne le token généré.
     *
     * @param int $accountId Identifiant du compte
     * @param int $wineId    Identifiant du vin
     * @param int $orderId   Identifiant de la commande
     * @return string Token hexadécimal de 64 caractères
     */
    public function generateEmailToken(int $accountId, int $wineId, int $orderId): string
    {
        $token = bin2hex(random_bytes(32));

        // VULN-008 : token_created_at tracé pour l'expiration 90j
        $this->db->execute(
            "INSERT INTO {$this->table} (account_id, wine_id, order_id, rating, token, token_used_at, token_created_at)
             VALUES (?, ?, ?, NULL, ?, NULL, NOW())
             ON DUPLICATE KEY UPDATE
               token            = VALUES(token),
               token_used_at    = NULL,
               token_created_at = NOW(),
               updated_at       = NOW()",
            [$accountId, $wineId, $orderId, $token]
        );

        return $token;
    }

    /**
     * Vérifie si un compte a commandé un vin (commande paid/processing/shipped/delivered).
     * Requête sur orders avec JSON_CONTAINS sur le champ content.
     *
     * @param int $accountId Identifiant du compte
     * @param int $wineId    Identifiant du vin
     * @return bool
     */
    public function hasOrdered(int $accountId, int $wineId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT 1 FROM orders
             WHERE user_id = ?
               AND status IN ('paid','processing','shipped','delivered')
               AND JSON_CONTAINS(content, JSON_OBJECT('wine_id', CAST(? AS UNSIGNED)))
             LIMIT 1",
            [$accountId, $wineId]
        );
        return (bool) $row;
    }

    /**
     * Retourne les notes moyennes pour un ensemble de wine_ids en une seule requête.
     * Les vins ayant moins de MIN_REVIEWS avis reçoivent null.
     *
     * @param  int[]                 $wineIds Liste des identifiants de vins
     * @return array<int, float|null>         Tableau [wine_id => avg|null]
     */
    public function getAvgBulk(array $wineIds): array
    {
        if ($wineIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($wineIds), '?'));

        $rows = $this->db->fetchAll(
            "SELECT wine_id, AVG(rating) AS avg, COUNT(*) AS cnt
             FROM {$this->table}
             WHERE wine_id IN ({$placeholders}) AND rating > 0
             GROUP BY wine_id",
            $wineIds
        );

        // Initialise tout à null
        $result = array_fill_keys($wineIds, null);

        foreach ($rows as $row) {
            if ((int) $row['cnt'] >= self::MIN_REVIEWS) {
                $result[(int) $row['wine_id']] = round((float) $row['avg'], 1);
            }
        }

        return $result;
    }

    /**
     * Génère un token unique pour une commande (identifie la commande dans l'email).
     * Stocké dans la table order_rating_tokens.
     * Si un token existe déjà pour ce (order_id, account_id), le retourne tel quel.
     *
     * @param int $orderId   Identifiant de la commande
     * @param int $accountId Identifiant du compte
     * @return string Token hexadécimal de 64 caractères
     */
    public function generateOrderToken(int $orderId, int $accountId): string
    {
        $existing = $this->db->fetchOne(
            "SELECT token FROM order_rating_tokens WHERE order_id = ? AND account_id = ?",
            [$orderId, $accountId]
        );

        if ($existing !== false && isset($existing['token'])) {
            return (string) $existing['token'];
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

        $this->db->execute(
            "INSERT INTO order_rating_tokens (order_id, account_id, token, expires_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE token = token",
            [$orderId, $accountId, $token, $expiresAt]
        );

        return $token;
    }

    /**
     * Résout un order token — retourne [order_id, account_id] ou null si invalide.
     *
     * @param string $token Token de commande
     * @return array<string, mixed>|null
     */
    public function resolveOrderToken(string $token): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT order_id, account_id FROM order_rating_tokens WHERE token = ?",
            [$token]
        );
        return $row ?: null;
    }

    /**
     * Résout un order token complet — retourne [order_id, account_id, expires_at] ou null.
     * Utilisé pour vérifier l'expiration côté ReviewController.
     *
     * @param string $token Token de commande
     * @return array<string, mixed>|null
     */
    public function resolveOrderTokenFull(string $token): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT order_id, account_id, expires_at FROM order_rating_tokens WHERE token = ?",
            [$token]
        );
        return $row ?: null;
    }

    /**
     * Top N vins par note moyenne (>= 2 avis minimum).
     *
     * @param int $limit Nombre maximum de résultats
     * @return array<int, array<string, mixed>>
     */
    public function getTopRated(int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT wine_id, AVG(rating) AS avg_rating, COUNT(*) AS rating_count
             FROM {$this->table}
             WHERE rating > 0
             GROUP BY wine_id
             HAVING COUNT(*) >= 2
             ORDER BY avg_rating DESC, rating_count DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Retourne toutes les notes d'un compte avec les infos du vin associé.
     *
     * Utilisé pour la page "Mes notes" de l'espace compte.
     *
     * @param int $accountId Identifiant du compte
     * @return array<int, array<string, mixed>>
     */
    public function getAllByAccount(int $accountId): array
    {
        return $this->db->fetchAll(
            "SELECT wr.wine_id, wr.rating, wr.created_at, wr.updated_at,
                    w.label_name, w.vintage, w.slug, w.image_path
               FROM {$this->table} wr
               JOIN wines w ON w.id = wr.wine_id
              WHERE wr.account_id = ?
                AND wr.rating > 0
              ORDER BY wr.updated_at DESC, wr.created_at DESC",
            [$accountId]
        );
    }

    /**
     * Retourne tous les vins commandés par un compte (distincts), avec leur note éventuelle.
     *
     * Utilisé pour proposer la notation de tous les vins achetés depuis le compte.
     *
     * @param int $accountId Identifiant du compte
     * @return array<int, array<string, mixed>>
     */
    public function getOrderedWinesWithRating(int $accountId): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT
                    w.id AS wine_id, w.label_name, w.vintage, w.slug, w.image_path,
                    wr.rating, wr.updated_at
               FROM orders o
               JOIN JSON_TABLE(o.content, '\$[*]' COLUMNS (
                    wine_id INT PATH '\$.wine_id'
               )) jt ON TRUE
               JOIN wines w ON w.id = jt.wine_id
               LEFT JOIN {$this->table} wr ON wr.wine_id = w.id AND wr.account_id = ?
              WHERE o.user_id = ?
                AND o.status NOT IN ('cancelled', 'refunded')
              ORDER BY wr.rating IS NULL DESC, w.label_name, w.vintage",
            [$accountId, $accountId]
        );
    }
}
