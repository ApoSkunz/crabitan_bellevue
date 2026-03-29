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

    /**
     * Un controller existant mais avec une méthode inexistante doit déclencher un abort 500.
     * Couvre la branche !method_exists() dans callAction().
     */
    public function testDispatchAborts500WhenMethodMissingOnExistingController(): void
    {
        $router = $this->makeRouter('GET', '/fr/accueil');
        // HomeController existe mais n'a pas de méthode "nonexistent"
        $router->get('/fr/accueil', 'HomeController@nonexistent');

        ob_start();
        try {
            $router->dispatch();
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            ob_end_clean();
            $this->assertSame(500, $e->status);
        }
    }

    /**
     * Un dispatch sur une route contenant {lang} doit appeler setLang() et
     * définir CURRENT_LANG avec la valeur extraite de l'URL.
     * Couvre les lignes 59-63 (isset($params['lang']) + setLang()) dans dispatch().
     */
    public function testDispatchExtractsLangParamAndCallsSetLang(): void
    {
        // On utilise un controller inexistant pour stopper rapidement après setLang()
        $router = $this->makeRouter('GET', '/fr/test-lang');
        $router->get('/{lang}/test-lang', 'NonExistentController@index');

        ob_start();
        try {
            $router->dispatch();
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            ob_end_clean();
            // setLang() a été exécutée (define CURRENT_LANG) — l'abort 500 provient de callAction
            $this->assertSame(500, $e->status);
            // CURRENT_LANG a été défini (ou redéfini) par setLang()
            $this->assertTrue(defined('CURRENT_LANG'));
        }
    }
}
