<?php

declare(strict_types=1);

namespace Core;

/**
 * Centralise la gestion du cookie auth_token pour garantir
 * des attributs cohérents entre création et suppression.
 */
class CookieHelper
{
    private const NAME = 'auth_token';

    /**
     * Pose le cookie auth_token.
     *
     * @param string $token  Valeur du JWT
     * @param int    $expiry Durée de vie en secondes ; 0 = cookie de session (pas d'expiration)
     * @return void
     */
    public static function set(string $token, int $expiry): void
    {
        $options = [
            'path'     => '/',
            'secure'   => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if ($expiry > 0) {
            $options['expires'] = time() + $expiry;
        } else {
            $options['expires'] = 0;
        }

        setcookie(self::NAME, $token, $options);
    }

    public static function clear(): void
    {
        setcookie(self::NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
