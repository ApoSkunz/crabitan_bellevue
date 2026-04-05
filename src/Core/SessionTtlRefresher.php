<?php

declare(strict_types=1);

namespace Core;

/**
 * Détermine et effectue le refresh de TTL d'une session active.
 *
 * Règle : si le token expire dans moins de JWT_REFRESH_THRESHOLD × JWT_EXPIRY
 * secondes, on prolonge expired_at en base de JWT_EXPIRY secondes supplémentaires.
 *
 * Le token JWT lui-même n'est PAS régénéré — seule la colonne connections.expired_at
 * est mise à jour. Cela évite toute émission de nouveau cookie.
 *
 * Les sessions remember-me (TTL > JWT_EXPIRY standard) sont exclues : leur durée
 * de vie (30 j) est par conception bien supérieure à JWT_EXPIRY, donc le ratio
 * calculé serait quasi-nul et le seuil ne serait jamais atteint. On les exclut
 * explicitement via le paramètre $isRememberMe pour éviter toute ambiguïté.
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
        // Les sessions remember-me ne sont pas concernées par le refresh TTL standard
        if ($isRememberMe) {
            return false;
        }

        if (!isset($payload['exp'], $payload['iat'])) {
            return false;
        }

        $totalTtl   = (int) $payload['exp'] - (int) $payload['iat'];
        $remaining  = (int) $payload['exp'] - time();
        $threshold  = (float) ($_ENV['JWT_REFRESH_THRESHOLD'] ?? 0.5);
        $expiry     = (int) ($_ENV['JWT_EXPIRY'] ?? 3600);

        // Sécurité : totalTtl doit être strictement positif pour éviter une division par zéro
        if ($totalTtl <= 0 || $remaining <= 0) {
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
