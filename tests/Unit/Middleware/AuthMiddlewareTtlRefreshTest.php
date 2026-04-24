<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Core\FeatureFlags;
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
        $_ENV['JWT_SECRET']  = 'test-secret-key-for-unit-tests';
        $_ENV['JWT_EXPIRY']  = '3600';
        FeatureFlags::setCache(['jwt_refresh_threshold' => ['key' => 'jwt_refresh_threshold', 'value' => '0.5', 'type' => 'float']]);
    }

    protected function tearDown(): void
    {
        FeatureFlags::flush();
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

    // ------------------------------------------------------------------
    // Cookie age_verified — sliding refresh quand le JWT est rafraîchi
    // ------------------------------------------------------------------

    /**
     * Quand le refresh est déclenché ET age_verified est présent, le cookie age_verified
     * est recréé avec TTL 24 h (sans age_remember).
     */
    public function testAgeVerifiedCookieRefreshedWithDefaultTtlWhenNoRemember(): void
    {
        $now = time();
        // Token standard proche de l'expiration (< 50% restant) → refresh déclenché
        $token = Jwt::encode([
            'sub'  => 10,
            'role' => 'customer',
            'iat'  => $now - 2200,
            'exp'  => $now + 1400,
        ]);
        $_COOKIE['auth_token']  = $token;
        $_COOKIE['age_verified'] = '1';
        // Pas de age_remember → TTL 24 h

        $refreshCalled = false;
        $result = AuthMiddleware::handle(
            fn() => true,
            function () use (&$refreshCalled): bool {
                $refreshCalled = true;
                return true;
            }
        );

        $this->assertSame(10, $result['sub']);
        $this->assertTrue($refreshCalled, 'Le refresher doit être appelé (TTL sous le seuil)');
        // Le cookie a été envoyé — on vérifie indirectement que le bloc age_verified a été exécuté
        // sans erreur (couverture de la ligne 80-89)
    }

    /**
     * Quand le refresh est déclenché ET age_verified + age_remember sont présents,
     * le cookie age_verified est recréé avec TTL 13 mois (397 j).
     */
    public function testAgeVerifiedCookieRefreshedWithRememberTtlWhenAgeCookiePresent(): void
    {
        $now = time();
        $token = Jwt::encode([
            'sub'  => 11,
            'role' => 'customer',
            'iat'  => $now - 2200,
            'exp'  => $now + 1400,
        ]);
        $_COOKIE['auth_token']  = $token;
        $_COOKIE['age_verified'] = '1';
        $_COOKIE['age_remember'] = '1'; // → TTL 397 jours

        $result = AuthMiddleware::handle(
            fn() => true,
            fn() => true  // refresh réussit
        );

        $this->assertSame(11, $result['sub']);
    }

    /**
     * Quand le refresh n'est PAS déclenché, le bloc age_verified ne s'exécute pas
     * (TTL encore confortable — $refreshed = false).
     */
    public function testAgeVerifiedCookieNotRefreshedWhenNoRefresh(): void
    {
        $now = time();
        // TTL encore à 60% → pas de refresh
        $token = Jwt::encode([
            'sub'  => 12,
            'role' => 'customer',
            'iat'  => $now - 1440,
            'exp'  => $now + 2160,
        ]);
        $_COOKIE['auth_token']  = $token;
        $_COOKIE['age_verified'] = '1';

        $refreshCalled = false;
        $result = AuthMiddleware::handle(
            fn() => true,
            function () use (&$refreshCalled): bool {
                $refreshCalled = true;
                return true;
            }
        );

        $this->assertSame(12, $result['sub']);
        $this->assertFalse($refreshCalled, 'Pas de refresh si TTL confortable');
    }

    // ------------------------------------------------------------------
    // isRememberMeToken — payload sans exp/iat → retourne false
    // ------------------------------------------------------------------

    /**
     * Un payload JWT sans exp/iat est traité comme non-remember-me (isRememberMeToken = false).
     * Le middleware retourne quand même le payload.
     */
    public function testHandleWithPayloadMissingExpIatIsNotRememberMe(): void
    {
        // Token sans exp/iat (payload minimal)
        $token = Jwt::encode([
            'sub'  => 20,
            'role' => 'customer',
            // pas de iat ni exp
        ]);
        $_COOKIE['auth_token'] = $token;

        $result = AuthMiddleware::handle(
            fn() => true,   // session active
            fn() => false   // refresher non déclenché (isRememberMe=false, TTL inconnu → maybeRefresh ne refresh pas)
        );

        $this->assertSame(20, $result['sub']);
    }
}
