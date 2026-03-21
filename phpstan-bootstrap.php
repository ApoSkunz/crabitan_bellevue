<?php

/**
 * Bootstrap PHPStan — définit les constantes runtime et les helpers globaux
 * pour que l'analyse statique puisse résoudre les symboles définis dynamiquement.
 */

// Constantes d'application
define('APP_ENV',  'development');
define('APP_URL',  'http://localhost');
define('APP_NAME', 'Crabitan Bellevue');

// Langue
define('DEFAULT_LANG',    'fr');
define('SUPPORTED_LANGS', ['fr', 'en']);
define('LANG_PATH',       __DIR__ . '/lang');

// Chemins
define('ROOT_PATH', __DIR__);
define('SRC_PATH',  __DIR__ . '/src');

// Helper de traduction — déclaré globalement pour PHPStan
// (la vraie implémentation est Core\__() dans src/Core/Lang.php)
if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        return $key;
    }
}
