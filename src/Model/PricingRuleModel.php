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
            "SELECT delivery_price, price_type
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
     * Calcule la remise livraison pour un nombre de bouteilles donné.
     * Retourne 0.0 si aucune règle active ou remise nulle.
     */
    public function computeDeliveryDiscount(int $totalBottles): float
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
