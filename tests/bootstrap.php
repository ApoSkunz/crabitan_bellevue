<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Constantes runtime
define('APP_ENV',        'testing');
define('APP_URL',        'http://localhost');
define('APP_NAME',       'Crabitan Bellevue Test');
define('DEFAULT_LANG',   'fr');
define('SUPPORTED_LANGS', ['fr', 'en']);
define('LANG_PATH',      __DIR__ . '/../lang');
define('ROOT_PATH',      __DIR__ . '/..');
define('SRC_PATH',       __DIR__ . '/../src');

// JWT secret pour les tests
$_ENV['JWT_SECRET'] = 'test-secret-key-for-unit-tests';
$_ENV['JWT_EXPIRY'] = '3600';
