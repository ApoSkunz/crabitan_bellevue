<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Constantes runtime
define('APP_ENV', 'testing');
define('APP_URL', 'http://localhost');
define('APP_BASE_URL', 'http://localhost');
define('APP_NAME', 'Crabitan Bellevue Test');
define('DEFAULT_LANG', 'fr');
define('SUPPORTED_LANGS', ['fr', 'en']);
define('LANG_PATH', __DIR__ . '/../lang');
define('ROOT_PATH', __DIR__ . '/..');
define('SRC_PATH', __DIR__ . '/../src');

// Helpers globaux (fonction __() pour les traductions)
require_once ROOT_PATH . '/src/helpers.php';

// Préchargement des traductions fr (évite que les tests d'intégration renvoient des clés brutes)
\Core\Lang::load('fr');

// JWT secret pour les tests
$_ENV['JWT_SECRET'] = 'test-secret-key-for-unit-tests';
$_ENV['JWT_EXPIRY'] = '3600';

// Mail — valeurs pour les tests (surchargeable via variables d'environnement CI)
$_ENV['MAIL_HOST']      = getenv('MAIL_HOST') !== false ? getenv('MAIL_HOST') : 'localhost';
$_ENV['MAIL_PORT']      = getenv('MAIL_PORT') !== false ? getenv('MAIL_PORT') : '1025'; // mailhog par défaut
$_ENV['MAIL_USER']      = getenv('MAIL_USER') !== false ? getenv('MAIL_USER') : 'noreply@test.local';
$_ENV['MAIL_PASS']      = getenv('MAIL_PASS') !== false ? getenv('MAIL_PASS') : 'unused';
$_ENV['MAIL_FROM_NAME'] = getenv('MAIL_FROM_NAME') ?: 'Crabitan Bellevue Test';
$_ENV['MAIL_FROM']      = getenv('MAIL_FROM') !== false ? getenv('MAIL_FROM') : 'noreply@test.local';

// Variables d'environnement depuis .env si présent (TI locaux — surchargeable via CI)
$_dotEnv = is_file(ROOT_PATH . '/.env') ? parse_ini_file(ROOT_PATH . '/.env') : [];
foreach ($_dotEnv ?: [] as $_k => $_v) {
    if (!isset($_ENV[$_k])) {
        $_ENV[$_k] = $_v;
    }
}
unset($_dotEnv, $_k, $_v);

// Clé de chiffrement — fallback test si ni CI ni .env ne la fournissent
$_ENV['DB_ENCRYPT_KEY'] ??= 'test-encrypt-key-for-unit-tests-only';

// BDD pour les tests d'intégration (surchargeable via variables d'environnement CI)
$_ENV['DB_HOST'] = getenv('DB_HOST') ?: '127.0.0.1';
$_ENV['DB_PORT'] = getenv('DB_PORT') ?: '3306';
$_ENV['DB_NAME'] = getenv('DB_NAME') ?: 'crabitan_bellevue';
$_ENV['DB_USER'] = getenv('DB_USER') ?: 'root';
$_ENV['DB_PASS'] = getenv('DB_PASS') ?: '';
