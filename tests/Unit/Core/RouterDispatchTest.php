<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Exception\HttpException;
use Core\Request;
use Core\Router;
use PHPUnit\Framework\TestCase;

class RouterDispatchTest extends TestCase
{
    private function makeRouter(string $method = 'GET', string $uri = '/'): Router
    {
        $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri];
        $_GET    = [];
        $_POST   = [];
        return new Router(new Request());
    }

    public function testDispatchAborts404WhenNoRoutesRegistered(): void
    {
        $router = $this->makeRouter('GET', '/fr/unknown');

        ob_start();
        try {
            $router->dispatch();
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertSame(404, $e->status);
        }
    }

    public function testDispatchAborts404WhenMethodMismatch(): void
    {
        $router = $this->makeRouter('POST', '/fr/vins');
        $router->get('/fr/vins', 'WineController@index');

        ob_start();
        try {
            $router->dispatch();
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertSame(404, $e->status);
        }
    }

    public function testDispatchAborts404WhenPathDoesNotMatch(): void
    {
        $router = $this->makeRouter('GET', '/fr/inexistant');
        $router->get('/fr/vins', 'WineController@index');

        ob_start();
        try {
            $router->dispatch();
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertSame(404, $e->status);
        }
    }

    public function testDispatchAborts500WhenControllerClassMissing(): void
    {
        $router = $this->makeRouter('GET', '/fr/vins');
        $router->get('/fr/vins', 'NonExistentController@index');

        ob_start();
        try {
            $router->dispatch();
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertSame(500, $e->status);
        }
    }
}
