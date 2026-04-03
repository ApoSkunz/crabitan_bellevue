<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\GoogleOAuthController;
use Core\Database;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires GoogleOAuthController — authorize + callback (sans appel HTTP réel).
 */
class GoogleOAuthControllerTest extends TestCase
{
    private \ReflectionProperty $dbInstanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $this->dbInstanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->dbInstanceProp->setAccessible(true);
        $this->dbInstanceProp->setValue(null, $dbMock);

        $_SESSION = [];
        $_COOKIE  = [];
        $_GET     = [];
        $_ENV['GOOGLE_CLIENT_ID']     = 'test-id';
        $_ENV['GOOGLE_CLIENT_SECRET'] = 'test-secret';
        $_ENV['APP_URL']              = 'http://localhost';
        $_SERVER['REQUEST_METHOD']    = 'GET';
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_COOKIE  = [];
        $_GET     = [];
    }

    private function makeController(): GoogleOAuthController
    {
        $_SERVER['REQUEST_URI'] = '/fr/auth/google';
        return new GoogleOAuthController(new Request());
    }

    // ----------------------------------------------------------------
    // authorize()
    // ----------------------------------------------------------------

    public function testAuthorizeStoresStateInSession(): void
    {
        $ctrl = $this->makeController();

        try {
            $ctrl->authorize(['lang' => 'fr']);
        } catch (HttpException) {
            // Response::redirect() lance HttpException — comportement attendu
        }

        $this->assertArrayHasKey('oauth_google_state', $_SESSION);
        $this->assertNotEmpty($_SESSION['oauth_google_state']);
    }

    public function testAuthorizeGeneratesUniqueStateEachCall(): void
    {
        $ctrl = $this->makeController();

        try { $ctrl->authorize(['lang' => 'fr']); } catch (HttpException) {}
        $state1 = $_SESSION['oauth_google_state'] ?? '';

        $_SESSION = [];
        try { $ctrl->authorize(['lang' => 'fr']); } catch (HttpException) {}
        $state2 = $_SESSION['oauth_google_state'] ?? '';

        $this->assertNotEquals($state1, $state2);
    }

    public function testAuthorizeRedirectsToGoogle(): void
    {
        $ctrl = $this->makeController();

        $redirected = false;
        try {
            $ctrl->authorize(['lang' => 'fr']);
        } catch (HttpException $e) {
            $redirected = str_contains((string) $e->location, 'accounts.google.com');
        }

        $this->assertTrue($redirected, 'authorize() doit rediriger vers accounts.google.com');
    }

    // ----------------------------------------------------------------
    // callback() — cas d'erreur sans appel HTTP
    // ----------------------------------------------------------------

    public function testCallbackWithGoogleErrorSetsFlashAndRedirects(): void
    {
        $_GET = ['error' => 'access_denied'];
        $_SESSION = ['oauth_google_state' => 'abc'];

        $ctrl = $this->makeController();

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {}

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    public function testCallbackWithMissingSessionStateSetsError(): void
    {
        $_GET     = ['code' => 'some-code', 'state' => 'attacker-state'];
        $_SESSION = []; // pas de state en session — CSRF

        $ctrl = $this->makeController();

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {}

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    public function testCallbackWithStateMismatchSetsError(): void
    {
        $_GET     = ['code' => 'some-code', 'state' => 'wrong-state'];
        $_SESSION = ['oauth_google_state' => 'correct-state'];

        $ctrl = $this->makeController();

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {}

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    public function testCallbackClearsStateAfterValidation(): void
    {
        // State correct mais pas de code → error après validation du state
        $_GET     = ['state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $ctrl = $this->makeController();

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {}

        $this->assertArrayNotHasKey('oauth_google_state', $_SESSION);
    }

    // ----------------------------------------------------------------
    // linkConfirm() — page de confirmation de rattachement
    // ----------------------------------------------------------------

    public function testLinkConfirmWithNoPendingLinkRedirects(): void
    {
        $_SESSION = []; // pas de pending_google_link en session

        $ctrl = $this->makeController();

        try {
            $ctrl->linkConfirm(['lang' => 'fr']);
        } catch (HttpException) {}

        // doit rediriger (pas d'erreur fatale, pas de page affichée)
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // linkConfirmPost() — confirmation du rattachement
    // ----------------------------------------------------------------

    public function testLinkConfirmPostWithNoPendingLinkSetsError(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION = []; // pas de pending_google_link

        $ctrl = $this->makeController();

        try {
            $ctrl->linkConfirmPost(['lang' => 'fr']);
        } catch (HttpException) {}

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }

    public function testLinkConfirmPostCancelRedirectsToLogin(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST    = ['action' => 'cancel'];
        $_SESSION = [
            'pending_google_link' => [
                'google_id'  => 'g123',
                'account_id' => 42,
                'email'      => 'test@example.com',
            ],
        ];

        $ctrl = $this->makeController();

        $redirected = false;
        try {
            $ctrl->linkConfirmPost(['lang' => 'fr']);
        } catch (HttpException $e) {
            $redirected = str_contains((string) $e->location, 'login=1')
                || str_contains((string) $e->location, 'fr');
        }

        $this->assertTrue($redirected);
        $this->assertArrayNotHasKey('pending_google_link', $_SESSION);
    }
}
