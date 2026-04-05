<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Api;

use Controller\Api\SessionApiController;
use Core\Database;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour SessionApiController (GET /api/session/ping).
 * La base de données et le JWT sont mockés — aucune connexion réelle requise.
 */
class SessionApiControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);

        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/session/ping';
        $_ENV['JWT_SECRET']        = 'test-secret-key-minimum-32-chars!!';
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_COOKIE = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée un stub Database avec fetchOne contrôlable.
     *
     * @param array<string,mixed>|false $sessionRow Résultat simulé du SELECT connections
     * @return Database
     */
    private function mockDb(array|false $sessionRow): Database
    {
        $db = $this->createStub(Database::class);
        $db->method('fetchOne')->willReturn($sessionRow);
        $this->instanceProp->setValue(null, $db);
        return $db;
    }

    // ----------------------------------------------------------------
    // ping() — sans cookie
    // ----------------------------------------------------------------

    /**
     * Sans cookie auth_token, ping() retourne 401.
     *
     * @return void
     */
    public function testPingReturns401WhenNoCookie(): void
    {
        $this->mockDb(false);
        $_COOKIE = [];

        $ctrl = new SessionApiController(new Request());

        ob_start();
        try {
            $ctrl->ping();
        } catch (\Throwable) {
        }
        ob_get_clean();

        $this->assertSame(401, http_response_code());
    }

    // ----------------------------------------------------------------
    // ping() — cookie invalide (JWT malformé)
    // ----------------------------------------------------------------

    /**
     * Un cookie présent mais JWT invalide → 401.
     *
     * @return void
     */
    public function testPingReturns401WithInvalidJwt(): void
    {
        $this->mockDb(false);
        $_COOKIE['auth_token'] = 'not.a.valid.jwt';

        $ctrl = new SessionApiController(new Request());

        ob_start();
        try {
            $ctrl->ping();
        } catch (\Throwable) {
        }
        ob_get_clean();

        $this->assertSame(401, http_response_code());
    }

    // ----------------------------------------------------------------
    // ping() — session révoquée en base
    // ----------------------------------------------------------------

    /**
     * JWT valide mais connexion révoquée en base → 401.
     *
     * @return void
     */
    public function testPingReturns401WhenSessionRevoked(): void
    {
        $this->mockDb(false); // fetchOne retourne false = pas de session active

        // Forge un JWT minimal valide
        $_COOKIE['auth_token'] = $this->forgeJwt();

        $ctrl = new SessionApiController(new Request());

        ob_start();
        try {
            $ctrl->ping();
        } catch (\Throwable) {
        }
        ob_get_clean();

        $this->assertSame(401, http_response_code());
    }

    // ----------------------------------------------------------------
    // ping() — session active → 204
    // ----------------------------------------------------------------

    /**
     * JWT valide et session active en base → 204.
     *
     * @return void
     */
    public function testPingReturns204WhenSessionActive(): void
    {
        $this->mockDb(['id' => 1]); // fetchOne retourne une ligne = session active
        $_COOKIE['auth_token'] = $this->forgeJwt();

        $ctrl = new SessionApiController(new Request());

        ob_start();
        try {
            $ctrl->ping();
        } catch (\Throwable) {
        }
        ob_get_clean();

        $this->assertSame(204, http_response_code());
    }

    // ----------------------------------------------------------------
    // Helpers internes
    // ----------------------------------------------------------------

    /**
     * Génère un JWT minimal valide pour les tests (secret = $_ENV['JWT_SECRET']).
     *
     * @return string
     */
    /** Encode en base64url (sans padding, chars URL-safe) — même format que Jwt::encode(). */
    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function forgeJwt(): string
    {
        $header  = $this->b64url((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = $this->b64url((string) json_encode([
            'sub' => 1,
            'exp' => time() + 3600,
            'iat' => time(),
        ]));
        $sig = $this->b64url(hash_hmac('sha256', $header . '.' . $payload, $_ENV['JWT_SECRET'], true));
        return $header . '.' . $payload . '.' . $sig;
    }
}
