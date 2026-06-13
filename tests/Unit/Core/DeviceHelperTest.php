<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\DeviceHelper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests unitaires pour le trait DeviceHelper.
 *
 * Couvre resolveDeviceToken() : token valide, invalide, absent.
 */
class DeviceHelperTest extends TestCase
{
    /** Classe concrète minimale utilisant le trait pour les tests. */
    private object $subject;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        $this->subject = new class {
            use DeviceHelper;

            public function resolve(): string
            {
                return $this->resolveDeviceToken();
            }
        };

        $this->method = new ReflectionMethod($this->subject, 'resolveDeviceToken');
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
    }

    // ----------------------------------------------------------------
    // Cookie valide — retourné directement
    // ----------------------------------------------------------------

    /**
     * Un token hexadécimal de 64 caractères est accepté.
     */
    public function testValidTokenIsReturnedFromCookie(): void
    {
        $valid = str_repeat('a1', 32); // 64 chars hexadécimaux
        $_COOKIE['device_token'] = $valid;

        $result = $this->method->invoke($this->subject);

        $this->assertSame($valid, $result);
    }

    // ----------------------------------------------------------------
    // Cookie avec format invalide — nouveau token généré
    // ----------------------------------------------------------------

    /**
     * Un token trop court doit être ignoré et un nouveau token généré.
     */
    public function testShortTokenIsRejectedAndNewTokenGenerated(): void
    {
        $_COOKIE['device_token'] = 'tooshort';

        $result = $this->method->invoke($this->subject);

        $this->assertNotSame('tooshort', $result);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result);
    }

    /**
     * Un token contenant des caractères non hexadécimaux est rejeté.
     */
    public function testNonHexTokenIsRejectedAndNewTokenGenerated(): void
    {
        $_COOKIE['device_token'] = str_repeat('zz', 32); // 64 chars mais pas hex

        $result = $this->method->invoke($this->subject);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result);
    }

    /**
     * Un token exactement 64 chars mais avec majuscules est rejeté (hex strict lowercase).
     */
    public function testUppercaseHexTokenIsRejected(): void
    {
        $_COOKIE['device_token'] = str_repeat('A1', 32); // majuscules

        $result = $this->method->invoke($this->subject);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result);
    }

    // ----------------------------------------------------------------
    // Cookie absent — nouveau token généré
    // ----------------------------------------------------------------

    /**
     * Sans cookie, un token hexadécimal de 64 caractères est généré.
     */
    public function testMissingCookieGeneratesNewToken(): void
    {
        $_COOKIE = [];

        $result = $this->method->invoke($this->subject);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result);
    }

    /**
     * Deux appels sans cookie génèrent des tokens différents.
     */
    public function testMissingCookieGeneratesUniqueTokens(): void
    {
        $_COOKIE = [];

        $first  = $this->method->invoke($this->subject);
        // Réinitialiser pour simuler un second appel sans cookie
        $_COOKIE = [];
        $second = $this->method->invoke($this->subject);

        $this->assertNotSame($first, $second);
    }

    // ----------------------------------------------------------------
    // deriveDeviceName() — couverture branches browser/OS
    // ----------------------------------------------------------------

    public function testDeriveDeviceNameEdge(): void
    {
        $m      = new ReflectionMethod($this->subject, 'deriveDeviceName');
        $result = $m->invoke($this->subject, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Edg/120.0');
        $this->assertStringContainsString('Edge', $result);
        $this->assertStringContainsString('Windows', $result);
    }

    public function testDeriveDeviceNameChrome(): void
    {
        $m      = new ReflectionMethod($this->subject, 'deriveDeviceName');
        $result = $m->invoke($this->subject, 'Mozilla/5.0 Chrome/120 Safari/537.36');
        $this->assertStringContainsString('Chrome', $result);
    }

    public function testDeriveDeviceNameFirefoxLinux(): void
    {
        $m      = new ReflectionMethod($this->subject, 'deriveDeviceName');
        $result = $m->invoke($this->subject, 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0');
        $this->assertStringContainsString('Firefox', $result);
        $this->assertStringContainsString('Linux', $result);
    }

    public function testDeriveDeviceNameSafariIos(): void
    {
        $m      = new ReflectionMethod($this->subject, 'deriveDeviceName');
        $result = $m->invoke($this->subject, 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604.1');
        $this->assertStringContainsString('Safari', $result);
        $this->assertStringContainsString('iOS', $result);
    }

    public function testDeriveDeviceNameUnknown(): void
    {
        $m      = new ReflectionMethod($this->subject, 'deriveDeviceName');
        $result = $m->invoke($this->subject, 'curl/7.68.0');
        $this->assertStringContainsString('Browser', $result);
        $this->assertStringContainsString('Unknown', $result);
    }
}
