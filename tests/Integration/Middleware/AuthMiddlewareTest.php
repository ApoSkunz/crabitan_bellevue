<?php

declare(strict_types=1);

namespace Tests\Integration\Middleware;

use Core\Exception\HttpException;
use Core\Jwt;
use Middleware\AuthMiddleware;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AuthMiddleware.
 * Couvre les chemins réels avec BDD : vérification session active / révoquée.
 */
class AuthMiddlewareTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE = [];
        $_ENV['JWT_SECRET']            = $_ENV['JWT_SECRET']            ?? 'integration-test-secret';
        $_ENV['JWT_EXPIRY']            = $_ENV['JWT_EXPIRY']            ?? '3600';
        $_ENV['JWT_REFRESH_THRESHOLD'] = $_ENV['JWT_REFRESH_THRESHOLD'] ?? '0.5';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un compte minimal et retourne son ID.
     *
     * @param string $email Adresse e-mail
     * @return int
     */
    private function insertAccount(string $email): int
    {
        return (int) self::$db->insert(
            "INSERT INTO accounts (email, password, account_type, role, lang, email_verified_at)
             VALUES (?, ?, 'individual', 'customer', 'fr', NOW())",
            [$email, password_hash('Password1!', PASSWORD_BCRYPT)]
        );
    }

    /**
     * Insère une connexion active en base et positionne le cookie JWT.
     *
     * @param int    $userId ID du compte
     * @param string $status Statut de la connexion ('active' ou 'revoked')
     * @return string Token JWT généré
     */
    private function createConnection(int $userId, string $status = 'active'): string
    {
        $token = Jwt::generate($userId, 'customer');
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token, $status]
        );
        return $token;
    }

    // ----------------------------------------------------------------
    // Tests couvrant les default closures (checker et refresher réels)
    // ----------------------------------------------------------------

    /**
     * Session active en base avec checkers null → payload retourné (checker réel exécuté).
     */
    public function testHandleWithRealCheckerReturnsPayloadForActiveSession(): void
    {
        $userId = $this->insertAccount('auth.mw.active@test.local');
        $token  = $this->createConnection($userId);
        $_COOKIE['auth_token'] = $token;

        // Appel avec null → active le checker et le refresher par défaut (corps des closures couverts)
        $payload = AuthMiddleware::handle(null, null);

        $this->assertSame($userId, $payload['sub']);
        $this->assertSame('customer', $payload['role']);
    }

    /**
     * Session révoquée en base → checker réel retourne false → abort 404.
     */
    public function testHandleWithRealCheckerAborts404ForRevokedSession(): void
    {
        $userId = $this->insertAccount('auth.mw.revoked@test.local');
        $token  = $this->createConnection($userId, 'revoked');
        $_COOKIE['auth_token'] = $token;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            AuthMiddleware::handle(null, null);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Aucune connexion en base pour ce token → checker réel retourne false → abort 404.
     */
    public function testHandleWithRealCheckerAborts404WhenNoConnectionInDb(): void
    {
        // Token valide JWT mais aucune ligne en base
        $token = Jwt::generate(9999, 'customer');
        $_COOKIE['auth_token'] = $token;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            AuthMiddleware::handle(null, null);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Token proche de l'expiration + refresher null → refresher réel exécuté (ConnectionModel::refreshExpiry).
     * Couvre les lignes 70-71 de la default closure $refresher.
     */
    public function testHandleWithRealRefresherExecutesRefreshExpiry(): void
    {
        $userId = $this->insertAccount('auth.mw.refresh@test.local');

        $now = time();
        // Token standard avec TTL restant < 50% (seuil 0.5 × 3600 = 1800 s)
        $token = Jwt::encode([
            'sub'  => $userId,
            'role' => 'customer',
            'iat'  => $now - 2200,
            'exp'  => $now + 1400,
        ]);
        self::$db->insert(
            "INSERT INTO connections (user_id, token, auth_method, status, expired_at)
             VALUES (?, ?, 'password', 'active', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$userId, $token]
        );
        $_COOKIE['auth_token'] = $token;

        // Appel avec checker null et refresher null → les deux closures par défaut s'exécutent
        $payload = AuthMiddleware::handle(null, null);

        $this->assertSame($userId, $payload['sub']);
    }
}
