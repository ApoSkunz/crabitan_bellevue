<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Core\Exception\HttpException;
use Core\CookieHelper;
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

        $payload = AdminMiddleware::handle(fn() => true, fn() => false);

        $this->assertSame('admin', $payload['role']);
    }

    public function testHandleReturnsPayloadForSuperAdminRole(): void
    {
        $_COOKIE['auth_token'] = Jwt::generate(2, 'super_admin');

        $payload = AdminMiddleware::handle(fn() => true, fn() => false);

        $this->assertSame('super_admin', $payload['role']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleRedirectsAndFlashesWhenAccountIsBlocked(): void
    {
        $_COOKIE['auth_token'] = Jwt::generate(1, 'admin');

        $redirected = false;
        try {
            ob_start();
            AdminMiddleware::handle(fn() => true, fn() => true);
        } catch (HttpException $e) {
            ob_end_clean();
            $redirected = true;
            $this->assertSame(302, $e->getCode());
        }

        $this->assertTrue($redirected);
        $this->assertSame(
            'Votre compte administrateur a été bloqué. Contactez votre super administrateur.',
            $_SESSION['flash']['modal_error'] ?? null
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleAborts404ForCustomerRole(): void
    {
        $_COOKIE['auth_token'] = Jwt::generate(3, 'customer');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            AdminMiddleware::handle(fn() => true);
        } finally {
            ob_end_clean();
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleReturns404WhenNoToken(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404); // 404 — path enumeration prevention (via AuthMiddleware)

        ob_start();
        try {
            AdminMiddleware::handle();
        } finally {
            ob_end_clean();
        }
    }
}
