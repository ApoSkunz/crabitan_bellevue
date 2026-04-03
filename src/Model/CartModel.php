<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Gestion du panier en base de données.
 *
 * Un seul panier actif par utilisateur (contrainte UNIQUE sur user_id).
 * Le contenu est stocké en JSON : [{wine_id, qty, name, image}].
 * Les prix ne sont jamais stockés ici — ils sont calculés au checkout.
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
     * @param array<int, array<string, mixed>> $items  Liste [{wine_id, qty, name, image}]
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
               updated_at     = NOW()",
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
     * @param array<int, array<string, mixed>> $localItems    Items du cookie [{wine_id, qty, name, image}]
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
            $wineId  = (int) ($localItem['wine_id'] ?? 0);
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
                    'name'    => $localItem['name'] ?? '',
                    'image'   => $localItem['image'] ?? '',
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
}
