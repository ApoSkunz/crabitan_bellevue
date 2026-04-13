<?php

/**
 * Script de reset OPCache protégé par token.
 *
 * Usage : GET https://crabitanbellevue.fr/opcache-reset.php?token=<OPCACHE_RESET_TOKEN>
 *
 * À utiliser après chaque déploiement IONOS pour invalider le bytecode PHP mis en cache
 * et forcer la relecture des fichiers .php modifiés.
 *
 * Sécurité :
 *   - Nécessite la variable d'environnement OPCACHE_RESET_TOKEN
 *   - Compare en timing-safe via hash_equals()
 *   - Retourne 403 si token absent ou invalide
 *   - À supprimer ou déplacer hors de public/ une fois la prod stabilisée
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

// Charger .env minimaliste pour récupérer OPCACHE_RESET_TOKEN
$envPath = dirname(__DIR__) . '/.env';
if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        $_ENV[$key] = $_ENV[$key] ?? $value;
    }
}

$expected = $_ENV['OPCACHE_RESET_TOKEN'] ?? '';
$provided = $_GET['token'] ?? '';

if ($expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

if (!function_exists('opcache_reset')) {
    http_response_code(503);
    echo "OPCache indisponible sur ce serveur.\n";
    exit;
}

$statsBefore = function_exists('opcache_get_status') ? opcache_get_status(false) : null;
$resetOk     = opcache_reset();
$statsAfter  = function_exists('opcache_get_status') ? opcache_get_status(false) : null;

echo "OPCache reset : " . ($resetOk ? 'OK' : 'KO') . "\n";
echo "Date : " . date('Y-m-d H:i:s') . "\n\n";

if (is_array($statsBefore)) {
    echo "--- Avant reset ---\n";
    echo "Cached scripts : " . ($statsBefore['opcache_statistics']['num_cached_scripts'] ?? '?') . "\n";
    echo "Hits           : " . ($statsBefore['opcache_statistics']['hits'] ?? '?') . "\n";
    echo "Memory used    : " . round(($statsBefore['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 2) . " MB\n\n";
}

if (is_array($statsAfter)) {
    echo "--- Après reset ---\n";
    echo "Cached scripts : " . ($statsAfter['opcache_statistics']['num_cached_scripts'] ?? '?') . "\n";
    echo "Memory used    : " . round(($statsAfter['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 2) . " MB\n";
}
