<?php

declare(strict_types=1);

namespace Model;

use Core\Model;

/**
 * Configuration Click & Collect — singleton (id=1).
 * Remplace la variable d'environnement CLICK_COLLECT_DISCOUNT_PER_BOTTLE.
 */
class ClickCollectSettingsModel extends Model
{
    protected string $table = 'click_collect_settings';

    private const DEFAULT_DISCOUNT = 2.20;

    /**
     * Retourne la remise par bouteille configurée (€).
     * Fallback sur la valeur par défaut (2.20€) si table vide.
     */
    public function getDiscountPerBottle(): float
    {
        $row = $this->db->fetchOne(
            "SELECT discount_per_bottle FROM {$this->table} WHERE id = 1"
        );
        if (!is_array($row)) {
            return self::DEFAULT_DISCOUNT;
        }
        return (float) $row['discount_per_bottle'];
    }

    /**
     * Enregistre la remise par bouteille. Crée la ligne si absente.
     *
     * @param float $discount Remise en euros (ex: 2.20)
     */
    public function setDiscountPerBottle(float $discount): void
    {
        $clean = max(0.0, round($discount, 2));
        $this->db->execute(
            "INSERT INTO {$this->table} (id, discount_per_bottle) VALUES (1, ?)
             ON DUPLICATE KEY UPDATE discount_per_bottle = VALUES(discount_per_bottle)",
            [$clean]
        );
    }
}
