<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset superglobals
        $_SERVER = [];
        $_GET    = [];
        $_POST   = [];
    }

    private function makeRequest(array $server = [], array $get = [], array $post = []): Request
    {
        $_SERVER = array_merge([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI'    => '/',
        ], $server);
        $_GET  = $get;
        $_POST = $post;

        return new Request();
    }

    public function testMethodIsUppercased(): void
    {
        $request = $this->makeRequest(['REQUEST_METHOD' => 'post']);
        $this->assertSame('POST', $request->method);
    }

    public function testPathExtractedFromUri(): void
    {
        $request = $this->makeRequest(['REQUEST_URI' => '/fr/vins?page=2']);
        $this->assertSame('/fr/vins', $request->path);
    }

    public function testQueryParamsAvailable(): void
    {
        $request = $this->makeRequest([], ['page' => '2', 'color' => 'red']);
        $this->assertSame('2', $request->get('page'));
        $this->assertSame('red', $request->get('color'));
    }

    public function testGetReturnsDefaultIfMissing(): void
    {
        $request = $this->makeRequest();
        $this->assertSame('default', $request->get('missing', 'default'));
    }

    public function testPostBodyAvailable(): void
    {
        $request = $this->makeRequest(['REQUEST_METHOD' => 'POST'], [], ['email' => 'a@b.com']);
        $this->assertSame('a@b.com', $request->post('email'));
    }

    public function testBearerTokenExtracted(): void
    {
        $request = $this->makeRequest([
            'REQUEST_METHOD'          => 'GET',
            'REQUEST_URI'             => '/',
            'HTTP_AUTHORIZATION'      => 'Bearer mytoken123',
        ]);
        $this->assertSame('mytoken123', $request->bearerToken());
    }

    public function testBearerTokenNullIfAbsent(): void
    {
        $request = $this->makeRequest();
        $this->assertNull($request->bearerToken());
    }

    public function testIsJson(): void
    {
        $request = $this->makeRequest(['HTTP_CONTENT_TYPE' => 'application/json']);
        $this->assertTrue($request->isJson());
    }

    public function testIsNotJson(): void
    {
        $request = $this->makeRequest(['HTTP_CONTENT_TYPE' => 'text/html']);
        $this->assertFalse($request->isJson());
    }

    public function testIsAjax(): void
    {
        $request = $this->makeRequest(['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $this->assertTrue($request->isAjax());
    }

    public function testIsNotAjax(): void
    {
        $request = $this->makeRequest();
        $this->assertFalse($request->isAjax());
    }

    public function testDefaultMethodIsGet(): void
    {
        $request = $this->makeRequest([]);
        $this->assertSame('GET', $request->method);
    }
}
