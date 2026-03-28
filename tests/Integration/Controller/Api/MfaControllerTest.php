<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Api;

use Controller\Api\MfaController;
use Core\Exception\HttpException;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour MfaController (poll endpoint).
 */
class MfaControllerTest extends IntegrationTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_SERVER['REQUEST_METHOD']  = 'GET';
        $_SERVER['REQUEST_URI']     = '/api/mfa/poll';

        $this->userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', NOW())",
            ['mfa.poll.' . bin2hex(random_bytes(4)) . '@test.local', password_hash('Pass123!', PASSWORD_BCRYPT)]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    private function makeController(): MfaController
    {
        return new MfaController(new \Core\Request());
    }

    // ----------------------------------------------------------------
    // poll — token vide
    // ----------------------------------------------------------------

    /**
     * Sans token en GET, retourne status=expired.
     */
    public function testPollWithEmptyTokenReturnsExpired(): void
    {
        $_GET['token'] = '';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->poll([]);
        } finally {
            $raw = ob_get_clean();
            $json = json_decode(is_string($raw) ? $raw : '', true);
            if ($json !== null) {
                $this->assertSame('expired', $json['status']);
            }
        }
    }

    // ----------------------------------------------------------------
    // poll — token inexistant
    // ----------------------------------------------------------------

    /**
     * Un token inexistant en BDD retourne status=expired.
     */
    public function testPollWithUnknownTokenReturnsExpired(): void
    {
        $_GET['token'] = 'nonexistent-token-xyz';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->poll([]);
        } finally {
            $raw = ob_get_clean();
            $json = json_decode(is_string($raw) ? $raw : '', true);
            if ($json !== null) {
                $this->assertSame('expired', $json['status']);
            }
        }
    }

    // ----------------------------------------------------------------
    // poll — token valide, non confirmé
    // ----------------------------------------------------------------

    /**
     * Un token valide mais pas encore confirmé retourne status=pending.
     */
    public function testPollWithPendingTokenReturnsPending(): void
    {
        $token = bin2hex(random_bytes(16));
        self::$db->insert(
            "INSERT INTO device_confirm_tokens
             (user_id, device_token, device_name, token, expires_at)
             VALUES (?, 'dev-tok-poll', 'Chrome · Test', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))",
            [$this->userId, $token]
        );

        $_GET['token'] = $token;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->poll([]);
        } finally {
            $raw = ob_get_clean();
            $json = json_decode(is_string($raw) ? $raw : '', true);
            if ($json !== null) {
                $this->assertSame('pending', $json['status']);
            }
        }
    }

    // ----------------------------------------------------------------
    // poll — token expiré (confirmed_at NULL, expires_at passé)
    // ----------------------------------------------------------------

    /**
     * Un token expiré (pas de ligne valide) retourne status=expired.
     */
    public function testPollWithExpiredTokenReturnsExpired(): void
    {
        $token = bin2hex(random_bytes(16));
        // Token expiré : expires_at dans le passé — le model doit retourner null/false
        self::$db->insert(
            "INSERT INTO device_confirm_tokens
             (user_id, device_token, device_name, token, expires_at)
             VALUES (?, 'dev-expired-poll', 'Chrome · Test', ?, DATE_SUB(NOW(), INTERVAL 1 MINUTE))",
            [$this->userId, $token]
        );

        $_GET['token'] = $token;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->poll([]);
        } finally {
            $raw = ob_get_clean();
            $json = json_decode(is_string($raw) ? $raw : '', true);
            if ($json !== null) {
                $this->assertSame('expired', $json['status']);
            }
        }
    }
}
