<?php

declare(strict_types=1);

namespace Middleware;

use Core\CookieHelper;
use Core\Database;
use Core\Jwt;
use Core\Response;
use Core\SessionTtlRefresher;
use Model\ConnectionModel;

class AuthMiddleware
{
    /**
     * Vérifie le JWT en cookie et retourne le payload.
     * Vérifie aussi que la session est toujours active en base (non révoquée).
     * Prolonge expired_at si le TTL restant est inférieur au seuil jwt_refresh_threshold.
     * Redirige vers /connexion si absent, invalide ou révoqué.
     *
     * @param  callable(string):bool|null      $sessionChecker  Injectable pour les tests unitaires.
     *                                                           null = vérification réelle en base.
     * @param  callable(string,int):bool|null  $ttlRefresher    Injectable pour les tests unitaires.
     *                                                           null = refresh réel en base.
     * @param  bool                            $redirectToLogin  true = mémorise _intended et redirige vers le modal
     *                                                           de connexion (pages utilisateur) au lieu d'un 404.
     */
    public static function handle(
        ?callable $sessionChecker = null,
        ?callable $ttlRefresher = null,
        bool $redirectToLogin = false
    ): array {
        $token = $_COOKIE['auth_token'] ?? null;

        if (!$token) {
            if ($redirectToLogin) {
                self::storeIntendedAndRedirect();
            }
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

        // Refresh TTL si le token approche de son expiration
        // Les sessions remember-me ont auth_method = 'password' avec TTL 30j :
        // on les détecte par comparaison entre la durée JWT et JWT_EXPIRY standard.
        $isRememberMe = self::isRememberMeToken($payload);
        $newToken     = null;

        $refresher = $ttlRefresher ?? static function (string $t, int $expiry) use ($payload, $isRememberMe, &$newToken): bool {
            $model = new ConnectionModel();
            if ($isRememberMe) {
                // Remember-me : on ne régénère pas le JWT (durée 30j volontaire)
                return $model->refreshExpiry($t, $expiry);
            }
            // Session standard : régénérer le JWT avec nouveau exp/iat pour éviter le kick
            // iat doit être mis à jour pour que isRememberMeToken() (exp-iat vs JWT_EXPIRY)
            // ne détecte pas à tort la session comme remember-me après le premier refresh.
            $newPayload        = $payload;
            $newPayload['iat'] = time();
            $newPayload['exp'] = time() + $expiry;
            $generated         = Jwt::encode($newPayload);
            $ok                = $model->refreshToken($t, $generated, $expiry);
            if ($ok) {
                $newToken = $generated;
            }
            return $ok;
        };

        $refreshed = SessionTtlRefresher::maybeRefresh($payload, $token, $isRememberMe, $refresher);

        // Émettre le nouveau cookie si le JWT a été régénéré
        if ($newToken !== null) {
            $expiry = (int) ($_ENV['JWT_EXPIRY'] ?? 7200);
            CookieHelper::set($newToken, $expiry);
        }

        // Sliding refresh du cookie age-gate quand le JWT approche de son expiration.
        // S'applique à tout utilisateur ayant passé la vérification d'âge (age_verified présent).
        // TTL : 13 mois si "se souvenir" coché, 24 h sinon — cohérent avec AgeGateController.
        if ($refreshed && isset($_COOKIE['age_verified'])) {
            $ageTtl = isset($_COOKIE['age_remember'])
                ? (397 * 24 * 3600)   // AgeGateController::COOKIE_TTL
                : (24 * 3600);         // AgeGateController::COOKIE_TTL_DEFAULT
            setcookie('age_verified', '1', [
                'expires'  => time() + $ageTtl,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        return $payload;
    }

    /**
     * Stocke l'URL courante en session comme destination post-connexion,
     * puis redirige vers le modal de connexion.
     *
     * @return never
     */
    private static function storeIntendedAndRedirect(): never
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

        // Guard : les chemins admin ne doivent jamais révéler leur existence via une redirection login
        if (preg_match('#/admin/#i', (string) $uri)) {
            Response::abort(404);
        }

        preg_match('#^/(fr|en)(/|$)#', (string) $uri, $m);
        $lang = $m[1] ?? 'fr';
        $_SESSION['_intended'] = $uri;
        Response::redirect("/{$lang}?login=1");
    }

    /**
     * Détermine si le token correspond à une session remember-me (longue durée).
     *
     * Un token remember-me a une TTL totale supérieure à JWT_EXPIRY standard.
     * On compare exp - iat au seuil standard pour discriminer les deux cas.
     *
     * @param  array $payload Payload JWT décodé
     * @return bool           true si remember-me
     */
    private static function isRememberMeToken(array $payload): bool
    {
        if (!isset($payload['exp'], $payload['iat'])) {
            return false;
        }

        $standardExpiry = (int) ($_ENV['JWT_EXPIRY'] ?? 7200);
        $tokenTtl       = (int) $payload['exp'] - (int) $payload['iat'];

        // Si la durée totale du token dépasse la durée standard, c'est un remember-me
        return $tokenTtl > $standardExpiry;
    }
}
