<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\TestController;

require_once __DIR__ . '/../Stubs/TestController.php';

class ControllerTest extends TestCase
{
    private TestController $controller;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/test';
        $_GET  = [];
        $_POST = [];
        $this->controller = new TestController(new Request());
    }

    public function testJsonThrowsHttpException(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(200);

        ob_start();
        try {
            $this->controller->callJson(['ok' => true]);
        } finally {
            ob_end_clean();
        }
    }

    public function testRedirectThrowsHttpException(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(302);

        $this->controller->callRedirect('/fr/connexion');
    }

    public function testAbortThrowsHttpException(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);

        ob_start();
        try {
            $this->controller->callAbort(404);
        } finally {
            ob_end_clean();
        }
    }

    public function testLangReturnsDefaultWhenCurrentLangNotDefined(): void
    {
        // CURRENT_LANG is not defined in bootstrap — falls back to DEFAULT_LANG ('fr')
        $result = $this->controller->callLang();
        $this->assertSame('fr', $result);
    }
}
