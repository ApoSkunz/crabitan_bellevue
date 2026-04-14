<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');
define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';

/** @var \Core\Container $container */
$container = require ROOT_PATH . '/config/container.php'; // NOSONAR — bootstrap DI, side-effect intentionnel

use Core\ErrorLogger;
use Core\Router;
use Core\Request;
use Middleware\SecurityHeaderMiddleware;

ErrorLogger::register();
SecurityHeaderMiddleware::apply();

$request = new Request();
$router  = new Router($request, $container);

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
    || str_contains($path, '/newsletter/confirmation')
    || str_contains($path, '/auth/google')
    || str_contains($path, '/mon-compte/nouvel-appareil')
    || str_contains($path, '/mon-compte/appareil/confirmer')
    || str_contains($path, '/mon-compte/appareil/annuler')
    || str_contains($path, '/securite/confirmer')
    || str_contains($path, '/securite/ce-netait-pas-moi')
    || str_contains($path, '/compte/reactiver')
    || str_contains($path, '/reinitialisation/')
    || str_ends_with($path, '/fiche-technique'); // QR codes — mention Loi Evin incluse dans le PDF

// Crawlers SEO connus : vérification DNS inverse pour éviter l'usurpation du User-Agent (VULN-004)
$ua        = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isCrawler = false;
if (preg_match('/googlebot|bingbot|slurp|duckduckbot|baiduspider|yandexbot|facebot|ia_archiver|crawler|spider/i', $ua)) {
    $remoteIp    = $_SERVER['REMOTE_ADDR'] ?? '';
    $dnsCacheKey = 'bot_dns:' . md5($remoteIp);
    $cached      = function_exists('apcu_fetch') && function_exists('apcu_exists') && apcu_exists($dnsCacheKey)
        ? apcu_fetch($dnsCacheKey)
        : null;

    if ($cached !== null) {
        $isCrawler = (bool) $cached;
    } else {
        $host      = $remoteIp !== '' ? (string) @gethostbyaddr($remoteIp) : '';
        $isCrawler = str_ends_with($host, '.googlebot.com')
            || str_ends_with($host, '.google.com')
            || str_ends_with($host, '.search.msn.com')
            || str_ends_with($host, '.crawl.yahoo.net')
            || str_ends_with($host, '.duckduckgo.com')
            || str_ends_with($host, '.archive.org');

        if (function_exists('apcu_store')) {
            apcu_store($dnsCacheKey, $isCrawler, 3600); // cache 1h par IP
        }
    }
}

// Website Carbon Badge : bypass age-gate pour que leur API mesure l'empreinte CO2.
// Pas de reverse-DNS documenté chez eux → on se fie au UA uniquement (risque d'usurpation
// accepté : Loi Evin ne couvre pas ce niveau de contournement technique).
if (!$isCrawler && preg_match('/WebsiteCarbon|websitecarbon\.com/i', $ua)) {
    $isCrawler = true;
}

// Fallback Referer : leur crawler headless peut utiliser un UA Chrome standard.
// Si la requête vient depuis websitecarbon.com (test manuel ou API), on bypass.
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!$isCrawler && $referer !== '' && preg_match('#^https?://([a-z0-9-]+\.)?websitecarbon\.com(/|$)#i', $referer)) {
    $isCrawler = true;
}

// Crawlers SEO majeurs : UA-trust si le reverse-DNS a échoué (IONOS mutualisé sans APCu).
// Un crawler usurpé ne verrait que le contenu public — aucun exploit Loi Evin possible
// (crawler = lecture seule, pas de consommation d'alcool).
if (!$isCrawler && preg_match('/Googlebot|Bingbot|DuckDuckBot|Applebot|YandexBot|Slurp/i', $ua)) {
    $isCrawler = true;
}

if (!$ageVerified && !$isPublicPath && !$isCrawler) {
    $redirect = rawurlencode($path !== '/' ? $path : '/' . DEFAULT_LANG);
    header('Location: /age-gate?redirect=' . $redirect);
    exit;
}

// Crawlers traités comme vérifiés pour l'affichage complet (meta tags, head, footer)
if ($isCrawler && !$ageVerified) {
    $_COOKIE['age_verified'] = '1';
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
