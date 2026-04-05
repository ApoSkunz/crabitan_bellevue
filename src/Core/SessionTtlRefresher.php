<?php

declare(strict_types=1);

namespace Core;

/**
 * Détermine et effectue le refresh de TTL d'une session active.
 *
 * Sessions standard : si le ratio de TTL restant est inférieur à JWT_REFRESH_THRESHOLD,
 * on prolonge connections.expired_at de JWT_EXPIRY secondes supplémentaires.
 *
 * Sessions remember-me : pas de renouvellement à la durée remember-me (évite les sessions
 * indéfinies). On évite uniquement la coupure mid-session en fin de vie en prolongeant
 * à JWT_EXPIRY quand il reste moins de JWT_EXPIRY × JWT_REFRESH_THRESHOLD secondes.
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
        $expiry    = (int) ($_ENV['JWT_EXPIRY'] ?? 3600);
        $threshold = (float) ($_ENV['JWT_REFRESH_THRESHOLD'] ?? 0.5);

        if ($remaining <= 0) {
            return false;
        }

        if ($isRememberMe) {
            // Ne renouvelle pas à la durée remember-me : évite la coupure mid-session
            // uniquement en fin de vie, en prolongeant à JWT_EXPIRY standard.
            if ($remaining > $expiry * $threshold) {
                return false;
            }

            return (bool) $refresher($token, $expiry);
        }

        $totalTtl = (int) $payload['exp'] - (int) $payload['iat'];

        // Sécurité : totalTtl doit être strictement positif pour éviter une division par zéro
        if ($totalTtl <= 0) {
            return false;
        }

        // Ratio de TTL restant (ex : 0.3 = 30% restant)
        $remainingRatio = $remaining / $totalTtl;

        if ($remainingRatio >= $threshold) {
            return false; // TTL encore confortable — pas de refresh
        }

        return (bool) $refresher($token, $expiry);
    }
}
