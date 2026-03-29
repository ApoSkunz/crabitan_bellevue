<?php

declare(strict_types=1);

namespace Middleware;

use Core\Response;

class AdminMiddleware
{
    /**
     * Vérifie l'auth ET le rôle admin/super_admin.
     * Retourne le payload JWT.
     *
     * @param  callable(string):bool|null  $sessionChecker  Propagé à AuthMiddleware (injectable pour les tests).
     */
    public static function handle(?callable $sessionChecker = null): array
    {
        $payload = AuthMiddleware::handle($sessionChecker);

        if (!in_array($payload['role'] ?? '', ['admin', 'super_admin'], true)) {
            Response::abort(404, 'Page introuvable');
        }

        return $payload;
    }
}
