<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Exception\HttpException;
use Core\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function testJsonThrowsHttpExceptionWithDefaultStatus(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            Response::json(['key' => 'value']);
        } finally {
            ob_end_clean();
        }
    }

    public function testJsonThrowsHttpExceptionWithCustomStatus(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(422);

        ob_start();
        try {
            Response::json(['error' => 'invalid'], 422);
        } finally {
            ob_end_clean();
        }
    }

    public function testRedirectThrowsHttpException(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        Response::redirect('/fr/connexion');
    }

    public function testRedirectStoresLocation(): void
    {
        try {
            Response::redirect('/fr/connexion', 301);
        } catch (HttpException $e) {
            $this->assertSame(301, $e->status);
            $this->assertSame('/fr/connexion', $e->location);
        }
    }

    public function testAbortThrowsHttpException(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            Response::abort(404);
        } finally {
            ob_end_clean();
        }
    }

    public function testAbortWithCustomStatusAndMessage(): void
    {
        ob_start();
        try {
            Response::abort(403, 'Accès refusé');
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertSame(403, $e->status);
            $this->assertSame('Accès refusé', $e->getMessage());
        }
    }

    public function testSetHeaderDoesNotThrow(): void
    {
        // header() is a no-op in CLI; just assert no exception is thrown
        Response::setHeader('X-Custom-Header', 'TestValue');
        $this->assertTrue(true);
    }
}
