<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\NewsletterController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de NewsletterController.
 *
 * La Database est mockée — aucune connexion BDD réelle requise.
 */
class NewsletterControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('fetchAll')->willReturn([]);

        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/newsletter/confirmation';
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
        $_GET     = [];
        $_POST    = [];
    }

    /**
     * confirmSubscription avec token vide redirige (HttpException via Response::redirect).
     */
    public function testConfirmSubscriptionEmptyTokenRedirects(): void
    {
        $_GET['token'] = '';

        $controller = new NewsletterController(new Request());

        $this->expectException(HttpException::class);
        $controller->confirmSubscription(['lang' => 'fr']);
    }

    /**
     * subscribe avec email invalide redirige avec flash error.
     */
    public function testSubscribeInvalidEmailRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email']            = 'pas_un_email';
        $_POST['csrf_token']       = 'fake';

        $controller = new NewsletterController(new Request());

        $this->expectException(HttpException::class);
        $controller->subscribe(['lang' => 'fr']);
    }

    /**
     * subscribe avec CSRF invalide redirige.
     */
    public function testSubscribeInvalidCsrfRedirects(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email']            = 'test@example.com';
        $_POST['csrf_token']       = 'mauvais_token';
        $_SESSION['csrf']          = 'bon_token';

        $controller = new NewsletterController(new Request());

        $this->expectException(HttpException::class);
        $controller->subscribe(['lang' => 'fr']);
    }
}
