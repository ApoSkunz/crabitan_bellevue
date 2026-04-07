<?php

declare(strict_types=1);

namespace Middleware;

use Core\CookieHelper;
use Core\Database;
use Core\Response;

class AdminMiddleware
{
    /**
     * Vérifie l'auth ET le rôle admin/super_admin.
     * Refuse l'accès (403) si le compte est bloqué (is_blocked = 1).
     * Retourne le payload JWT.
     *
     * @param  callable(string):bool|null  $sessionChecker Propagé à AuthMiddleware (injectable pour les tests).
     * @param  callable(int):bool|null     $blockChecker   Callable(userId) → true si bloqué. null = vérif BDD réelle.
     * @return array<string, mixed> Payload JWT décodé
     */
    public static function handle(?callable $sessionChecker = null, ?callable $blockChecker = null): array
    {
        $payload = AuthMiddleware::handle($sessionChecker);

        if (!in_array($payload['role'] ?? '', ['admin', 'super_admin'], true)) {
            Response::abort(404, 'Page introuvable');
        }

        // Vérification compte bloqué
        $userId  = (int) ($payload['sub'] ?? 0);
        $checker = $blockChecker ?? static function (int $id): bool {
            $row = Database::getInstance()->fetchOne(
                "SELECT is_blocked FROM accounts WHERE id = ? AND deleted_at IS NULL",
                [$id]
            );
            return $row !== false && (bool) $row['is_blocked'];
        };

        if ($userId > 0 && $checker($userId)) {
            CookieHelper::clear();
            $lang = defined('DEFAULT_LANG') ? DEFAULT_LANG : 'fr';
            $_SESSION['flash']['modal_error'] = 'Votre compte administrateur a été bloqué. Contactez votre super administrateur.';
            Response::redirect('/' . $lang . '/connexion');
        }

        return $payload;
    }
}
