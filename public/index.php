<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');
define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';

use Core\Router;
use Core\Request;

$request = new Request();
$router  = new Router($request);

require_once ROOT_PATH . '/config/routes.php';

// Sliding refresh : si "Se souvenir de moi" était coché, on repousse l'expiry à chaque visite
$ageVerified = ($_COOKIE['age_verified'] ?? '') === '1';
if ($ageVerified && isset($_COOKIE['age_remember'])) {
    $ttl        = 397 * 24 * 3600;
    $cookieBase = ['path' => '/', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax'];
    setcookie('age_verified', '1', array_merge($cookieBase, ['expires' => time() + $ttl]));
    setcookie('age_remember', '1', array_merge($cookieBase, ['expires' => time() + $ttl]));
}

// Empêche le bfcache navigateur de restaurer des pages avec flash messages révolus
header('Cache-Control: no-store');

try {
    $router->dispatch();
} catch (\Core\Exception\HttpException) {
    exit;
} catch (\Throwable $e) {
    error_log('[500] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    \Core\Response::abort(500, 'Internal Server Error');
}
