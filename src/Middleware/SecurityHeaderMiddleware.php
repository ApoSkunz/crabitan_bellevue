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

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self'",
            "connect-src 'self' https://api.websitecarbon.com",
            "form-action 'self' https://tpeweb.e-transactions.fr https://tpeweb1.e-transactions.fr https://recette-tpeweb.e-transactions.fr",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        header("Content-Security-Policy: {$csp}");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
}
