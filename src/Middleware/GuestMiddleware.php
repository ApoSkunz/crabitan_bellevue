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
            Jwt::decode($token);
            $lang = defined('CURRENT_LANG') ? CURRENT_LANG : 'fr';
            Response::redirect("/{$lang}/mon-compte");
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable) {
            // Token invalide ou expiré → laisser passer
        }
    }
}
