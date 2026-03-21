<?php

declare(strict_types=1);

// Chargement des variables d'environnement
$env = parse_ini_file(ROOT_PATH . '/.env');
foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}

// Environnement
define('APP_ENV', $_ENV['APP_ENV']  ?? 'production');
define('APP_URL', $_ENV['APP_URL']  ?? '');
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Crabitan Bellevue');

// Affichage des erreurs selon environnement
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Langues supportées
define('SUPPORTED_LANGS', ['fr', 'en']);

// Détection langue navigateur : fr si le browser est en français, sinon en
$_detectedLang = 'en';
$_acceptLang   = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
preg_match_all('/([a-z]{2})(?:-[a-z]{2})?/i', $_acceptLang, $_langMatches);
foreach ($_langMatches[1] as $_l) {
    if (in_array($_l, SUPPORTED_LANGS, true)) {
        $_detectedLang = $_l;
        break;
    }
}
define('DEFAULT_LANG', $_detectedLang);
unset($_detectedLang, $_acceptLang, $_langMatches, $_l);

// Chargement de la langue par défaut (surchargée par le Router si {lang} dans l'URL)
\Core\Lang::load(DEFAULT_LANG);

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
