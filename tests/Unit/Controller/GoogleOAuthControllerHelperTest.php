<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\GoogleOAuthController;
use Core\Database;
use Core\Exception\GoogleOAuthException;
use Core\Exception\HttpException;
use Core\Request;
use PHPUnit\Framework\TestCase;
use Service\GoogleOAuthService;

/**
 * Tests unitaires GoogleOAuthController — helpers privés et callback RuntimeException.
 */
class GoogleOAuthControllerHelperTest extends TestCase
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
        $_SERVER['REQUEST_URI']       = '/fr/auth/google';

        unset($_ENV['GOOGLE_FR_FALBACK'], $_ENV['GOOGLE_EN_FALBACK']);
    }

    protected function tearDown(): void
    {
        $this->dbInstanceProp->setValue(null, null);
        $_SESSION = [];
        $_COOKIE  = [];
        $_GET     = [];
        unset($_ENV['GOOGLE_FR_FALBACK'], $_ENV['GOOGLE_EN_FALBACK']);
    }

    private function makeController(): GoogleOAuthController
    {
        return new GoogleOAuthController(new Request());
    }

    private function callPrivate(GoogleOAuthController $ctrl, string $method, mixed ...$args): mixed
    {
        $m = new \ReflectionMethod(GoogleOAuthController::class, $method);
        $m->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        return $m->invoke($ctrl, ...$args);
    }

    // ----------------------------------------------------------------
    // buildRedirectUri()
    // ----------------------------------------------------------------

    public function testBuildRedirectUriFrUsesAppUrlWhenNoFallback(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'buildRedirectUri', 'fr');

        $this->assertStringContainsString('http://localhost', (string) $result);
        $this->assertStringContainsString('/fr/auth/google/callback', (string) $result);
    }

    public function testBuildRedirectUriFrUsesFallbackEnvVar(): void
    {
        $_ENV['GOOGLE_FR_FALBACK'] = 'http://localhost/fr/auth/google/callback';
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'buildRedirectUri', 'fr');

        $this->assertSame('http://localhost/fr/auth/google/callback', $result);
    }

    public function testBuildRedirectUriEnUsesAppUrlWhenNoFallback(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'buildRedirectUri', 'en');

        $this->assertStringContainsString('/en/auth/google/callback', (string) $result);
    }

    public function testBuildRedirectUriEnUsesFallbackEnvVar(): void
    {
        $_ENV['GOOGLE_EN_FALBACK'] = 'http://localhost/en/auth/google/callback';
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'buildRedirectUri', 'en');

        $this->assertSame('http://localhost/en/auth/google/callback', $result);
    }

    // ----------------------------------------------------------------
    // deriveDeviceName()
    // ----------------------------------------------------------------

    public function testDeriveDeviceNameEdge(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Edg/120');
        $this->assertStringStartsWith('Edge', (string) $result);
    }

    public function testDeriveDeviceNameChrome(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36');
        $this->assertStringStartsWith('Chrome', (string) $result);
    }

    public function testDeriveDeviceNameFirefox(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'Mozilla/5.0 (Windows NT 10.0; rv:120) Gecko/20100101 Firefox/120');
        $this->assertStringStartsWith('Firefox', (string) $result);
    }

    public function testDeriveDeviceNameSafari(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'Mozilla/5.0 (Macintosh) AppleWebKit/605 Version/17 Safari/605');
        $this->assertStringStartsWith('Safari', (string) $result);
    }

    public function testDeriveDeviceNameUnknownBrowser(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'curl/7.85.0');
        $this->assertStringStartsWith('Browser', (string) $result);
    }

    public function testDeriveDeviceNameWindows(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'Mozilla/5.0 (Windows NT 10.0; rv:120) Gecko Firefox/120');
        $this->assertStringEndsWith('Windows', (string) $result);
    }

    public function testDeriveDeviceNameMacOs(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) Safari/605');
        $this->assertStringEndsWith('macOS', (string) $result);
    }

    public function testDeriveDeviceNameAndroid(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'Mozilla/5.0 (Linux; Android 14; Pixel) Chrome/120');
        $this->assertStringEndsWith('Android', (string) $result);
    }

    public function testDeriveDeviceNameIos(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604');
        $this->assertStringEndsWith('iOS', (string) $result);
    }

    public function testDeriveDeviceNameLinux(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'Mozilla/5.0 (X11; Linux x86_64; rv:120) Gecko Firefox/120');
        $this->assertStringEndsWith('Linux', (string) $result);
    }

    public function testDeriveDeviceNameUnknownOs(): void
    {
        $ctrl   = $this->makeController();
        $result = $this->callPrivate($ctrl, 'deriveDeviceName', 'curl/7.85.0');
        $this->assertStringEndsWith('Unknown', (string) $result);
    }

    // ----------------------------------------------------------------
    // callback() — GoogleOAuthException depuis le service
    // ----------------------------------------------------------------

    public function testCallbackSetsFlashWhenServiceThrowsException(): void
    {
        $_GET     = ['code' => 'valid-code', 'state' => 'valid-state'];
        $_SESSION = ['oauth_google_state' => 'valid-state'];

        $ctrl = $this->makeController();

        // Mock du service qui lève GoogleOAuthException lors de l'échange de code
        $oauthMock = $this->createStub(GoogleOAuthService::class);
        $oauthMock->method('exchangeCode')->willThrowException(new GoogleOAuthException('failed'));

        $prop = new \ReflectionProperty(GoogleOAuthController::class, 'oauth');
        $prop->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        $prop->setValue($ctrl, $oauthMock);

        try {
            $ctrl->callback(['lang' => 'fr']);
        } catch (HttpException) {
        }

        $this->assertArrayHasKey('modal_error', $_SESSION['flash'] ?? []);
    }
}
