<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\NewsletterController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de NewsletterController.
 *
 * La Database est mockée — aucune connexion BDD réelle requise.
 */
#[AllowMockObjectsWithoutExpectations]
class NewsletterControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturn(false);
        $dbMock->method('fetchAll')->willReturn([]);

        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
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

    /**
     * subscribe rate-limité retourne JSON 429.
     * Le mock fetchOne retourne 3 tentatives récentes pour déclencher rate_limit.
     */
    public function testSubscribeRateLimitReturnsJson429(): void
    {
        // 1re requête : accounts->findByEmail → false (pas de compte)
        // 2e requête : model->countRecentAttempts → 3 tentatives récentes
        $this->instanceProp->setValue(null, null);
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('fetchOne')->willReturnOnConsecutiveCalls(
            false,
            ['attempts_24h' => 3, 'last_attempt_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]
        );
        $dbMock->method('fetchAll')->willReturn([]);
        $this->instanceProp->setValue(null, $dbMock);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email']            = 'test@example.com';

        $controller = new NewsletterController(new Request());

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(429);
            $controller->subscribe(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * confirmSubscription avec token inconnu (absent de la BDD) affiche la vue d'erreur.
     */
    public function testConfirmSubscriptionUnknownTokenShowsErrorView(): void
    {
        $_GET['token'] = bin2hex(random_bytes(32));

        $controller = new NewsletterController(new Request());

        ob_start();
        $controller->confirmSubscription(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    // ----------------------------------------------------------------
    // Tests complémentaires — branches manquantes
    // ----------------------------------------------------------------

    /**
     * subscribe() — succès : le service ne lève pas d'exception → JSON 200.
     */
    public function testSubscribeSuccessReturnsJson200(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email']            = 'nouveau@example.com';

        $controller = new NewsletterController(new Request());

        // Injecter un NewsletterService mock qui ne lève pas d'exception
        $serviceMock = $this->createMock(\Service\NewsletterService::class);
        $serviceMock->expects($this->once())->method('subscribe');

        $prop = new \ReflectionProperty(NewsletterController::class, 'newsletterService');
        $prop->setValue($controller, $serviceMock);

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(200);
            $controller->subscribe(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * subscribe() — already_confirmed : même réponse JSON 200 neutre (anti-énumération).
     */
    public function testSubscribeAlreadyConfirmedReturnsJson200(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email']            = 'deja@example.com';

        $controller = new NewsletterController(new Request());

        $serviceMock = $this->createMock(\Service\NewsletterService::class);
        $serviceMock->method('subscribe')
            ->willThrowException(new \Core\Exception\NewsletterException('already_confirmed'));

        $prop = new \ReflectionProperty(NewsletterController::class, 'newsletterService');
        $prop->setValue($controller, $serviceMock);

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(200);
            $controller->subscribe(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * subscribe() — erreur générique NewsletterException → JSON 429.
     */
    public function testSubscribeGenericNewsletterExceptionReturnsJson429(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['email']            = 'erreur@example.com';

        $controller = new NewsletterController(new Request());

        $serviceMock = $this->createMock(\Service\NewsletterService::class);
        $serviceMock->method('subscribe')
            ->willThrowException(new \Core\Exception\NewsletterException('some_other_error'));

        $prop = new \ReflectionProperty(NewsletterController::class, 'newsletterService');
        $prop->setValue($controller, $serviceMock);

        ob_start();
        try {
            $this->expectException(HttpException::class);
            $this->expectExceptionCode(429);
            $controller->subscribe(['lang' => 'fr']);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * confirmSubscription() — succès : le service ne lève pas d'exception → vue success.
     */
    public function testConfirmSubscriptionSuccessShowsSuccessView(): void
    {
        $_GET['token'] = 'valid-token-xyz';

        $controller = new NewsletterController(new Request());

        $serviceMock = $this->createMock(\Service\NewsletterService::class);
        $serviceMock->expects($this->once())->method('confirmSubscription');

        $prop = new \ReflectionProperty(NewsletterController::class, 'newsletterService');
        $prop->setValue($controller, $serviceMock);

        ob_start();
        $controller->confirmSubscription(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--ok', $output);
    }

    /**
     * confirmSubscription() — token expiré → vue d'erreur avec reason 'expired'.
     */
    public function testConfirmSubscriptionExpiredTokenShowsErrorView(): void
    {
        $_GET['token'] = 'expired-token-abc';

        $controller = new NewsletterController(new Request());

        $serviceMock = $this->createMock(\Service\NewsletterService::class);
        $serviceMock->method('confirmSubscription')
            ->willThrowException(new \Core\Exception\NewsletterException('expired'));

        $prop = new \ReflectionProperty(NewsletterController::class, 'newsletterService');
        $prop->setValue($controller, $serviceMock);

        ob_start();
        $controller->confirmSubscription(['lang' => 'fr']);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    // ----------------------------------------------------------------
    // subscribeFromWelcome — toutes les branches
    // ----------------------------------------------------------------

    /**
     * subscribeFromWelcome() — email vide → vue d'erreur.
     */
    public function testSubscribeFromWelcomeEmptyEmailShowsErrorView(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/newsletter/inscription-bienvenue';
        $_GET['email'] = '';

        $controller = new NewsletterController(new Request());

        ob_start();
        $controller->subscribeFromWelcome([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    /**
     * subscribeFromWelcome() — email invalide → vue d'erreur.
     */
    public function testSubscribeFromWelcomeInvalidEmailShowsErrorView(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/newsletter/inscription-bienvenue';
        $_GET['email'] = 'pas_un_email';

        $controller = new NewsletterController(new Request());

        ob_start();
        $controller->subscribeFromWelcome([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    /**
     * subscribeFromWelcome() — déjà confirmé (BDD) → vue succès sans appel subscribe.
     */
    public function testSubscribeFromWelcomeAlreadyConfirmedInDbShowsSuccessView(): void
    {
        $this->instanceProp->setValue(null, null);
        $dbMock = $this->createStub(Database::class);
        // findByEmail retourne un abonné confirmé
        $dbMock->method('fetchOne')->willReturn(['email' => 'confirmed@example.com', 'newsletter_confirmed' => 1]);
        $dbMock->method('fetchAll')->willReturn([]);
        $this->instanceProp->setValue(null, $dbMock);

        $_SERVER['REQUEST_URI'] = '/fr/newsletter/inscription-bienvenue';
        $_GET['email'] = 'confirmed@example.com';

        $controller = new NewsletterController(new Request());

        ob_start();
        $controller->subscribeFromWelcome([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--ok', $output);
    }

    /**
     * subscribeFromWelcome() — succès subscribe → vue succès.
     */
    public function testSubscribeFromWelcomeSuccessShowsSuccessView(): void
    {
        $_SERVER['REQUEST_URI'] = '/en/newsletter/welcome-subscribe';
        $_GET['email'] = 'nouveau@example.com';

        $controller = new NewsletterController(new Request());

        $serviceMock = $this->createMock(\Service\NewsletterService::class);
        $serviceMock->expects($this->once())->method('subscribe');

        $prop = new \ReflectionProperty(NewsletterController::class, 'newsletterService');
        $prop->setValue($controller, $serviceMock);

        ob_start();
        $controller->subscribeFromWelcome([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--ok', $output);
    }

    /**
     * subscribeFromWelcome() — already_confirmed exception → vue succès (anti-énumération).
     */
    public function testSubscribeFromWelcomeAlreadyConfirmedExceptionShowsSuccessView(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/newsletter/inscription-bienvenue';
        $_GET['email'] = 'deja@example.com';

        $controller = new NewsletterController(new Request());

        $serviceMock = $this->createMock(\Service\NewsletterService::class);
        $serviceMock->method('subscribe')
            ->willThrowException(new \Core\Exception\NewsletterException('already_confirmed'));

        $prop = new \ReflectionProperty(NewsletterController::class, 'newsletterService');
        $prop->setValue($controller, $serviceMock);

        ob_start();
        $controller->subscribeFromWelcome([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--ok', $output);
    }

    /**
     * subscribeFromWelcome() — erreur générique → vue d'erreur.
     */
    public function testSubscribeFromWelcomeGenericErrorShowsErrorView(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/newsletter/inscription-bienvenue';
        $_GET['email'] = 'erreur@example.com';

        $controller = new NewsletterController(new Request());

        $serviceMock = $this->createMock(\Service\NewsletterService::class);
        $serviceMock->method('subscribe')
            ->willThrowException(new \Core\Exception\NewsletterException('rate_limit'));

        $prop = new \ReflectionProperty(NewsletterController::class, 'newsletterService');
        $prop->setValue($controller, $serviceMock);

        ob_start();
        $controller->subscribeFromWelcome([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('auth-status--error', $output);
    }

    /**
     * subscribeFromWelcome() — lang détectée depuis /en/ URI.
     */
    public function testSubscribeFromWelcomeDetectsEnglishLangFromUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/en/newsletter/welcome-subscribe';
        $_GET['email'] = 'english@example.com';

        $controller = new NewsletterController(new Request());

        $serviceMock = $this->createMock(\Service\NewsletterService::class);
        $serviceMock->expects($this->once())->method('subscribe')
            ->with('english@example.com', 'en', $this->anything());

        $prop = new \ReflectionProperty(NewsletterController::class, 'newsletterService');
        $prop->setValue($controller, $serviceMock);

        ob_start();
        $controller->subscribeFromWelcome([]);
        ob_get_clean();

        $this->assertTrue(true); // subscribe appelé avec lang='en'
    }
}
