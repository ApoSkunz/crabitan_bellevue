<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH',  ROOT_PATH . '/src');
define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/config/config.php';

use Core\Router;
use Core\Request;

$request = new Request();
$router  = new Router($request);

require_once ROOT_PATH . '/config/routes.php';

// Vérification d'âge — obligatoire pour les sites vendant de l'alcool
$path         = $request->getPath();
$ageVerified  = ($_COOKIE['age_verified'] ?? '') === '1';
$isPublicPath = str_starts_with($path, '/age-gate')
    || str_starts_with($path, '/admin')
    || str_starts_with($path, '/assets');

if (!$ageVerified && !$isPublicPath) {
    $redirect = rawurlencode($path !== '/' ? $path : '/fr');
    header('Location: /age-gate?redirect=' . $redirect);
    exit;
}

$router->dispatch();
