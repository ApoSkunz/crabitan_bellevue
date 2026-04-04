<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

class PricingRuleModel extends Model
{
    protected string $table = 'pricing_rules';

    /**
     * Retourne la règle active correspondant à une quantité et un format donnés.
     *
     * @return array<string, mixed>|null
     */
    public function findForQuantity(int $qty, string $format = 'bottle'): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT min_quantity, delivery_price, price_type
             FROM {$this->table}
             WHERE format = ?
               AND active = 1
               AND min_quantity <= ?
               AND (max_quantity IS NULL OR max_quantity >= ?)
             ORDER BY min_quantity DESC
             LIMIT 1",
            [$format, $qty, $qty]
        );
        return $row ?: null;
    }

    /**
     * Retourne la prochaine tranche tarifaire au-dessus de la quantité donnée.
     * Utile pour indiquer au client combien de bouteilles lui manquent pour
     * atteindre le palier de remise suivant.
     *
     * @param int    $qty    Quantité actuelle dans le panier
     * @param string $format Format du produit (bottle|bib)
     * @return array<string, mixed>|null
     */
    public function findNextTierFor(int $qty, string $format = 'bottle'): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT id, min_quantity, delivery_price, price_type, label
             FROM {$this->table}
             WHERE format = ?
               AND active = 1
               AND min_quantity > ?
             ORDER BY min_quantity ASC
             LIMIT 1",
            [$format, $qty]
        );
        return $row ?: null;
    }

    /**
     * Retourne toutes les règles actives pour un format donné, triées par min_quantity ASC.
     * Utilisé pour afficher le tableau des paliers de remise dans la vue panier.
     *
     * @param string $format Format du produit (bottle|bib)
     * @return array<int, array<string, mixed>>
     */
    public function findAllActive(string $format = 'bottle'): array
    {
        return $this->db->fetchAll(
            "SELECT id, min_quantity, max_quantity, delivery_price, price_type, label
             FROM {$this->table}
             WHERE format = ? AND active = 1
             ORDER BY min_quantity ASC",
            [$format]
        );
    }

    /**
     * Calcule la remise livraison pour un nombre de bouteilles donné.
     * Retourne 0.0 si aucune règle active ou remise nulle.
     */
    public function computeDeliveryDiscount(int $totalBottles): float // NOSONAR php:S1142 — guard clauses intentionnels
    {
        if ($totalBottles <= 0) {
            return 0.0;
        }
        $rule = $this->findForQuantity($totalBottles);
        if (!$rule) {
            return 0.0;
        }
        $price = (float) $rule['delivery_price'];
        if ($price <= 0.0) {
            return 0.0;
        }
        if ($rule['price_type'] === 'per_bottle') {
            return round($price * $totalBottles, 2);
        }
        return $price; // fixed
    }
}
