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

    // ── confirmLang() ─────────────────────────────────────────────────────────

    /**
     * Vérifie que confirmLang() pose le cookie et redirige vers l'URL demandée.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmLangSetsSessionCookieAndRedirects(): void
    {
        $this->bootstrapApp();
        $_POST   = ['redirect' => '/fr'];
        $_COOKIE = [];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->confirmLang(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught, 'confirmLang() doit rediriger');
        $this->assertSame(302, $caught->status);
        $this->assertStringNotContainsString('google.com', $caught->location ?? '');
    }

    /**
     * Vérifie que confirmLang() rejette une URL de redirection externe (open redirect).
     *
     * @return void
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmLangRejectsOpenRedirect(): void
    {
        $this->bootstrapApp();
        $_POST   = ['redirect' => 'https://evil.com/phishing'];
        $_COOKIE = [];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->confirmLang(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $location = $caught->location ?? '';
        $this->assertStringStartsWith('/', $location, 'La redirection doit être un chemin relatif interne');
        $this->assertStringNotContainsString('evil.com', $location);
    }

    /**
     * Vérifie que confirmLang() rejette une URL commençant par "//" (open redirect).
     *
     * @return void
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConfirmLangSanitizesDoubleSlashRedirect(): void
    {
        $this->bootstrapApp();
        $_POST   = ['redirect' => '//evil.com/xss'];
        $_COOKIE = [];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->confirmLang(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught);
        $this->assertSame(302, $caught->status);
        $this->assertStringNotContainsString('evil.com', $caught->location ?? '');
    }

    /**
     * Vérifie que exitLang() redirige vers google.com.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testExitLangRedirectsToGoogle(): void
    {
        $this->bootstrapApp();
        $_COOKIE = [];

        $caught = null;
        ob_start();
        try {
            $this->makeController()->exitLang(['lang' => 'fr']);
        } catch (HttpException $e) {
            $caught = $e;
        }
        ob_end_clean();

        $this->assertNotNull($caught);
        $this->assertStringContainsString('google.com', $caught->location ?? '');
    }
}
