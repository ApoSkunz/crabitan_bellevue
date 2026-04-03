<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Core\Exception\GoogleOAuthException;
use PHPUnit\Framework\TestCase;
use Service\GoogleOAuthService;

/**
 * Tests unitaires GoogleOAuthService — httpPost() et httpGet() (implémentation réelle).
 *
 * On invoque ces méthodes protégées via ReflectionMethod avec une URL injoignable
 * pour déclencher le chemin d'erreur (file_get_contents retourne false → exception).
 */
class GoogleOAuthServiceHttpTest extends TestCase
{
    private GoogleOAuthService $service;
    private \ReflectionMethod $httpPost;
    private \ReflectionMethod $httpGet;

    protected function setUp(): void
    {
        $this->service  = new GoogleOAuthService('test-id', 'test-secret');
        $this->httpPost = new \ReflectionMethod($this->service, 'httpPost');
        $this->httpGet  = new \ReflectionMethod($this->service, 'httpGet');
    }

    // ----------------------------------------------------------------
    // httpPost() — implémentation réelle
    // ----------------------------------------------------------------

    public function testHttpPostThrowsGoogleOAuthExceptionOnConnectionFailure(): void
    {
        // Port 1 — aucun service système ne devrait écouter sur ce port
        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('Google OAuth HTTP POST failed');

        $this->httpPost->invoke($this->service, 'http://localhost:1/token', 'code=test');
    }

    // ----------------------------------------------------------------
    // httpGet() — implémentation réelle
    // ----------------------------------------------------------------

    public function testHttpGetThrowsGoogleOAuthExceptionOnConnectionFailure(): void
    {
        $this->expectException(GoogleOAuthException::class);
        $this->expectExceptionMessage('Google OAuth HTTP GET failed');

        $this->httpGet->invoke($this->service, 'http://localhost:1/userinfo', 'fake-token');
    }
}
