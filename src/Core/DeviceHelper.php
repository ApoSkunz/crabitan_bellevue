<?php

declare(strict_types=1);

namespace Core;

/**
 * Résolution du device token et dérivation du nom d'appareil depuis le User-Agent.
 * Partagé entre GoogleOAuthController et GoogleLinkStatusController.
 */
trait DeviceHelper
{
    /**
     * Retourne le device token depuis le cookie, ou en génère un nouveau.
     *
     * @return string Token hexadécimal de 64 caractères
     */
    private function resolveDeviceToken(): string
    {
        $token = $_COOKIE['device_token'] ?? null;
        if ($token !== null && preg_match('/^[0-9a-f]{64}$/', $token)) {
            return $token;
        }
        $token = null;
        $token = bin2hex(random_bytes(32));
        setcookie('device_token', $token, [
            'expires'  => time() + (90 * 24 * 3600),
            'path'     => '/',
            'secure'   => (defined('APP_ENV') ? APP_ENV : ($_ENV['APP_ENV'] ?? 'production')) === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return $token;
    }

    /**
     * Dérive un nom lisible d'appareil depuis le User-Agent.
     *
     * @param string $ua User-Agent HTTP
     * @return string Nom au format "Browser · OS"
     */
    private function deriveDeviceName(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg')                                       => 'Edge',
            str_contains($ua, 'Chrome') && !str_contains($ua, 'Chromium') => 'Chrome',
            str_contains($ua, 'Firefox')                                   => 'Firefox',
            str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')   => 'Safari',
            default                                                        => 'Browser',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android')                              => 'Android',
            str_contains($ua, 'Windows')                              => 'Windows',
            str_contains($ua, 'Macintosh')                            => 'macOS',
            str_contains($ua, 'Linux')                                => 'Linux',
            default                                                   => 'Unknown',
        };
        return "{$browser} · {$os}";
    }
}
