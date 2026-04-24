<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Core\Database;
use Core\FeatureFlags;
use Core\Jwt;
use Middleware\AuthMiddleware;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests de couverture complémentaires pour AuthMiddleware.
 *
 * Couvre la branche du refresher par défaut (remember-me path) : AuthMiddleware.php:74.
 * Le refresher par défaut crée un ConnectionModel et appelle refreshExpiry() pour les sessions
 * remember-me. On mocke la base de données via le singleton Database pour éviter la connexion réelle.
 */
#[AllowMockObjectsWithoutExpectations]
class AuthMiddlewareCoverageTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $_COOKIE = [];
        $_ENV['JWT_SECRET'] = 'test-secret-key-for-unit-tests';
        $_ENV['JWT_EXPIRY']  = '3600'; // 1h

        FeatureFlags::setCache([
            'jwt_refresh_threshold' => [
                'key'   => 'jwt_refresh_threshold',
                'value' => '0.5',
                'type'  => 'float',
            ],
        ]);

        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
    }

    protected function tearDown(): void
    {
        FeatureFlags::flush();
        $this->instanceProp->setValue(null, null);
    }

    /**
     * Le refresher par défaut (remember-me) appelle ConnectionModel::refreshExpiry().
     * Couvre AuthMiddleware.php:74 : return $model->refreshExpiry($t, $expiry).
     *
     * Conditions :
     *  - Token remember-me (durée = 30j >> JWT_EXPIRY = 1h)
     *  - TTL restant < seuil 50% (token vieux de 28j, expire dans 2j)
     *  - Pas de $ttlRefresher injecté → le refresher par défaut s'exécute
     *  - DB mockée : execute() retourne 1 pour que refreshExpiry() retourne true
     */
    public function testDefaultRefresherCallsRefreshExpiryForRememberMeToken(): void
    {
        $now = time();

        // Token remember-me : iat = 28 jours en arrière, exp = dans 2 jours
        // Durée totale = 30j >> JWT_EXPIRY (1h) → détecté comme remember-me
        // TTL restant = 2j / 30j ≈ 6,7% < 50% → refresh déclenché
        $token = Jwt::encode([
            'sub'  => 99,
            'role' => 'customer',
            'iat'  => $now - (28 * 86400),
            'exp'  => $now + (2 * 86400),
        ]);
        $_COOKIE['auth_token'] = $token;

        // Mock Database pour que ConnectionModel::refreshExpiry() ne plante pas
        $dbMock = $this->createMock(Database::class);
        $dbMock->method('execute')->willReturn(1); // 1 row affected = token mis à jour
        $this->instanceProp->setValue(null, $dbMock);

        // Appel sans $ttlRefresher → chemin par défaut (line 70-85)
        // $sessionChecker = fn() => true : session active
        $payload = AuthMiddleware::handle(fn() => true);

        $this->assertSame(99, $payload['sub']);
    }
}
