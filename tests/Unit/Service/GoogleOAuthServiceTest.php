<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\GoogleOAuthService;

/**
 * Tests unitaires GoogleOAuthService — uniquement les méthodes sans appel HTTP.
 */
class GoogleOAuthServiceTest extends TestCase
{
    private GoogleOAuthService $service;

    protected function setUp(): void
    {
        $this->service = new GoogleOAuthService('test-client-id', 'test-client-secret');
    }

    public function testBuildAuthUrlContainsClientId(): void
    {
        $url = $this->service->buildAuthUrl('http://localhost/fr/auth/google/callback', 'random-state');
        $this->assertStringContainsString('client_id=test-client-id', $url);
    }

    public function testBuildAuthUrlContainsState(): void
    {
        $url = $this->service->buildAuthUrl('http://localhost/fr/auth/google/callback', 'my-state-123');
        $this->assertStringContainsString('state=my-state-123', $url);
    }

    public function testBuildAuthUrlContainsRedirectUri(): void
    {
        $redirectUri = 'http://localhost/fr/auth/google/callback';
        $url         = $this->service->buildAuthUrl($redirectUri, 'state');
        $this->assertStringContainsString('redirect_uri=' . urlencode($redirectUri), $url);
    }

    public function testBuildAuthUrlContainsEmailScope(): void
    {
        $url = $this->service->buildAuthUrl('http://localhost/fr/auth/google/callback', 'state');
        $this->assertStringContainsString('scope=', $url);
        $this->assertStringContainsString('email', urldecode($url));
        $this->assertStringContainsString('profile', urldecode($url));
    }

    public function testBuildAuthUrlPointsToGoogle(): void
    {
        $url = $this->service->buildAuthUrl('http://localhost/fr/auth/google/callback', 'state');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $url);
    }

    public function testBuildAuthUrlContainsResponseTypeCode(): void
    {
        $url = $this->service->buildAuthUrl('http://localhost/fr/auth/google/callback', 'state');
        $this->assertStringContainsString('response_type=code', $url);
    }
}
