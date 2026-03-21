<?php

declare(strict_types=1);

namespace Middleware;

use Core\Jwt;
use Core\Response;

class AuthMiddleware
{
    /**
     * Vérifie le JWT en cookie et retourne le payload.
     * Redirige vers /connexion si absent ou invalide.
     */
    public static function handle(): array
    {
        $token = $_COOKIE['auth_token'] ?? null;
        $lang  = defined('CURRENT_LANG') ? CURRENT_LANG : 'fr';

        if (!$token) {
            Response::redirect("/{$lang}/connexion");
        }

        try {
            return Jwt::decode($token);
        } catch (\Throwable) {
            setcookie('auth_token', '', time() - 1, '/', '', true, true);
            Response::redirect("/{$lang}/connexion");
        }
    }
}
