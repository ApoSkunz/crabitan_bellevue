<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\AuthController;
use Core\Database;
use Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la méthode privée deriveDeviceName() d'AuthController.
 * Chaque bras des deux match() est couvert pour garantir ≥ 95% de coverage sur la classe.
 */
class AuthControllerDeriveDeviceNameTest extends TestCase
{
    private \ReflectionMethod $method;
    private AuthController $controller;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $dbMock = $this->createStub(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $_COOKIE  = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/fr/';

        $this->controller = new AuthController(new Request());

        $this->method = new \ReflectionMethod(AuthController::class, 'deriveDeviceName');
        $this->method->setAccessible(true);
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
        $_COOKIE  = [];
        $_SESSION = [];
    }

    /**
     * Helper : appelle deriveDeviceName() avec l'UA donné et retourne le résultat.
     *
     * @param string $ua User-Agent string
     * @return string Le nom de l'appareil dérivé
     */
    private function derive(string $ua): string
    {
        return (string) $this->method->invoke($this->controller, $ua);
    }

    // ----------------------------------------------------------------
    // Branches navigateur
    // ----------------------------------------------------------------

    /**
     * Un UA contenant "Edg" est reconnu comme Edge.
     */
    public function testEdgeBrowserDetected(): void
    {
        $ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36 Edg/120';
        $result = $this->derive($ua);
        $this->assertStringStartsWith('Edge', $result);
    }

    /**
     * Un UA contenant "Chrome" (sans "Chromium") est reconnu comme Chrome.
     */
    public function testChromeBrowserDetected(): void
    {
        $ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $result = $this->derive($ua);
        $this->assertStringStartsWith('Chrome', $result);
    }

    /**
     * Un UA contenant "Firefox" est reconnu comme Firefox.
     */
    public function testFirefoxBrowserDetected(): void
    {
        $ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0';
        $result = $this->derive($ua);
        $this->assertStringStartsWith('Firefox', $result);
    }

    /**
     * Un UA contenant "Safari" sans "Chrome" est reconnu comme Safari.
     */
    public function testSafariBrowserDetected(): void
    {
        $ua     = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17 Safari/605.1.15';
        $result = $this->derive($ua);
        $this->assertStringStartsWith('Safari', $result);
    }

    /**
     * Un UA inconnu (aucun navigateur reconnu) retourne "Browser" (bras default).
     */
    public function testUnknownBrowserFallsToDefault(): void
    {
        $result = $this->derive('curl/7.85.0');
        $this->assertStringStartsWith('Browser', $result);
    }

    // ----------------------------------------------------------------
    // Branches OS
    // ----------------------------------------------------------------

    /**
     * Un UA contenant "iPhone" est reconnu comme iOS.
     */
    public function testIphoneOsDetected(): void
    {
        $ua     = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17 Mobile/15E148 Safari/604.1';
        $result = $this->derive($ua);
        $this->assertStringEndsWith('iOS', $result);
    }

    /**
     * Un UA contenant "iPad" est reconnu comme iOS.
     */
    public function testIpadOsDetected(): void
    {
        $ua     = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17 Mobile/15E148 Safari/604.1';
        $result = $this->derive($ua);
        $this->assertStringEndsWith('iOS', $result);
    }

    /**
     * Un UA contenant "Android" est reconnu comme Android.
     */
    public function testAndroidOsDetected(): void
    {
        $ua     = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';
        $result = $this->derive($ua);
        $this->assertStringEndsWith('Android', $result);
    }

    /**
     * Un UA contenant "Windows" est reconnu comme Windows.
     */
    public function testWindowsOsDetected(): void
    {
        $ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36';
        $result = $this->derive($ua);
        $this->assertStringEndsWith('Windows', $result);
    }

    /**
     * Un UA contenant "Macintosh" est reconnu comme macOS.
     */
    public function testMacOsDetected(): void
    {
        $ua     = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Version/17 Safari/605.1.15';
        $result = $this->derive($ua);
        $this->assertStringEndsWith('macOS', $result);
    }

    /**
     * Un UA contenant "Linux" (sans Android) est reconnu comme Linux.
     */
    public function testLinuxOsDetected(): void
    {
        $ua     = 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0';
        $result = $this->derive($ua);
        $this->assertStringEndsWith('Linux', $result);
    }

    /**
     * Un UA sans OS reconnu retourne "Unknown" (bras default).
     */
    public function testUnknownOsFallsToDefault(): void
    {
        $result = $this->derive('curl/7.85.0');
        $this->assertStringEndsWith('Unknown', $result);
    }

    /**
     * Le format retourné est "{Browser} · {OS}".
     */
    public function testReturnFormatIsBrowserDotOs(): void
    {
        $ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0';
        $result = $this->derive($ua);
        $this->assertSame('Firefox · Windows', $result);
    }
}
