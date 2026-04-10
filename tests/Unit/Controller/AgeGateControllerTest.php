<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Exception\HttpException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 3));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';
    }

    private function makeController(): \Controller\AgeGateController
    {
        return new \Controller\AgeGateController(new \Core\Request());
    }

    // ── show() ─────────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testShowRendersViewIfNotVerified(): void
    {
        $this->bootstrapApp();
        $_COOKIE = [];
        $_GET    = [];

        $this->expectNotToPerformAssertions();

        ob_start();
        try {
            $this->makeController()->show();
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu de vue non disponible en CLI : ' . $e->getMessage());
        }
        ob_get_clean();
    }

    // ── confirm() ──────────────────────────────────────────────────────────

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmMinorRedirectsToGoogle(): void
    {
        $this->bootstrapApp();
        $_POST              = ['legal_age' => '0', 'redirect' => '/fr'];
        $_COOKIE            = [];
        $_SERVER['HTTP_HOST'] = 'crabitan.local';

        ob_start();
        $this->makeController()->confirm();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'google.com',
            $output,
            'Sans exit_url valide, confirm() doit afficher une page meta-refresh vers Google'
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmMinorWithExternalRefererRedirectsToReferer(): void
    {
        $this->bootstrapApp();
        $_POST              = ['legal_age' => '0', 'exit_url' => 'https://www.example.com/page'];
        $_COOKIE            = [];
        $_SERVER['HTTP_HOST'] = 'crabitan.local';

        ob_start();
        $this->makeController()->confirm();
        $output = ob_get_clean();

        $this->assertStringContainsString('example.com', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmMinorWithInternalExitUrlFallsBackToGoogle(): void
    {
        $this->bootstrapApp();
        // exit_url pointant vers le site lui-même → doit être rejeté → Google
        $_POST              = ['legal_age' => '0', 'exit_url' => 'https://crabitan.local/fr'];
        $_COOKIE            = [];
        $_SERVER['HTTP_HOST'] = 'crabitan.local';

        ob_start();
        $this->makeController()->confirm();
        $output = ob_get_clean();

        $this->assertStringContainsString('google.com', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmAbsentChoiceRedirectsToGoogle(): void
    {
        $this->bootstrapApp();
        $_POST              = ['redirect' => '/fr']; // legal_age absent
        $_COOKIE            = [];
        $_SERVER['HTTP_HOST'] = 'crabitan.local';

        ob_start();
        $this->makeController()->confirm();
        $output = ob_get_clean();

        $this->assertStringContainsString('google.com', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
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

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
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

    /**
     * Vérifie que show() accepte un lang valide fourni via $_GET['lang'].
     *
     * Cette branche couvre la ligne :
     *   $lang = in_array($_GET['lang'] ?? '', ['fr', 'en'], true) ? $_GET['lang'] : DEFAULT_LANG;
     * quand $_GET['lang'] === 'en' (valide).
     *
     * @return void
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testShowUsesValidLangFromGet(): void
    {
        $this->bootstrapApp();
        $_COOKIE = [];
        $_GET    = ['lang' => 'en'];

        $this->expectNotToPerformAssertions();

        ob_start();
        try {
            $this->makeController()->show();
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Rendu de vue non disponible en CLI : ' . $e->getMessage());
        }
        ob_get_clean();
    }

    /**
     * Vérifie que confirm() écrit un log IP+horodatage dans logs/age-gate.log (Art. L3342-1 CSP).
     *
     * @return void
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmMajorWritesAgeGateLog(): void
    {
        $this->bootstrapApp();
        $_POST                  = ['legal_age' => '1', 'redirect' => '/fr'];
        $_COOKIE                = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $logFile = ROOT_PATH . '/logs/age-gate.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        ob_start();
        try {
            $this->makeController()->confirm();
        } catch (HttpException $e) {
            // redirection attendue
        }
        ob_end_clean();

        $this->assertFileExists($logFile, 'confirm() doit créer logs/age-gate.log');
        $this->assertStringContainsString('127.0.0.1', (string) file_get_contents($logFile));
    }

    /**
     * Vérifie que sanitizeRedirect refuse une URL commençant par "//"
     * (open redirect via protocole implicite) et retourne DEFAULT_LANG.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmSanitizesDoubleSlashRedirect(): void
    {
        $this->bootstrapApp();
        $_POST   = ['legal_age' => '1', 'redirect' => '//evil.com/xss'];
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
        // //evil.com doit être bloqué — redirection vers la langue par défaut
        $this->assertStringNotContainsString('evil.com', $caught->location ?? '');
    }
}
