<?php

declare(strict_types=1);

namespace Middleware;

/**
 * Middleware de sécurité HTTP.
 *
 * Génère un nonce CSP par requête et envoie l'ensemble des en-têtes de sécurité.
 * À appeler en tout premier dans public/index.php, avant toute sortie HTML.
 */
class SecurityHeaderMiddleware
{
    /**
     * Génère le nonce CSP, le définit comme constante globale CSP_NONCE,
     * puis envoie tous les en-têtes de sécurité HTTP.
     *
     * @return void
     */
    public static function apply(): void
    {
        $nonce = base64_encode(random_bytes(16));

        if (!defined('CSP_NONCE')) {
            define('CSP_NONCE', $nonce);
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self' https://api.websitecarbon.com",
            "form-action 'self' https://tpeweb.e-transactions.fr https://tpeweb1.e-transactions.fr https://recette-tpeweb.e-transactions.fr",
            "frame-src https://www.google.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        // X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
        // et HSTS sont gérés statiquement dans public/.htaccess
        header("Content-Security-Policy: {$csp}");

        // X-Robots-Tag : noai sur les pages vins (catalogue)
        if (self::isWinePath($uri)) {
            header('X-Robots-Tag: noai, noimageai');
        }
    }

    /**
     * Détermine si l'URI courante est une page catalogue vins.
     *
     * @param string $uri URI de la requête
     * @return bool
     */
    public static function isWinePath(string $uri): bool
    {
        $path = strtok($uri, '?');
        return (bool) preg_match('#^/(fr/vins|en/wines)(/|$)#', (string) $path);
    }
}
