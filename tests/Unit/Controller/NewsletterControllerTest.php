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
     * confirmSubscription avec token vide affiche la vue d'erreur (aucune exception levée).
     */
    public function testConfirmSubscriptionEmptyTokenShowsErrorView(): void
    {
        $_GET['token'] = '';

        $controller = new NewsletterController(new Request());

        ob_start();
        try {
            $controller->confirmSubscription(['lang' => 'fr']);
            $this->assertTrue(true); // vue affichée sans exception
        } finally {
            ob_end_clean();
        }
    }

    /**
     * subscribe avec email invalide retourne JSON 422 (HttpException via Response::json).
     */
    public function testSubscribeInvalidEmailReturnsJson422(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email']            = 'pas_un_email';

        $controller = new NewsletterController(new Request());

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(422);
            $controller->subscribe(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * subscribe avec email vide retourne JSON 422.
     */
    public function testSubscribeEmptyEmailReturnsJson422(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email']            = '';

        $controller = new NewsletterController(new Request());

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(422);
            $controller->subscribe(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }
}
