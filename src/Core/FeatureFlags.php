<?php

declare(strict_types=1);

namespace Core;

/**
 * Accès aux feature flags via variables d'environnement.
 * Note : CLICK_COLLECT_DISCOUNT_PER_BOTTLE a été déplacé en BDD
 * (table click_collect_settings, modifiable depuis /admin/tarifs).
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
}
