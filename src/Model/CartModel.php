<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Gestion du panier en base de données.
 *
 * Un seul panier actif par utilisateur (contrainte UNIQUE sur user_id).
 * Le contenu est stocké en JSON : [{wine_id, qty}] — nom, image et prix
 * sont récupérés depuis WineModel à la demande via /api/cart/details.
 */
class CartModel extends Model
{
    protected string $table = 'carts';

    /**
     * Retourne le panier d'un utilisateur ou false s'il n'existe pas.
     *
     * @param int $userId Identifiant de l'utilisateur
     * @return array<string, mixed>|false
     */
    public function findByUserId(int $userId): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Crée ou met à jour (upsert) le panier d'un utilisateur.
     *
     * Calcule automatiquement total_quantity comme somme des qtés.
     * Les prix sont laissés à 0.00 — calculés au checkout.
     *
     * @param int                          $userId Identifiant de l'utilisateur
     * @param array<int, array<string, mixed>> $items  Liste [{wine_id, qty}]
     * @return void
     */
    public function save(int $userId, array $items): void
    {
        $totalQty   = array_sum(array_column($items, 'qty'));
        $contentJson = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);

        $this->db->execute(
            "INSERT INTO {$this->table} (user_id, content, total_quantity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
               content        = VALUES(content),
               total_quantity = VALUES(total_quantity),
               updated_at     = NOW()", // phpcs:ignore Generic.Files.LineLength
            [$userId, $contentJson, $totalQty]
        );
    }

    /**
     * Vide le contenu du panier (remet à zéro sans supprimer la ligne).
     *
     * @param int $userId Identifiant de l'utilisateur
     * @return void
     */
    public function clear(int $userId): void
    {
        $this->db->execute(
            "UPDATE {$this->table}
             SET content = '[]', total_quantity = 0, updated_at = NOW()
             WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Décode et retourne les articles du panier depuis la ligne BDD.
     *
     * @param array<string, mixed> $row Ligne retournée par findByUserId()
     * @return array<int, array<string, mixed>>
     */
    public function getContent(array $row): array
    {
        $decoded = json_decode((string) ($row['content'] ?? '[]'), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Fusionne un panier cookie (local) avec le panier en base de données.
     *
     * Règles :
     * - Les quantités sont cumulées (additive).
     * - Chaque article est plafonné au stock disponible via $stockResolver.
     * - Les articles du cookie enrichissent le panier BDD (name, image du cookie si absent en BDD).
     *
     * @param int                          $userId        Identifiant de l'utilisateur
     * @param array<int, array<string, mixed>> $localItems    Items du cookie [{id, qty}] (clé "id" utilisée par le JS)
     * @param callable(int):int            $stockResolver Callable retournant le stock pour un wine_id donné
     * @return void
     */
    public function mergeLocalCart(int $userId, array $localItems, callable $stockResolver): void
    {
        $existing = $this->findByUserId($userId);
        $dbItems  = $existing !== false ? $this->getContent($existing) : [];

        // Indexer les items BDD par wine_id pour fusion rapide
        $indexed = [];
        foreach ($dbItems as $item) {
            $wineId = (int) $item['wine_id'];
            $indexed[$wineId] = $item;
        }

        // Fusionner les items locaux dans l'index
        foreach ($localItems as $localItem) {
            // Le cookie JS utilise la clé "id", la BDD utilise "wine_id" — on accepte les deux
            $wineId   = (int) ($localItem['wine_id'] ?? $localItem['id'] ?? 0);
            $localQty = (int) ($localItem['qty'] ?? 0);

            if ($wineId <= 0 || $localQty <= 0) {
                continue;
            }

            $stock = $stockResolver($wineId);

            if (isset($indexed[$wineId])) {
                $merged = (int) $indexed[$wineId]['qty'] + $localQty;
                $indexed[$wineId]['qty'] = min($merged, $stock);
            } else {
                $indexed[$wineId] = [
                    'wine_id' => $wineId,
                    'qty'     => min($localQty, $stock),
                ];
            }
        }

        // Supprimer les articles avec qty ≤ 0 (stock épuisé après plafonnement)
        $merged = array_values(array_filter(
            $indexed,
            fn(array $item): bool => (int) $item['qty'] > 0
        ));

        $this->save($userId, $merged);
    }

    /**
     * Supprime du panier les articles dont le vin est indisponible ou hors stock.
     *
     * Chaque article est vérifié via $wineData(wine_id) qui retourne les données du vin ou null.
     * Les articles dont le vin est introuvable, non disponible ou en quantité nulle sont retirés.
     * Les quantités sont plafonnées au stock restant.
     *
     * @param int      $userId    Identifiant de l'utilisateur
     * @param callable $wineData  callable(int $wineId): ?array — retourne la ligne du vin ou null
     * @return array<int, array<string, mixed>>  Articles retirés du panier
     */
    public function removeUnavailableItems(int $userId, callable $wineData): array
    {
        $row = $this->findByUserId($userId);
        if ($row === false) {
            return [];
        }

        $items   = $this->getContent($row);
        $kept    = [];
        $removed = [];
        $changed = false;

        foreach ($items as $item) {
            $wineId = (int) ($item['wine_id'] ?? 0);
            $wine   = $wineData($wineId);

            if ($wine === null || !(bool) $wine['available']) {
                $removed[] = $item;
                $changed   = true;
                continue;
            }

            $qty = min((int) $item['qty'], (int) $wine['stock_quantity']);
            if ($qty !== (int) $item['qty']) {
                $changed = true;
            }
            $kept[] = array_merge($item, ['qty' => $qty]);
        }

        if ($changed) {
            $this->save($userId, $kept);
        }

        return $removed;
    }

    /**
     * Retourne les paniers abandonnés dont la dernière mise à jour se situe entre hoursMin et hoursMax heures.
     *
     * Seuls les paniers non vides (total_quantity > 0) associés à un utilisateur connecté
     * (user_id non null) sont retournés. Ce critère cible les paniers J+3 par défaut.
     *
     * @param int $hoursMin Borne inférieure en heures (incluse)
     * @param int $hoursMax Borne supérieure en heures (exclue)
     * @return array<int, array<string, mixed>>
     */
    public function getAbandoned(int $hoursMin = 72, int $hoursMax = 96): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table}
             WHERE user_id IS NOT NULL
               AND total_quantity > 0
               AND reminder_sent_at IS NULL
               AND updated_at <= NOW() - INTERVAL ? HOUR
               AND updated_at >  NOW() - INTERVAL ? HOUR",
            [$hoursMin, $hoursMax]
        );
    }

    /**
     * Marque un panier comme ayant reçu la relance email.
     *
     * @param int $cartId Identifiant du panier
     * @return void
     */
    public function markReminderSent(int $cartId): void
    {
        $this->db->execute(
            "UPDATE {$this->table} SET reminder_sent_at = NOW() WHERE id = ?",
            [$cartId]
        );
    }

    /**
     * Retourne les données enrichies des paniers abandonnés prêts pour l'envoi email.
     *
     * Effectue un JOIN carts → accounts, puis enrichit les items via un JOIN sur wines.
     * Chaque élément contient : cart_id, email, firstname, lang, items (enrichis), cart_url.
     *
     * @param int $hoursMin Borne inférieure en heures (incluse)
     * @param int $hoursMax Borne supérieure en heures (exclue)
     * @return array<int, array<string, mixed>>
     */
    public function getAbandonedForEmail(int $hoursMin = 72, int $hoursMax = 96): array
    {
        $rows = $this->db->fetchAll(
            "SELECT c.id AS cart_id, c.content, c.user_id,
                    a.email, a.firstname, a.company_name, a.lang
               FROM {$this->table} c
               JOIN accounts a ON a.id = c.user_id
              WHERE c.user_id IS NOT NULL
                AND c.total_quantity > 0
                AND c.reminder_sent_at IS NULL
                AND c.updated_at <= NOW() - INTERVAL ? HOUR
                AND c.updated_at >  NOW() - INTERVAL ? HOUR",
            [$hoursMin, $hoursMax]
        );

        $result = [];
        foreach ($rows as $row) {
            $rawItems = json_decode((string) ($row['content'] ?? '[]'), true);
            if (!is_array($rawItems) || empty($rawItems)) {
                continue;
            }

            $wineIds = array_filter(array_map(
                fn(array $item): int => (int) ($item['wine_id'] ?? 0),
                $rawItems
            ), fn(int $id): bool => $id > 0);

            if (empty($wineIds)) {
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($wineIds), '?'));
            $wines = $this->db->fetchAll(
                "SELECT id, label_name, vintage, image_path, price
                   FROM wines
                  WHERE id IN ({$placeholders})",
                array_values($wineIds)
            );

            $wineMap = [];
            foreach ($wines as $wine) {
                $wineMap[(int) $wine['id']] = $wine;
            }

            $enrichedItems = [];
            foreach ($rawItems as $item) {
                $wineId = (int) ($item['wine_id'] ?? 0);
                if (!isset($wineMap[$wineId])) {
                    continue;
                }
                $wine = $wineMap[$wineId];
                $enrichedItems[] = [
                    'wine_id'    => $wineId,
                    'label_name' => (string) ($wine['label_name'] ?? ''),
                    'vintage'    => (int) ($wine['vintage'] ?? 0),
                    'image_path' => (string) ($wine['image_path'] ?? ''),
                    'price'      => (float) ($wine['price'] ?? 0.0),
                    'qty'        => (int) ($item['qty'] ?? 0),
                ];
            }

            if (empty($enrichedItems)) {
                continue;
            }

            $lang = in_array((string) ($row['lang'] ?? ''), ['fr', 'en'], true)
                ? (string) $row['lang'] : 'fr';

            $result[] = [
                'cart_id'   => (int) $row['cart_id'],
                'email'     => (string) ($row['email'] ?? ''),
                'firstname' => (string) ($row['firstname'] ?? $row['company_name'] ?? 'Client'),
                'lang'      => $lang,
                'items'     => $enrichedItems,
            ];
        }

        return $result;
    }

    /**
     * Retire un vin spécifique de tous les paniers (ex. vin rendu indisponible par l'admin).
     *
     * @param int $wineId Identifiant du vin à retirer
     * @return void
     */
    public function purgeWineFromAllCarts(int $wineId): void
    {
        $rows = $this->db->fetchAll(
            "SELECT id, user_id, content FROM {$this->table} WHERE total_quantity > 0"
        );

        foreach ($rows as $row) {
            $items    = $this->getContent($row);
            $filtered = array_values(
                array_filter($items, fn(array $i): bool => (int) ($i['wine_id'] ?? 0) !== $wineId)
            );

            if (count($filtered) !== count($items)) {
                $this->save((int) $row['user_id'], $filtered);
            }
        }
    }
}
