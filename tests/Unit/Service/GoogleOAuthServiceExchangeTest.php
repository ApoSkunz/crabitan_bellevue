<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Core\Exception\GoogleOAuthException;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires GoogleOAuthService — exchangeCode() et fetchUserInfo()
 * via sous-classe stub (sans appel HTTP réel).
 */
class GoogleOAuthServiceExchangeTest extends TestCase
{
    private StubGoogleOAuthService $service;

    protected function setUp(): void
    {
        $this->service = new StubGoogleOAuthService('test-id', 'test-secret');
    }

    // ----------------------------------------------------------------
    // exchangeCode()
    // ----------------------------------------------------------------

    public function testExchangeCodeReturnsAccessToken(): void
    {
        $this->service->postResponse = (string) json_encode(['access_token' => 'tok_abc', 'token_type' => 'Bearer']);

        $result = $this->service->exchangeCode('auth-code', 'http://localhost/callback');

        $this->assertSame('tok_abc', $result['access_token']);
    }

    public function testExchangeCodeThrowsWhenAccessTokenMissing(): void
    {
        $this->service->postResponse = (string) json_encode(['error' => 'invalid_grant']);

        $this->expectException(GoogleOAuthException::class);
        $this->service->exchangeCode('bad-code', 'http://localhost/callback');
    }

    public function testExchangeCodeThrowsWhenHttpPostFails(): void
    {
        $this->service->postFail = true;

        $this->expectException(GoogleOAuthException::class);
        $this->service->exchangeCode('code', 'http://localhost/callback');
    }

    // ----------------------------------------------------------------
    // fetchUserInfo()
    // ----------------------------------------------------------------

    public function testFetchUserInfoReturnsUserData(): void
    {
        $this->service->getResponse = (string) json_encode([
            'sub'         => 'google-user-123',
            'email'       => 'test@example.com',
            'given_name'  => 'Jean',
            'family_name' => 'Dupont',
        ]);

        $result = $this->service->fetchUserInfo('access_token_xyz');

        $this->assertSame('google-user-123', $result['sub']);
        $this->assertSame('test@example.com', $result['email']);
    }

    public function testFetchUserInfoThrowsWhenSubMissing(): void
    {
        $this->service->getResponse = (string) json_encode(['email' => 'test@example.com']);

        $this->expectException(GoogleOAuthException::class);
        $this->service->fetchUserInfo('token');
    }

    public function testFetchUserInfoThrowsWhenEmailMissing(): void
    {
        $this->service->getResponse = (string) json_encode(['sub' => 'google-123']);

        $this->expectException(GoogleOAuthException::class);
        $this->service->fetchUserInfo('token');
    }

    public function testFetchUserInfoThrowsWhenHttpGetFails(): void
    {
        $this->service->getFail = true;

        $this->expectException(GoogleOAuthException::class);
        $this->service->fetchUserInfo('token');
    }
}
