<?php

declare(strict_types=1);

// Chargement des variables d'environnement
$env = is_file(ROOT_PATH . '/.env') ? parse_ini_file(ROOT_PATH . '/.env') : [];
foreach ($env ?: [] as $key => $value) {
    $_ENV[$key] = $value;
}

// Environnement
defined('APP_ENV')  || define('APP_ENV', $_ENV['APP_ENV']   ?? 'production');
defined('APP_URL')      || define('APP_URL', $_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr');
defined('APP_BASE_URL') || define('APP_BASE_URL', rtrim(APP_URL, '/'));
defined('APP_NAME') || define('APP_NAME', $_ENV['APP_NAME'] ?? 'Château Crabitan Bellevue');
define('ASSET_VERSION', '1.32.1');

// Affichage des erreurs selon environnement
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Langues supportées
defined('SUPPORTED_LANGS') || define('SUPPORTED_LANGS', ['fr', 'en']);

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
// Cookie cb-lang (posé à la connexion) prioritaire sur la détection navigateur
$_savedLang = in_array($_COOKIE['cb-lang'] ?? '', SUPPORTED_LANGS, true) ? $_COOKIE['cb-lang'] : null;
defined('DEFAULT_LANG') || define('DEFAULT_LANG', $_savedLang ?? $_detectedLang);
unset($_detectedLang, $_acceptLang, $_langMatches, $_l, $_savedLang);

// Chargement de la langue par défaut (surchargée par le Router si {lang} dans l'URL)
\Core\Lang::load(DEFAULT_LANG);

// Session — attributs de sécurité explicites (VULN-004)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure'   => APP_ENV === 'production',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}
