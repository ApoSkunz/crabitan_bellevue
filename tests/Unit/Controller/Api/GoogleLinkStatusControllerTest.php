<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Api;

use Controller\Api\GoogleLinkStatusController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use Model\GoogleLinkTokenModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour GoogleLinkStatusController::status().
 * Couvre : session vide, token actif, token consommé, token expiré, rate limit.
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
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/api/google-link-status';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * @param bool|null $isConsumedResult Valeur retournée par GoogleLinkTokenModel::isConsumed()
     */
    private function makeController(bool|null $isConsumedResult): GoogleLinkStatusController
    {
        $tokenModel = $this->createStub(GoogleLinkTokenModel::class);
        $tokenModel->method('isConsumed')->willReturn($isConsumedResult);

        $request    = new Request();
        $controller = new GoogleLinkStatusController($request);

        $prop = new \ReflectionProperty(GoogleLinkStatusController::class, 'linkTokens');
        $prop->setValue($controller, $tokenModel);

        return $controller;
    }

    /**
     * @return array{status: int, body: string}
     */
    private function callStatus(GoogleLinkStatusController $ctrl): array
    {
        $body = '';
        ob_start();
        try {
            $ctrl->status([]);
        } catch (HttpException $e) {
            $body = (string) ob_get_clean();
            return ['status' => $e->getCode(), 'body' => $body];
        }
        ob_end_clean();
        $this->fail('Expected HttpException (json response) was not thrown.');
    }

    // ----------------------------------------------------------------

    /**
     * Sans token en session → expired.
     */
    public function testNoTokenInSessionReturnsExpired(): void
    {
        $_SESSION = [];
        $ctrl   = $this->makeController(null);
        $result = $this->callStatus($ctrl);
        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('"expired"', $result['body']);
    }

    /**
     * Token en session, DB retourne null (introuvable/expiré) → expired.
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
     * Token en session, non consommé → pending.
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
     * Token en session, consommé → linked + session nettoyée.
     */
    public function testTokenConsumedReturnsLinked(): void
    {
        $_SESSION['pending_google_link_token']    = 'plaintoken123';
        $_SESSION['google_link_status_last_call'] = 0;
        $ctrl   = $this->makeController(true);
        $result = $this->callStatus($ctrl);
        $this->assertSame(200, $result['status']);
        $this->assertStringContainsString('"linked"', $result['body']);
        $this->assertArrayNotHasKey('pending_google_link_token', $_SESSION);
    }

    /**
     * 2 appels < 1 s → second retourne pending avec 429.
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
