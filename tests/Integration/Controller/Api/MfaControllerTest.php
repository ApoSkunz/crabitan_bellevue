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
    // poll — token confirmé → status=ok (happy path)
    // ----------------------------------------------------------------

    /**
     * Un token confirmé émet le JWT et retourne status=ok + redirect.
     */
    public function testPollWithConfirmedTokenReturnsOk(): void
    {
        $token       = bin2hex(random_bytes(16));
        $deviceToken = 'dev-confirmed-' . bin2hex(random_bytes(8));

        self::$db->insert(
            "INSERT INTO device_confirm_tokens
             (user_id, device_token, device_name, token, expires_at, confirmed_at)
             VALUES (?, ?, 'Chrome · Test', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())",
            [$this->userId, $deviceToken, $token]
        );

        $_GET['token'] = $token;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->poll([]);
        } finally {
            $raw  = ob_get_clean();
            $json = json_decode(is_string($raw) ? $raw : '', true);
            if ($json !== null) {
                $this->assertSame('ok', $json['status']);
                $this->assertArrayHasKey('redirect', $json);
            }
        }
    }

    /**
     * Un token confirmé avec redirect_url explicite retourne cette URL.
     */
    public function testPollWithConfirmedTokenUsesRedirectUrl(): void
    {
        $token       = bin2hex(random_bytes(16));
        $deviceToken = 'dev-redir-' . bin2hex(random_bytes(8));

        self::$db->insert(
            "INSERT INTO device_confirm_tokens
             (user_id, device_token, device_name, token, expires_at, confirmed_at, redirect_url)
             VALUES (?, ?, 'Firefox · Test', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW(), '/fr/mon-compte')",
            [$this->userId, $deviceToken, $token]
        );

        $_GET['token'] = $token;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->poll([]);
        } finally {
            $raw  = ob_get_clean();
            $json = json_decode(is_string($raw) ? $raw : '', true);
            if ($json !== null) {
                $this->assertSame('ok', $json['status']);
                $this->assertSame('/fr/mon-compte', $json['redirect']);
            }
        }
    }

    /**
     * Un token confirmé pour un compte non vérifié (email_verified_at NULL) retourne expired.
     */
    public function testPollWithConfirmedTokenUnverifiedAccountReturnsExpired(): void
    {
        $unverifiedId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang)
             VALUES (?, ?, 'customer', 'fr')",
            ['mfa.unverified.' . bin2hex(random_bytes(4)) . '@test.local', password_hash('Pass123!', PASSWORD_BCRYPT)]
        );

        $token       = bin2hex(random_bytes(16));
        $deviceToken = 'dev-unver-' . bin2hex(random_bytes(8));

        self::$db->insert(
            "INSERT INTO device_confirm_tokens
             (user_id, device_token, device_name, token, expires_at, confirmed_at)
             VALUES (?, ?, 'Chrome · Test', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())",
            [$unverifiedId, $deviceToken, $token]
        );

        $_GET['token'] = $token;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->poll([]);
        } finally {
            $raw  = ob_get_clean();
            $json = json_decode(is_string($raw) ? $raw : '', true);
            if ($json !== null) {
                $this->assertSame('expired', $json['status']);
            }
        }
    }

    /**
     * Un token confirmé pour un compte avec has_connected=0 appelle markAsConnected.
     */
    public function testPollWithConfirmedTokenMarksAccountAsConnected(): void
    {
        // has_connected = 0 par défaut à la création du compte
        $token       = bin2hex(random_bytes(16));
        $deviceToken = 'dev-connect-' . bin2hex(random_bytes(8));

        self::$db->insert(
            "INSERT INTO device_confirm_tokens
             (user_id, device_token, device_name, token, expires_at, confirmed_at)
             VALUES (?, ?, 'Safari · Test', ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())",
            [$this->userId, $deviceToken, $token]
        );

        $_GET['token'] = $token;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->makeController()->poll([]);
        } finally {
            ob_get_clean();
            $account = self::$db->fetchOne('SELECT has_connected FROM accounts WHERE id = ?', [$this->userId]);
            if ($account !== false) {
                $this->assertSame(1, (int) $account['has_connected']);
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
