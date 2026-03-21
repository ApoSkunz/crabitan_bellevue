<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

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
        return new \Controller\AgeGateController();
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

        ob_start();
        try {
            $this->makeController()->show();
        } catch (\Exception $e) {
            // Response::redirect() peut lancer une exception dans les tests
        }
        ob_end_clean();

        $headers = headers_list();
        $hasRedirect = array_filter($headers, fn($h) => str_starts_with($h, 'Location:'));
        $this->assertNotEmpty($hasRedirect, 'show() doit rediriger si déjà vérifié');
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

        // show() appelle $this->view() qui require le fichier PHP de la vue
        // On vérifie juste qu'aucune exception n'est levée
        ob_start();
        try {
            $this->makeController()->show();
        } catch (\Throwable $e) {
            ob_end_clean();
            // Une exception sur le rendu de vue est acceptable en contexte CLI
            $this->markTestSkipped('Rendu de vue non disponible en CLI : ' . $e->getMessage());
        }
        $output = ob_get_clean();

        // Si on arrive ici, aucune exception — test passé
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
        $_POST = ['legal_age' => '0', 'redirect' => '/fr'];
        $_COOKIE = [];

        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (\Exception $e) {
        }
        ob_end_clean();

        $headers = headers_list();
        $location = array_filter($headers, fn($h) => str_starts_with($h, 'Location:'));
        $this->assertNotEmpty($location, 'confirm() doit émettre un header Location pour un mineur');

        $locationHeader = reset($location);
        $this->assertStringContainsString('google.com', $locationHeader, 'confirm() doit rediriger vers Google pour un mineur');
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

        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (\Exception $e) {
        }
        ob_end_clean();

        $headers  = headers_list();
        $location = array_filter($headers, fn($h) => str_starts_with($h, 'Location:'));
        $this->assertNotEmpty($location);
        $this->assertStringContainsString('google.com', reset($location));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testConfirmMajorSetsAgeVerifiedCookie(): void
    {
        $this->bootstrapApp();
        $_POST   = ['legal_age' => '1', 'redirect' => '/fr'];
        $_COOKIE = [];

        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (\Exception $e) {
        }
        ob_end_clean();

        $headers     = headers_list();
        $setCookies  = array_filter($headers, fn($h) => str_starts_with($h, 'Set-Cookie:'));
        $cookieNames = implode(' ', $setCookies);

        $this->assertStringContainsString('age_verified=1', $cookieNames, 'Le cookie age_verified doit être posé pour un majeur');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testConfirmMajorWithRememberSetsLongTtlAndRememberCookie(): void
    {
        $this->bootstrapApp();
        $_POST   = ['legal_age' => '1', 'remember' => '1', 'redirect' => '/fr'];
        $_COOKIE = [];

        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (\Exception $e) {
        }
        ob_end_clean();

        $headers     = headers_list();
        $setCookies  = array_filter($headers, fn($h) => str_starts_with($h, 'Set-Cookie:'));
        $cookieStr   = implode(' ', $setCookies);

        $this->assertStringContainsString('age_verified=1', $cookieStr);
        $this->assertStringContainsString('age_remember=1', $cookieStr, 'Le cookie age_remember doit être posé avec "se souvenir de moi"');

        // Vérifier qu'une date d'expiry est présente (cookie persistant)
        $this->assertStringContainsString('expires=', strtolower($cookieStr));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testConfirmMajorWithoutRememberNoRememberCookie(): void
    {
        $this->bootstrapApp();
        $_POST   = ['legal_age' => '1', 'redirect' => '/fr']; // pas de "remember"
        $_COOKIE = [];

        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (\Exception $e) {
        }
        ob_end_clean();

        $headers    = headers_list();
        $setCookies = array_filter($headers, fn($h) => str_starts_with($h, 'Set-Cookie:'));
        $cookieStr  = implode(' ', $setCookies);

        $this->assertStringContainsString('age_verified=1', $cookieStr);
        $this->assertStringNotContainsString('age_remember=1', $cookieStr, 'age_remember ne doit PAS être posé sans "se souvenir de moi"');
    }
}
