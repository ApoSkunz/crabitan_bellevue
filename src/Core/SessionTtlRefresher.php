<?php

declare(strict_types=1);

namespace Core;

/**
 * Détermine et effectue le refresh de TTL d'une session active.
 *
 * Sessions standard : si le ratio de TTL restant est inférieur à jwt_refresh_threshold,
 * on prolonge connections.expired_at de JWT_EXPIRY secondes supplémentaires.
 *
 * Sessions remember-me : pas de renouvellement à la durée remember-me (évite les sessions
 * indéfinies). On évite uniquement la coupure mid-session en fin de vie en prolongeant
 * à JWT_EXPIRY quand il reste moins de JWT_EXPIRY × jwt_refresh_threshold secondes.
 * Exposition maximale : durée remember-me + JWT_EXPIRY.
 *
 * Le token JWT lui-même n'est PAS régénéré — seule la colonne connections.expired_at
 * est mise à jour. Cela évite toute émission de nouveau cookie.
 */
class SessionTtlRefresher
{
    /**
     * Évalue si un refresh est nécessaire et le déclenche le cas échéant.
     *
     * @param array    $payload      Payload JWT décodé (doit contenir 'exp' et 'iat')
     * @param string   $token        JWT brut (clé dans connections)
     * @param bool     $isRememberMe true si la session est de type remember-me (30 j)
     * @param callable $refresher    fn(string $token, int $expiry): bool — injectable pour les tests
     *                               Signature : function(string $token, int $expiry): bool
     * @return bool                  true si un refresh a été effectué
     */
    public static function maybeRefresh(
        array $payload,
        string $token,
        bool $isRememberMe,
        callable $refresher
    ): bool {
        if (!isset($payload['exp'], $payload['iat'])) {
            return false;
        }

        $remaining = (int) $payload['exp'] - time();
        $expiry    = (int) ($_ENV['JWT_EXPIRY'] ?? 7200);
        $threshold = \Core\FeatureFlags::getFloat('jwt_refresh_threshold', 0.5);

        return self::needsRefresh($remaining, $isRememberMe, $payload, $expiry, $threshold)
            && (bool) $refresher($token, $expiry);
    }

    /**
     * Détermine si la session doit être prolongée.
     *
     * @param int   $remaining    Secondes restantes avant expiration
     * @param bool  $isRememberMe true si session remember-me
     * @param array $payload      Payload JWT (pour lire iat/exp sur session standard)
     * @param int   $expiry       Durée standard JWT_EXPIRY en secondes
     * @param float $threshold    Seuil jwt_refresh_threshold (ex : 0.5)
     * @return bool               true si un refresh doit être effectué
     */
    private static function needsRefresh(
        int $remaining,
        bool $isRememberMe,
        array $payload,
        int $expiry,
        float $threshold
    ): bool {
        if ($remaining <= 0) {
            return false;
        }

        if ($isRememberMe) {
            // Refresh uniquement en fin de vie pour éviter la coupure mid-session
            return $remaining <= $expiry * $threshold;
        }

        $totalTtl = (int) $payload['exp'] - (int) $payload['iat'];

        // totalTtl > 0 garantit l'absence de division par zéro
        return $totalTtl > 0 && ($remaining / $totalTtl) < $threshold;
    }
}
