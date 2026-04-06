<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Core\Exception\HttpException;
use Core\Jwt;
use Middleware\AuthMiddleware;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE = [];
        $_ENV['JWT_SECRET'] = 'test-secret-key-for-unit-tests';
        $_ENV['JWT_EXPIRY']  = '3600';
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleReturns404WhenNoCookie(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404); // 404 — ne révèle pas l'existence des routes protégées

        ob_start();
        try {
            AuthMiddleware::handle();
        } finally {
            ob_end_clean();
        }
    }

    public function testHandleReturnsPayloadForValidToken(): void
    {
        $token = Jwt::generate(1, 'customer');
        $_COOKIE['auth_token'] = $token;

        // Injecte un checker qui simule une session active en base
        $payload = AuthMiddleware::handle(fn() => true);

        $this->assertSame(1, $payload['sub']);
        $this->assertSame('customer', $payload['role']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleReturns404WhenSessionRevoked(): void
    {
        $token = Jwt::generate(1, 'customer');
        $_COOKIE['auth_token'] = $token;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404); // 404 — ne révèle pas l'existence des routes protégées

        ob_start();
        try {
            AuthMiddleware::handle(fn() => false);
        } finally {
            ob_end_clean();
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleReturns404WhenTokenIsInvalid(): void
    {
        $_COOKIE['auth_token'] = 'invalid.token.value';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404); // 404 — ne révèle pas l'existence des routes protégées

        ob_start();
        try {
            AuthMiddleware::handle();
        } finally {
            ob_end_clean();
        }
    }

    // ----------------------------------------------------------------
    // redirectToLogin = true
    // ----------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleRedirectsToLoginWhenNoCookieAndRedirectToLoginTrue(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/mon-compte/commandes';

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302); // redirect vers /{lang}?login=1

        ob_start();
        try {
            AuthMiddleware::handle(redirectToLogin: true);
        } finally {
            ob_end_clean();
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleStoresIntendedInSessionWhenRedirectToLogin(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/mon-compte/profil';

        try {
            ob_start();
            AuthMiddleware::handle(redirectToLogin: true);
        } catch (HttpException) {
            ob_end_clean();
        }

        $this->assertSame('/fr/mon-compte/profil', $_SESSION['_intended'] ?? null);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleExtractsEnLangFromUriWhenRedirectToLogin(): void
    {
        $_SERVER['REQUEST_URI'] = '/en/my-account/orders?page=2';

        $exception = null;
        try {
            ob_start();
            AuthMiddleware::handle(redirectToLogin: true);
        } catch (HttpException $e) {
            ob_end_clean();
            $exception = $e;
        }

        // Redirect URL doit contenir /en?login=1
        $this->assertNotNull($exception);
        $this->assertSame(302, $exception->getCode());
        $this->assertStringContainsString('/en?login=1', $exception->location ?? '');
    }
}
