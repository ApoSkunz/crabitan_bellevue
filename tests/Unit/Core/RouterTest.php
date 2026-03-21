<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Request;
use Core\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private function makeRouter(string $method = 'GET', string $uri = '/'): Router
    {
        $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri];
        $_GET    = [];
        $_POST   = [];

        return new Router(new Request());
    }

    public function testStaticRoutePatternMatches(): void
    {
        $router = $this->makeRouter('GET', '/fr/vins');
        $router->get('/fr/vins', 'WineController@index');

        // Vérifie que la route est enregistrée (via réflexion)
        $reflection = new \ReflectionClass($router);
        $prop = $reflection->getProperty('routes');
        $prop->setAccessible(true);
        $routes = $prop->getValue($router);

        $this->assertCount(1, $routes);
        $this->assertSame('GET', $routes[0]['method']);
        $this->assertSame('/fr/vins', $routes[0]['path']);
        $this->assertSame('WineController@index', $routes[0]['action']);
    }

    public function testDynamicRoutePatternBuilt(): void
    {
        $router = $this->makeRouter();
        $router->get('/{lang}/vins/{slug}', 'WineController@show');

        $reflection = new \ReflectionClass($router);
        $prop = $reflection->getProperty('routes');
        $prop->setAccessible(true);
        $routes = $prop->getValue($router);

        $pattern = $routes[0]['pattern'];

        $this->assertMatchesRegularExpression($pattern, '/fr/vins/bordeaux-2019');
        $this->assertMatchesRegularExpression($pattern, '/en/vins/some-wine');
        $this->assertDoesNotMatchRegularExpression($pattern, '/fr/vins/');
    }

    public function testPostRouteRegistered(): void
    {
        $router = $this->makeRouter('POST', '/fr/connexion');
        $router->post('/fr/connexion', 'AuthController@login');

        $reflection = new \ReflectionClass($router);
        $prop = $reflection->getProperty('routes');
        $prop->setAccessible(true);
        $routes = $prop->getValue($router);

        $this->assertSame('POST', $routes[0]['method']);
    }

    public function testMultipleRoutesRegistered(): void
    {
        $router = $this->makeRouter();
        $router->get('/fr/vins', 'WineController@index');
        $router->get('/fr/vins/{slug}', 'WineController@show');
        $router->post('/fr/panier/ajouter', 'CartController@add');

        $reflection = new \ReflectionClass($router);
        $prop = $reflection->getProperty('routes');
        $prop->setAccessible(true);
        $routes = $prop->getValue($router);

        $this->assertCount(3, $routes);
    }

    public function testPatternDoesNotMatchPartialPath(): void
    {
        $router = $this->makeRouter();
        $router->get('/fr/vins', 'WineController@index');

        $reflection = new \ReflectionClass($router);
        $prop = $reflection->getProperty('routes');
        $prop->setAccessible(true);
        $routes = $prop->getValue($router);

        $pattern = $routes[0]['pattern'];

        $this->assertMatchesRegularExpression($pattern, '/fr/vins');
        $this->assertDoesNotMatchRegularExpression($pattern, '/fr/vins/extra');
    }
}
