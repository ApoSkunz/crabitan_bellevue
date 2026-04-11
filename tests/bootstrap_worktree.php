<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- bootstrap : mélange define() et require inévitable
// Bootstrap pour les tests en contexte worktree (vendor dans le projet parent).
$parentVendor = dirname(__DIR__, 4) . '/vendor/autoload.php';
$autoloader   = require $parentVendor;

// Surcharge uniquement les fichiers du worktree qui diffèrent du projet principal.
// La classmap Composer a priorité sur PSR-4 ; on injecte directement les classes
// du worktree avant toute utilisation pour les remplacer dans le cache PHP.
$worktreeRoot = dirname(__DIR__);

// Fichiers créés / modifiés dans ce worktree (à maintenir à jour si de nouvelles classes s'ajoutent)
$worktreeOverrides = [
    $worktreeRoot . '/src/Service/GaeApiInterface.php',
    $worktreeRoot . '/src/Service/GaeApiClient.php',
    $worktreeRoot . '/src/Service/RefundService.php',
    $worktreeRoot . '/src/Service/MailService.php',
    $worktreeRoot . '/src/Model/OrderModel.php',
    $worktreeRoot . '/src/Controller/AccountController.php',
    $worktreeRoot . '/src/Controller/Admin/OrderAdminController.php',
];

foreach ($worktreeOverrides as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

$autoloader->addPsr4('Tests\\', [$worktreeRoot . '/tests/'], true);

define('APP_ENV', 'testing');
define('APP_URL', 'http://localhost');
define('APP_NAME', 'Crabitan Bellevue Test');
define('DEFAULT_LANG', 'fr');
define('SUPPORTED_LANGS', ['fr', 'en']);
define('LANG_PATH', __DIR__ . '/../lang');
define('ROOT_PATH', __DIR__ . '/..');
define('SRC_PATH', __DIR__ . '/../src');

require_once ROOT_PATH . '/src/helpers.php';

$_ENV['JWT_SECRET'] = 'test-secret-key-for-unit-tests';
$_ENV['JWT_EXPIRY'] = '3600';

$_ENV['MAIL_HOST']      = getenv('MAIL_HOST') !== false ? getenv('MAIL_HOST') : 'localhost';
$_ENV['MAIL_PORT']      = getenv('MAIL_PORT') !== false ? getenv('MAIL_PORT') : '1025';
$_ENV['MAIL_USER']      = getenv('MAIL_USER') !== false ? getenv('MAIL_USER') : 'noreply@test.local';
$_ENV['MAIL_PASS']      = getenv('MAIL_PASS') !== false ? getenv('MAIL_PASS') : 'unused';
$_ENV['MAIL_FROM_NAME'] = getenv('MAIL_FROM_NAME') ?: 'Crabitan Bellevue Test';
$_ENV['MAIL_FROM']      = getenv('MAIL_FROM') !== false ? getenv('MAIL_FROM') : 'noreply@test.local';

$_ENV['DB_HOST'] = getenv('DB_HOST') ?: '127.0.0.1';
$_ENV['DB_PORT'] = getenv('DB_PORT') ?: '3306';
$_ENV['DB_NAME'] = getenv('DB_NAME') ?: 'crabitan_bellevue';
$_ENV['DB_USER'] = getenv('DB_USER') ?: 'root';
$_ENV['DB_PASS'] = getenv('DB_PASS') ?: '';
