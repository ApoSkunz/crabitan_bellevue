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

// Vérification d'âge — obligatoire pour les sites vendant de l'alcool
$path         = $request->path;
$ageVerified  = ($_COOKIE['age_verified'] ?? '') === '1';
$isPublicPath = str_starts_with($path, '/age-gate')
    || str_starts_with($path, '/admin')
    || str_starts_with($path, '/assets')
    || str_ends_with($path, '/mentions-legales')
    || str_ends_with($path, '/politique-de-confidentialite')
    || str_contains($path, '/newsletter/desabonnement');

if (!$ageVerified && !$isPublicPath) {
    $redirect = rawurlencode($path !== '/' ? $path : '/' . DEFAULT_LANG);
    header('Location: /age-gate?redirect=' . $redirect);
    exit;
}

// Sliding refresh : si "Se souvenir de moi" était coché, on repousse l'expiry à chaque visite
if ($ageVerified && isset($_COOKIE['age_remember'])) {
    $ttl        = 397 * 24 * 3600;
    $cookieBase = ['path' => '/', 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax'];
    setcookie('age_verified', '1', array_merge($cookieBase, ['expires' => time() + $ttl]));
    setcookie('age_remember', '1', array_merge($cookieBase, ['expires' => time() + $ttl]));
}

try {
    $router->dispatch();
} catch (\Core\Exception\HttpException) {
    exit;
} catch (\Throwable $e) {
    error_log('[500] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    \Core\Response::abort(500, 'Internal Server Error');
}
