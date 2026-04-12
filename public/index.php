<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');
define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';

use Core\ErrorLogger;
use Core\Router;
use Core\Request;
use Middleware\SecurityHeaderMiddleware;

ErrorLogger::register();
SecurityHeaderMiddleware::apply();

$request = new Request();
$router  = new Router($request);

require_once ROOT_PATH . '/config/routes.php';

// ── Vérification d'âge côté serveur (Art. L3342-1 CSP) ──────────────────────
// Chemins accessibles sans cookie (page age-gate elle-même, admin, assets, pages légales…)
$path         = $request->path;
$ageVerified  = ($_COOKIE['age_verified'] ?? '') === '1';
$isPublicPath = str_starts_with($path, '/age-gate')
    || str_starts_with($path, '/admin')
    || str_starts_with($path, '/assets')
    || str_ends_with($path, '/mentions-legales')
    || str_ends_with($path, '/politique-de-confidentialite')
    || str_ends_with($path, '/conditions-generales-de-vente')
    || str_ends_with($path, '/support')
    || str_contains($path, '/newsletter/desabonnement')
    || str_contains($path, '/auth/google')
    || str_ends_with($path, '/fiche-technique'); // QR codes — mention Loi Evin incluse dans le PDF

// Crawlers SEO connus : pas de cookie mais on laisse passer pour l'indexation
$ua        = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isCrawler = (bool) preg_match(
    '/googlebot|bingbot|slurp|duckduckbot|baiduspider|yandexbot|facebot|ia_archiver|crawler|spider|headlesschrome|lighthouse/i',
    $ua
);

if (!$ageVerified && !$isPublicPath && !$isCrawler) {
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

// Empêche le bfcache navigateur de restaurer des pages avec flash messages révolus
header('Cache-Control: no-store');

try {
    $router->dispatch();
} catch (\Core\Exception\HttpException) {
    exit;
} catch (\Throwable $e) {
    \Core\ErrorLogger::write([
        'timestamp' => date('Y-m-d H:i:s'),
        'level'     => 'EXCEPTION',
        'message'   => get_class($e) . ': ' . $e->getMessage(),
        'file'      => $e->getFile(),
        'line'      => $e->getLine(),
        'trace'     => substr($e->getTraceAsString(), 0, 500),
        'url'       => $_SERVER['REQUEST_URI'] ?? '',
        'method'    => $_SERVER['REQUEST_METHOD'] ?? '',
    ]);
    \Core\Response::abort(500, 'Internal Server Error');
}
