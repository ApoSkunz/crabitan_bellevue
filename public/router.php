<?php

/**
 * Router for PHP built-in server (used in CI E2E tests).
 * Serves static files directly; routes everything else through index.php.
 */

$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$realBase = realpath(__DIR__);
$realFile = ($uri !== '/') ? realpath(__DIR__ . $uri) : false;

if ($realFile !== false && str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)) {
    return false;
}

require __DIR__ . '/index.php';
