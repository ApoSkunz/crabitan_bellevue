<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\AuthController;
use Core\Exception\HttpException;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration ciblant les branches de deriveDeviceName() dans AuthController.
 *
 * Chaque test appelle login() avec un User-Agent distinct afin de couvrir
 * les différentes combinaisons navigateur/OS détectées par la méthode privée.
 */
class AuthControllerDeviceNameTest extends IntegrationTestCase
{
    private const CSRF = 'device-name-csrf-token';

    /** Compteur pour générer des emails uniques sans collision de transaction. */
    private int $emailIndex = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emailIndex = 0;
        $_COOKIE  = [];
        $_SESSION = ['csrf' => self::CSRF];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_POST    = [];
        $_COOKIE  = [];
        $_SESSION = [];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée un compte vérifié avec un email unique par test et retourne l'email.
     *
     * @param string $ua User-Agent à injecter dans $_SERVER
     * @return string Email du compte créé
     */
    private function insertAccountAndSetUa(string $ua): string
    {
        $this->emailIndex++;
        $email = "device-test-{$this->emailIndex}-" . uniqid() . '@example.com';
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', NOW())",
            [$email, password_hash('Password123!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Device', 'User', 'M')",
            [$id]
        );
        $_SERVER['HTTP_USER_AGENT'] = $ua;
        return $email;
    }

    /**
     * Appelle login() avec l'email et le mot de passe donnés, retourne l'exception HTTP.
     *
     * @param string $email Email du compte
     * @return HttpException
     */
    private function doLogin(string $email): HttpException
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/connexion';
        $_GET = [];
        $controller = new AuthController(new Request());

        $_POST = [
            'email'      => $email,
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        try {
            $controller->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            return $e;
        }
        // Unreachable, but satisfies static analysis
        throw new \RuntimeException('Unreachable');
    }

    // ----------------------------------------------------------------
    // deriveDeviceName — branche navigateur Edge
    // ----------------------------------------------------------------

    /**
     * Vérifie que login() réussit avec un UA Microsoft Edge (contient "Edg").
     * Couvre la branche `str_contains($ua, 'Edg')` → 'Edge' dans deriveDeviceName().
     *
     * @return void
     */
    public function testLoginWithEdgeUserAgent(): void
    {
        $email = $this->insertAccountAndSetUa(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36 Edg/120.0.0.0'
        );
        $e = $this->doLogin($email);
        $this->assertSame(302, $e->status);
    }

    // ----------------------------------------------------------------
    // deriveDeviceName — branche navigateur Chrome (sans Chromium)
    // ----------------------------------------------------------------

    /**
     * Vérifie que login() réussit avec un UA Google Chrome standard.
     * Couvre la branche `str_contains($ua, 'Chrome') && !str_contains($ua, 'Chromium')` → 'Chrome'.
     *
     * @return void
     */
    public function testLoginWithChromeUserAgent(): void
    {
        $email = $this->insertAccountAndSetUa(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        );
        $e = $this->doLogin($email);
        $this->assertSame(302, $e->status);
    }

    // ----------------------------------------------------------------
    // deriveDeviceName — branche navigateur Firefox
    // ----------------------------------------------------------------

    /**
     * Vérifie que login() réussit avec un UA Mozilla Firefox.
     * Couvre la branche `str_contains($ua, 'Firefox')` → 'Firefox' dans deriveDeviceName().
     *
     * @return void
     */
    public function testLoginWithFirefoxUserAgent(): void
    {
        $email = $this->insertAccountAndSetUa(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0'
        );
        $e = $this->doLogin($email);
        $this->assertSame(302, $e->status);
    }

    // ----------------------------------------------------------------
    // deriveDeviceName — branche navigateur Safari (sans Chrome)
    // ----------------------------------------------------------------

    /**
     * Vérifie que login() réussit avec un UA Safari pur (sans "Chrome").
     * Couvre la branche `str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')` → 'Safari'.
     *
     * @return void
     */
    public function testLoginWithSafariUserAgent(): void
    {
        $email = $this->insertAccountAndSetUa(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15'
        );
        $e = $this->doLogin($email);
        $this->assertSame(302, $e->status);
    }

    // ----------------------------------------------------------------
    // deriveDeviceName — branche OS iOS (iPhone)
    // ----------------------------------------------------------------

    /**
     * Vérifie que login() réussit avec un UA iPhone.
     * Couvre la branche `str_contains($ua, 'iPhone')` → 'iOS' dans deriveDeviceName().
     *
     * @return void
     */
    public function testLoginWithIphoneUserAgent(): void
    {
        $email = $this->insertAccountAndSetUa(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1'
        );
        $e = $this->doLogin($email);
        $this->assertSame(302, $e->status);
    }

    // ----------------------------------------------------------------
    // deriveDeviceName — branche OS Android
    // ----------------------------------------------------------------

    /**
     * Vérifie que login() réussit avec un UA Android.
     * Couvre la branche `str_contains($ua, 'Android')` → 'Android' dans deriveDeviceName().
     *
     * @return void
     */
    public function testLoginWithAndroidUserAgent(): void
    {
        $email = $this->insertAccountAndSetUa(
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.43 Mobile Safari/537.36'
        );
        $e = $this->doLogin($email);
        $this->assertSame(302, $e->status);
    }

    // ----------------------------------------------------------------
    // deriveDeviceName — branche OS macOS
    // ----------------------------------------------------------------

    /**
     * Vérifie que login() réussit avec un UA macOS (Macintosh).
     * Couvre la branche `str_contains($ua, 'Macintosh')` → 'macOS' dans deriveDeviceName().
     *
     * @return void
     */
    public function testLoginWithMacintoshUserAgent(): void
    {
        $email = $this->insertAccountAndSetUa(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        );
        $e = $this->doLogin($email);
        $this->assertSame(302, $e->status);
    }

    // ----------------------------------------------------------------
    // handleUntrustedDevice — branche displayName company
    // ----------------------------------------------------------------

    /**
     * Vérifie que handleUntrustedDevice() compose le displayName depuis company_name
     * quand account_type = 'company' et que l'appareil n'est pas de confiance.
     * Couvre la branche ternaire ligne 484 dans handleUntrustedDevice().
     *
     * @return void
     */
    public function testLoginWithUntrustedDeviceCompanyAccountRedirectsToNewDevice(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/Test';

        // Compte company vérifié avec has_connected = 1 → pas premier login
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, has_connected, account_type)
             VALUES ('untrusted-company@example.com', ?, 'customer', 'fr', NOW(), 1, 'company')",
            [password_hash('Password123!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_companies (account_id, company_name) VALUES (?, 'Château Test SARL')",
            [$id]
        );

        // Pas de device_token cookie → appareil inconnu → MFA requis
        $_COOKIE = [];
        $_POST = [
            'email'      => 'untrusted-company@example.com',
            'password'   => 'Password123!',
            'csrf_token' => self::CSRF,
        ];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI']    = '/fr/connexion';
        $_GET = [];
        $controller = new AuthController(new Request());

        try {
            $controller->login(['lang' => 'fr']);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(302, $e->status);
            $this->assertStringContainsString('nouvel-appareil', $e->location);
        }
    }
}
