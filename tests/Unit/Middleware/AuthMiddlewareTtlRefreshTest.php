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
    // Pas de refresh — session remember-me (TTL > JWT_EXPIRY)
    // ------------------------------------------------------------------

    /**
     * Le refresher n'est PAS appelé pour une session remember-me, même si le TTL est bas.
     */
    public function testRefresherNotCalledForRememberMeSession(): void
    {
        $now = time();
        $rememberMeTtl = 30 * 24 * 3600; // 30 jours
        // Créé il y a 29 jours → TTL restant : 1 jour → ratio ~3% < seuil
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

        $this->assertFalse($refreshCalled, 'Le refresher ne doit PAS être appelé pour une session remember-me');
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
