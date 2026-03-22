<?php

declare(strict_types=1);

// Fonction helper globale pour les traductions (accessible dans les vues)
if (!function_exists('__')) {
    function __(string $key, array $replace = []): string // NOSONAR
    {
        return \Core\Lang::get($key, $replace);
    }
}
