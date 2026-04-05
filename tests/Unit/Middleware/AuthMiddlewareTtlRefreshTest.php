<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Core\Jwt;
use Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique TTL refresh dans AuthMiddleware::handle().
 *
 * Couvre : refresh déclenché sur session standard, absence de refresh sur
 * session remember-me, aucun refresh si TTL confortable.
 * Tous les appels BDD sont injectés via les callables $sessionChecker / $ttlRefresher.
 */
class AuthMiddlewareTtlRefreshTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE = [];
        $_ENV['JWT_SECRET']            = 'test-secret-key-for-unit-tests';
        $_ENV['JWT_EXPIRY']            = '3600';
        $_ENV['JWT_REFRESH_THRESHOLD'] = '0.5';
    }

    // ------------------------------------------------------------------
    // Refresh déclenché — session standard dont le TTL est épuisé à > 50%
    // ------------------------------------------------------------------

    /**
     * Le refresher est appelé quand le token standard a moins de 50% de TTL restant.
     */
    public function testRefresherCalledWhenTtlBelowThreshold(): void
    {
        $now = time();
        // Crée un token dont iat/exp simulent 38% restant (< 50%)
        $token = Jwt::encode([
            'sub'  => 1,
            'role' => 'customer',
            'iat'  => $now - 2200,
            'exp'  => $now + 1400,
        ]);
        $_COOKIE['auth_token'] = $token;

        $refreshCalled = false;
        $result = AuthMiddleware::handle(
            fn() => true,  // session active
            function (string $t, int $expiry) use (&$refreshCalled): bool {
                $refreshCalled = true;
                return true;
            }
        );

        $this->assertSame(1, $result['sub']);
        $this->assertTrue($refreshCalled, 'Le refresher doit être appelé quand le TTL est sous le seuil');
    }

    // ------------------------------------------------------------------
    // Pas de refresh — TTL encore confortable (> 50%)
    // ------------------------------------------------------------------

    /**
     * Le refresher n'est PAS appelé quand le token a encore 60% de TTL restant.
     */
    public function testRefresherNotCalledWhenTtlAboveThreshold(): void
    {
        $now = time();
        $token = Jwt::encode([
            'sub'  => 2,
            'role' => 'customer',
            'iat'  => $now - 1440,
            'exp'  => $now + 2160,
        ]);
        $_COOKIE['auth_token'] = $token;

        $refreshCalled = false;
        AuthMiddleware::handle(
            fn() => true,
            function () use (&$refreshCalled): bool {
                $refreshCalled = true;
                return true;
            }
        );

        $this->assertFalse($refreshCalled, 'Le refresher ne doit PAS être appelé quand le TTL est confortable');
    }

    // ------------------------------------------------------------------
    // Sessions remember-me — anti-coupure fin de vie
    // ------------------------------------------------------------------

    /**
     * Le refresher n'est PAS appelé pour un remember-me avec TTL restant > JWT_EXPIRY × threshold.
     * Ex : 24h restantes >> seuil 1800 s → pas de refresh.
     */
    public function testRefresherNotCalledForRememberMeWithComfortableTtl(): void
    {
        $now = time();
        // Créé il y a 29 jours → TTL restant : 24h >> seuil (3600 × 0.5 = 1800 s)
        $token = Jwt::encode([
            'sub'  => 3,
            'role' => 'customer',
            'iat'  => $now - (29 * 86400),
            'exp'  => $now + 86400,
        ]);
        $_COOKIE['auth_token'] = $token;

        $refreshCalled = false;
        AuthMiddleware::handle(
            fn() => true,
            function () use (&$refreshCalled): bool {
                $refreshCalled = true;
                return true;
            }
        );

        $this->assertFalse($refreshCalled, 'Pas de refresh si le TTL remember-me est encore confortable');
    }

    /**
     * Le refresher EST appelé pour un remember-me dont le TTL restant < JWT_EXPIRY × threshold.
     * Refresh à JWT_EXPIRY (durée standard), pas à la durée remember-me.
     */
    public function testRefresherCalledForRememberMeNearExpiry(): void
    {
        $now = time();
        // Remaining = 1000 s < seuil (3600 × 0.5 = 1800 s) → refresh déclenché
        $token = Jwt::encode([
            'sub'  => 5,
            'role' => 'customer',
            'iat'  => $now - (30 * 86400 - 1000),
            'exp'  => $now + 1000,
        ]);
        $_COOKIE['auth_token'] = $token;

        $refreshCalled  = false;
        $receivedExpiry = null;
        AuthMiddleware::handle(
            fn() => true,
            function (string $t, int $expiry) use (&$refreshCalled, &$receivedExpiry): bool {
                $refreshCalled  = true;
                $receivedExpiry = $expiry;
                return true;
            }
        );

        $this->assertTrue($refreshCalled, 'Le refresher doit être appelé pour un remember-me en fin de vie');
        $this->assertSame(3600, $receivedExpiry, 'Le refresh doit utiliser JWT_EXPIRY, pas la durée remember-me');
    }

    // ------------------------------------------------------------------
    // Le payload est toujours retourné même si le refresh échoue (BDD down)
    // ------------------------------------------------------------------

    /**
     * Si le refresher retourne false (ex : session expirée entre temps en BDD),
     * le middleware retourne quand même le payload valide.
     */
    public function testHandleReturnsPayloadEvenWhenRefresherReturnsFalse(): void
    {
        $now = time();
        $token = Jwt::encode([
            'sub'  => 4,
            'role' => 'customer',
            'iat'  => $now - 2200,
            'exp'  => $now + 1400,
        ]);
        $_COOKIE['auth_token'] = $token;

        $result = AuthMiddleware::handle(
            fn() => true,
            fn() => false  // refresh échoue silencieusement
        );

        $this->assertSame(4, $result['sub']);
    }
}
