<?php

declare(strict_types=1);

namespace Core;

class Jwt
{
    private static string $algorithm = 'SHA256';

    public static function encode(array $payload): string
    {
        $header  = self::base64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = self::base64url(json_encode($payload));
        $signature = self::base64url(hash_hmac(
            self::$algorithm,
            "$header.$payload",
            $_ENV['JWT_SECRET'],
            true
        ));

        return "$header.$payload.$signature";
    }

    public static function decode(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Token JWT invalide');
        }

        [$header, $payload, $signature] = $parts;

        $expectedSig = self::base64url(hash_hmac(
            self::$algorithm,
            "$header.$payload",
            $_ENV['JWT_SECRET'],
            true
        ));

        if (!hash_equals($expectedSig, $signature)) {
            throw new \Core\Exception\JwtException('Signature JWT invalide');
        }

        $data = json_decode(self::base64urlDecode($payload), true);

        if (isset($data['exp']) && $data['exp'] < time()) {
            throw new \Core\Exception\JwtException('Token JWT expiré');
        }

        return $data;
    }

    /**
     * Génère un JWT signé pour un utilisateur.
     *
     * @param int    $userId Identifiant de l'utilisateur
     * @param string $role   Rôle de l'utilisateur
     * @param int    $ttl    Durée de vie en secondes (0 = utilise JWT_EXPIRY, défaut env)
     * @return string
     */
    public static function generate(int $userId, string $role, int $ttl = 0): string
    {
        $expiry = $ttl > 0 ? $ttl : (int) ($_ENV['JWT_EXPIRY'] ?? 3600);

        return self::encode([
            'sub'  => $userId,
            'role' => $role,
            'iat'  => time(),
            'exp'  => time() + $expiry,
        ]);
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
