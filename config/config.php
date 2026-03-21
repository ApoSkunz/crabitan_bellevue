<?php

declare(strict_types=1);

// Chargement des variables d'environnement
$env = parse_ini_file(ROOT_PATH . '/.env');
foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}

// Environnement
define('APP_ENV',  $_ENV['APP_ENV']  ?? 'production');
define('APP_URL',  $_ENV['APP_URL']  ?? '');
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Crabitan Bellevue');

// Affichage des erreurs selon environnement
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Langue par défaut
define('DEFAULT_LANG', 'fr');
define('SUPPORTED_LANGS', ['fr', 'en']);
