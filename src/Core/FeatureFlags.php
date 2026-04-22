<?php

declare(strict_types=1);

namespace Core;

/**
 * Accès aux feature flags via variables d'environnement.
 */
class FeatureFlags
{
    /**
     * Vérifie si le click & collect est activé.
     *
     * @return bool
     */
    public static function isClickCollectEnabled(): bool
    {
        $val = strtolower(trim($_ENV['CLICK_COLLECT_ENABLED'] ?? 'false'));
        return in_array($val, ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Retourne la remise click & collect par bouteille (en euros).
     *
     * @return float
     */
    public static function getClickCollectDiscountPerBottle(): float
    {
        return (float) ($_ENV['CLICK_COLLECT_DISCOUNT_PER_BOTTLE'] ?? 2.20);
    }
}
