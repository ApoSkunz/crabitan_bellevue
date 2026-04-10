<?php

declare(strict_types=1);

namespace Middleware;

use Core\Exception\HttpException;
use Core\Jwt;
use Core\Response;

/**
 * Middleware invoqué sur les routes réservées aux visiteurs non authentifiés.
 * Redirige les utilisateurs déjà connectés vers leur espace compte ou l'admin.
 */
class GuestMiddleware
{
    /**
     * Redirige vers /mon-compte (ou /admin) si l'utilisateur est déjà connecté.
     * Ne fait rien si le token est absent, invalide ou expiré.
     *
     * @return void
     */
    public static function handle(): void
    {
        $token = $_COOKIE['auth_token'] ?? null;

        if (!$token) {
            return;
        }

        try {
            $payload = Jwt::decode($token);
            $lang    = defined('CURRENT_LANG') ? CURRENT_LANG : 'fr';
            if (in_array($payload['role'] ?? '', ['admin', 'super_admin'], true)) {
                Response::redirect('/admin');
            }
            Response::redirect("/{$lang}/mon-compte");
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            // Token invalide ou expiré → laisser passer
        }
    }
}
