<?php

/**
 * Router for PHP built-in server (used in CI E2E tests).
 * Serves static files directly; routes everything else through index.php.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

require __DIR__ . '/index.php';
