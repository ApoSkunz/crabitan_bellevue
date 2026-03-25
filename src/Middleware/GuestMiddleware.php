<?php

declare(strict_types=1);

namespace Middleware;

use Core\Exception\HttpException;
use Core\Jwt;
use Core\Response;

class GuestMiddleware
{
    /**
     * Redirige vers /mon-compte si l'utilisateur est déjà connecté.
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
