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

    /**
     * Vérifie que le body JSON d'une requête POST est décodé correctement via php://input.
     *
     * @return void
     */
    public function testPostBodyParsesJsonContentType(): void
    {
        // Enregistre le mock de stream pour php://input
        \Tests\Unit\Stubs\PhpInputMockStream::$inputData = '{"key":"value","num":42}';
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', \Tests\Unit\Stubs\PhpInputMockStream::class);

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI'    => '/',
                'CONTENT_TYPE'   => 'application/json; charset=utf-8',
            ];
            $_GET  = [];
            $_POST = [];

            $request = new Request();

            $this->assertSame('value', $request->post('key'));
            $this->assertSame(42, $request->post('num'));
        } finally {
            stream_wrapper_restore('php');
        }
    }

    /**
     * Vérifie qu'un body JSON invalide est traité comme un tableau vide.
     *
     * @return void
     */
    public function testPostBodyReturnsEmptyArrayOnInvalidJson(): void
    {
        \Tests\Unit\Stubs\PhpInputMockStream::$inputData = '{invalid json}';
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', \Tests\Unit\Stubs\PhpInputMockStream::class);

        try {
            $_SERVER = [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI'    => '/',
                'CONTENT_TYPE'   => 'application/json',
            ];
            $_GET  = [];
            $_POST = [];

            $request = new Request();

            $this->assertSame([], $request->body);
        } finally {
            stream_wrapper_restore('php');
        }
    }
}
