<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Api;

use Controller\Api\CartApiController;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CartApiController (stubs 501).
 */
class CartApiControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/api/panier/ajouter';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';
    }

    protected function tearDown(): void
    {
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    private function makeController(): CartApiController
    {
        return new CartApiController(new Request());
    }

    /**
     * add retourne 501 Not Implemented.
     */
    public function testAddReturns501(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(501);

        ob_start();
        try {
            $this->makeController()->add([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * update retourne 501 Not Implemented.
     */
    public function testUpdateReturns501(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(501);

        ob_start();
        try {
            $this->makeController()->update([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * remove retourne 501 Not Implemented.
     */
    public function testRemoveReturns501(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(501);

        ob_start();
        try {
            $this->makeController()->remove([]);
        } finally {
            ob_end_clean();
        }
    }
}
