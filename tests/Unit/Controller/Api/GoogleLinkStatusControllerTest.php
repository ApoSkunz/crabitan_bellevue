<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Api;

use Controller\Api\GoogleLinkStatusController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\AccountModel;
use Model\ConnectionModel;
use Model\GoogleLinkTokenModel;
use Model\TrustedDeviceModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour GoogleLinkStatusController::status().
 * Couvre : session vide, token actif, token consommé + session émise, token expiré, rate limit.
 */
class GoogleLinkStatusControllerTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        $_COOKIE  = [];
        $_SERVER['REQUEST_METHOD']  = 'GET';
        $_SERVER['REQUEST_URI']     = '/api/google-link-status';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
        $_ENV['JWT_SECRET']         = 'test-secret-key-minimum-32-chars!!';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_COOKIE  = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * @param bool|null                 $isConsumedResult Valeur retournée par isConsumed()
     * @param array<string,mixed>|false $findByHashResult Valeur retournée par findByHash()
     * @param array<string,mixed>|false $accountResult    Valeur retournée par AccountModel::findById()
     */
    private function makeController(
        bool|null $isConsumedResult,
        array|false $findByHashResult = false,
        array|false $accountResult = false
    ): GoogleLinkStatusController {
        $tokenModel = $this->createStub(GoogleLinkTokenModel::class);
        $tokenModel->method('isConsumed')->willReturn($isConsumedResult);
        $tokenModel->method('findByHash')->willReturn($findByHashResult);

        $accountModel = $this->createStub(AccountModel::class);
        $accountModel->method('findById')->willReturn($accountResult);
        $accountModel->method('hasDeclaredMajority')->willReturn(true);

        $connModel    = $this->createStub(ConnectionModel::class);
        $trustedModel = $this->createStub(TrustedDeviceModel::class);
        $trustedModel->method('isTrusted')->willReturn(false);

        $request    = new Request();
        $controller = new GoogleLinkStatusController($request);

        $this->inject($controller, 'linkTokens', $tokenModel);
        $this->inject($controller, 'accounts', $accountModel);
        $this->inject($controller, 'connections', $connModel);
        $this->inject($controller, 'trustedDevices', $trustedModel);

        return $controller;
    }

    private function inject(object $obj, string $prop, mixed $value): void
    {
        $ref = new \ReflectionProperty($obj, $prop);
        $ref->setValue($obj, $value);
    }

    /**
     * @return array{status: int, body: string}
     */
    private function callStatus(GoogleLinkStatusController $ctrl): array
    {
        ob_start();
        try {
            $ctrl->status([]);
        } catch (HttpException $e) {
            $body = (string) ob_get_clean();
            return ['status' => $e->getCode(), 'body' => $body];
        }
        ob_end_clean();
        $this->fail('Expected HttpException was not thrown.');
    }

    // ----------------------------------------------------------------

    /**
     * Sans token en session → expired.
     */
    public function testNoTokenInSessionReturnsExpired(): void
    {
        $ctrl   = $this->makeController(null);
        $result = $this->callStatus($ctrl);
        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('"expired"', $result['body']);
    }

    /**
     * Token en session, DB retourne null → expired.
     */
    public function testTokenNotFoundInDbReturnsExpired(): void
    {
        $_SESSION['pending_google_link_token'] = 'plaintoken123';
        $ctrl   = $this->makeController(null);
        $result = $this->callStatus($ctrl);
        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('"expired"', $result['body']);
    }

    /**
     * Token actif (non consommé) → pending.
     */
    public function testTokenActiveReturnsPending(): void
    {
        $_SESSION['pending_google_link_token'] = 'plaintoken123';
        $ctrl   = $this->makeController(false);
        $result = $this->callStatus($ctrl);
        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('"pending"', $result['body']);
    }

    /**
     * Token consommé, compte trouvé → linked + redirect + session nettoyée.
     */
    public function testTokenConsumedReturnsLinked(): void
    {
        $_SESSION['pending_google_link_token']    = 'plaintoken123';
        $_SESSION['google_link_status_last_call'] = 0;

        $record  = ['id' => 1, 'account_id' => 42, 'google_id' => 'gid', 'lang' => 'fr', 'consumed_at' => '2026-04-20'];
        $account = ['id' => 42, 'role' => 'user', 'lang' => 'fr', 'has_connected' => '1', 'email' => 'test@test.com'];

        $ctrl   = $this->makeController(true, $record, $account);
        $result = $this->callStatus($ctrl);

        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('"linked"', $result['body']);
        $this->assertStringContainsString('"redirect"', $result['body']);
        $this->assertArrayNotHasKey('pending_google_link_token', $_SESSION);
    }

    /**
     * Token consommé mais compte introuvable → expired.
     */
    public function testTokenConsumedButAccountMissingReturnsExpired(): void
    {
        $_SESSION['pending_google_link_token'] = 'plaintoken123';

        $record = ['id' => 1, 'account_id' => 99, 'google_id' => 'gid', 'lang' => 'fr', 'consumed_at' => '2026-04-20'];

        $ctrl   = $this->makeController(true, $record, false);
        $result = $this->callStatus($ctrl);
        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('"expired"', $result['body']);
    }

    /**
     * Rate limit : 2 appels < 1 s → 429 + pending.
     */
    public function testRateLimitReturnsPending429(): void
    {
        $_SESSION['pending_google_link_token']    = 'plaintoken123';
        $_SESSION['google_link_status_last_call'] = time();

        $ctrl   = $this->makeController(false);
        $result = $this->callStatus($ctrl);
        $this->assertSame(429, $result['status']);
        $this->assertStringContainsString('"pending"', $result['body']);
    }
}
