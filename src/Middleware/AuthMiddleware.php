<?php

declare(strict_types=1);

namespace Middleware;

use Core\CookieHelper;
use Core\Database;
use Core\Jwt;
use Core\Response;

class AuthMiddleware
{
    /**
     * Vérifie le JWT en cookie et retourne le payload.
     * Vérifie aussi que la session est toujours active en base (non révoquée).
     * Redirige vers /connexion si absent, invalide ou révoqué.
     *
     * @param  callable(string):bool|null  $sessionChecker  Injectable pour les tests unitaires.
     *                                                       null = vérification réelle en base.
     */
    public static function handle(?callable $sessionChecker = null): array
    {
        $token = $_COOKIE['auth_token'] ?? null;
        $lang  = defined('CURRENT_LANG') ? CURRENT_LANG : 'fr';

        if (!$token) {
            Response::abort(404); // 404 — ne révèle pas l'existence des routes protégées
        }

        try {
            $payload = Jwt::decode($token);
        } catch (\Throwable) {
            CookieHelper::clear();
            Response::abort(404); // token invalide — idem
        }

        // Vérifie que la session n'a pas été révoquée en base
        $checker = $sessionChecker ?? static function (string $t): bool {
            $row = Database::getInstance()->fetchOne(
                "SELECT id FROM connections WHERE token = ? AND status = 'active'",
                [$t]
            );
            return (bool) $row;
        };

        if (!$checker($token)) {
            CookieHelper::clear();
            Response::abort(404); // session révoquée — idem
        }

        return $payload;
    }
}
