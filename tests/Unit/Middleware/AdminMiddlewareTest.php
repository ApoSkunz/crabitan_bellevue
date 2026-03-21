<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Core\Exception\HttpException;
use Core\Jwt;
use Middleware\AdminMiddleware;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class AdminMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE = [];
        $_ENV['JWT_SECRET'] = 'test-secret-key-for-unit-tests';
        $_ENV['JWT_EXPIRY']  = '3600';
    }

    public function testHandleReturnsPayloadForAdminRole(): void
    {
        $_COOKIE['auth_token'] = Jwt::generate(1, 'admin');

        $payload = AdminMiddleware::handle();

        $this->assertSame('admin', $payload['role']);
    }

    public function testHandleReturnsPayloadForSuperAdminRole(): void
    {
        $_COOKIE['auth_token'] = Jwt::generate(2, 'super_admin');

        $payload = AdminMiddleware::handle();

        $this->assertSame('super_admin', $payload['role']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleAborts403ForCustomerRole(): void
    {
        $_COOKIE['auth_token'] = Jwt::generate(3, 'customer');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ob_start();
        try {
            AdminMiddleware::handle();
        } finally {
            ob_end_clean();
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleRedirectsWhenNoToken(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        AdminMiddleware::handle();
    }
}
