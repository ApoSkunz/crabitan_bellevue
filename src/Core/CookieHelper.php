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

    public static function set(string $token, int $expiry): void
    {
        setcookie(self::NAME, $token, [
            'expires'  => time() + $expiry,
            'path'     => '/',
            'secure'   => APP_ENV === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
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
