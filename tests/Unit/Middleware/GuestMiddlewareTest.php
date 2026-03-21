<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Core\Exception\HttpException;
use Core\Jwt;
use Middleware\GuestMiddleware;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class GuestMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE = [];
        $_ENV['JWT_SECRET'] = 'test-secret-key-for-unit-tests';
        $_ENV['JWT_EXPIRY']  = '3600';
    }

    public function testHandleDoesNothingWhenNoCookie(): void
    {
        // Should return void without throwing
        GuestMiddleware::handle();
        $this->assertTrue(true);
    }

    public function testHandleDoesNothingWhenTokenIsInvalid(): void
    {
        $_COOKIE['auth_token'] = 'bad.token.here';

        // Invalid token → silently ignored, no redirect
        GuestMiddleware::handle();
        $this->assertTrue(true);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleRedirectsWhenValidTokenPresent(): void
    {
        $_COOKIE['auth_token'] = Jwt::generate(1, 'customer');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        GuestMiddleware::handle();
    }
}
