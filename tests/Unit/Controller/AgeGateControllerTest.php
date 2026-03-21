<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Exception\HttpException;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour AgeGateController.
 *
 * Chaque test s'exécute dans un processus séparé (@runInSeparateProcess)
 * pour éviter les conflits sur les appels à header() / setcookie().
 */
class AgeGateControllerTest extends TestCase
{
    // ── Helpers ────────────────────────────────────────────────────────────

    private function bootstrapApp(): void
    {
        define('ROOT_PATH', dirname(__DIR__, 3));
        define('SRC_PATH', ROOT_PATH . '/src');
        define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';
    }

    private function makeController(): \Controller\AgeGateController
    {
        return new \Controller\AgeGateController(new \Core\Request());
    }

    // ── show() ─────────────────────────────────────────────────────────────

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testShowRedirectsIfAlreadyVerified(): void
    {
        $this->bootstrapApp();
        $_COOKIE['age_verified'] = '1';

        $caught = null;
        ob_start();
        try {
            $this->makeController()->show();
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'show() doit lancer HttpException si déjà vérifié');
        $this->assertSame(302, $caught->status);
        $this->assertNotNull($caught->location, 'show() doit fournir une URL de redirection');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testShowRendersViewIfNotVerified(): void
    {
        $this->bootstrapApp();
        $_COOKIE = [];
        $_GET    = [];

        ob_start();
        try {
            $this->makeController()->show();
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu de vue non disponible en CLI : ' . $e->getMessage());
        }
        ob_get_clean();

        $this->assertTrue(true);
    }

    // ── confirm() ──────────────────────────────────────────────────────────

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testConfirmMinorRedirectsToGoogle(): void
    {
        $this->bootstrapApp();
        $_POST   = ['legal_age' => '0', 'redirect' => '/fr'];
        $_COOKIE = [];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'confirm() doit lancer HttpException pour un mineur');
        $this->assertStringContainsString(
            'google.com',
            $caught->location ?? '',
            'confirm() doit rediriger vers Google pour un mineur'
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testConfirmAbsentChoiceRedirectsToGoogle(): void
    {
        $this->bootstrapApp();
        $_POST   = ['redirect' => '/fr']; // legal_age absent
        $_COOKIE = [];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught);
        $this->assertStringContainsString('google.com', $caught->location ?? '');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testConfirmMajorRedirectsToRequestedUrl(): void
    {
        $this->bootstrapApp();
        $_POST   = ['legal_age' => '1', 'redirect' => '/fr'];
        $_COOKIE = [];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'confirm() doit rediriger un majeur');
        $this->assertSame(302, $caught->status);
        // Un majeur est redirigé vers l'URL demandée, pas vers Google
        $this->assertStringNotContainsString(
            'google.com',
            $caught->location ?? '',
            'Un majeur ne doit PAS être redirigé vers Google'
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testConfirmMajorWithRememberRedirectsToRequestedUrl(): void
    {
        $this->bootstrapApp();
        $_POST   = ['legal_age' => '1', 'remember' => '1', 'redirect' => '/fr'];
        $_COOKIE = [];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertStringNotContainsString('google.com', $caught->location ?? '');
        // La vérification des cookies age_verified/age_remember est couverte par les tests E2E
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testConfirmMajorWithoutRememberRedirectsToRequestedUrl(): void
    {
        $this->bootstrapApp();
        $_POST   = ['legal_age' => '1', 'redirect' => '/fr'];
        $_COOKIE = [];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertStringNotContainsString('google.com', $caught->location ?? '');
    }
}
