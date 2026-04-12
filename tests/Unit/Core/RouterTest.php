<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Container;
use Core\Request;
use Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour Router.
 *
 * Vérifie l'enregistrement des routes et l'instanciation
 * avec et sans Container PSR-11.
 */
class RouterTest extends TestCase
{
    /**
     * Crée un Router sans container (comportement legacy).
     *
     * @param string $method Méthode HTTP
     * @param string $uri    URI de la requête
     * @return Router
     */
    private function makeRouter(string $method = 'GET', string $uri = '/'): Router
    {
        $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri];
        $_GET    = [];
        $_POST   = [];

        return new Router(new Request());
    }

    /**
     * Crée un Router avec un Container PSR-11 injecté.
     *
     * @param string         $method    Méthode HTTP
     * @param string         $uri       URI de la requête
     * @param Container|null $container Container DI
     * @return Router
     */
    private function makeRouterWithContainer(
        string $method = 'GET',
        string $uri = '/',
        ?Container $container = null
    ): Router {
        $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri];
        $_GET    = [];
        $_POST   = [];

        return new Router(new Request(), $container ?? new Container());
    }

    public function testStaticRoutePatternMatches(): void
    {
        $router = $this->makeRouter('GET', '/fr/vins');
        $router->get('/fr/vins', 'WineController@index');

        // Vérifie que la route est enregistrée (via réflexion)
        $reflection = new \ReflectionClass($router);
        $prop = $reflection->getProperty('routes');
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
        $routes = $prop->getValue($router);

        $this->assertCount(3, $routes);
    }

    public function testPatternDoesNotMatchPartialPath(): void
    {
        $router = $this->makeRouter();
        $router->get('/fr/vins', 'WineController@index');

        $reflection = new \ReflectionClass($router);
        $prop = $reflection->getProperty('routes');
        $routes = $prop->getValue($router);

        $pattern = $routes[0]['pattern'];

        $this->assertMatchesRegularExpression($pattern, '/fr/vins');
        $this->assertDoesNotMatchRegularExpression($pattern, '/fr/vins/extra');
    }

    // ── Tests DI Container ──────────────────────────────────────────────────

    /**
     * Le Router s'instancie avec un Container PSR-11.
     */
    public function testConstructorAcceptsContainer(): void
    {
        $container = new Container();
        $router    = $this->makeRouterWithContainer('GET', '/', $container);

        $this->assertInstanceOf(Router::class, $router);
    }

    /**
     * Le Router s'instancie avec null comme container (équivalent sans container).
     */
    public function testConstructorAcceptsNullContainer(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
        $_GET    = [];
        $_POST   = [];

        $router = new Router(new Request(), null);

        $this->assertInstanceOf(Router::class, $router);
    }

    /**
     * Le Router avec container enregistre les routes correctement.
     */
    public function testRouteRegistrationWithContainer(): void
    {
        $router = $this->makeRouterWithContainer();
        $router->get('/fr/vins', 'WineController@index');
        $router->post('/fr/panier', 'CartController@add');

        $reflection = new \ReflectionClass($router);
        $prop       = $reflection->getProperty('routes');
        $routes     = $prop->getValue($router);

        $this->assertCount(2, $routes);
    }
}
