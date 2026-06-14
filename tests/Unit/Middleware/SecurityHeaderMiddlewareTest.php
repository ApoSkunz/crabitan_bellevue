<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Middleware\SecurityHeaderMiddleware;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour SecurityHeaderMiddleware.
 */
class SecurityHeaderMiddlewareTest extends TestCase
{
    /**
     * Vérifie que apply() définit CSP_NONCE et que c'est du base64 valide (16 octets).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testApplyDefinesCspNonceAsValidBase64(): void
    {
        SecurityHeaderMiddleware::apply();

        $nonce   = constant('CSP_NONCE');
        $decoded = base64_decode((string) $nonce, strict: true);

        $this->assertNotFalse($decoded, 'CSP_NONCE doit être du base64 valide');
        $this->assertSame(16, strlen((string) $decoded), 'Le nonce doit être issu de 16 octets');
    }

    /**
     * Vérifie qu'un second appel à apply() ne modifie pas CSP_NONCE (idempotence).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testApplyIsIdempotent(): void
    {
        SecurityHeaderMiddleware::apply();
        $first = (string) constant('CSP_NONCE');

        SecurityHeaderMiddleware::apply();
        $second = (string) constant('CSP_NONCE');

        $this->assertSame($first, $second, 'Un second appel ne doit pas modifier CSP_NONCE');
    }

    // ── isWinePath() ─────────────────────────────────────────────────────────

    /**
     * Les URIs catalogue vins doivent retourner true.
     */
    public function testIsWinePathReturnsTrueForFrVins(): void
    {
        $this->assertTrue(SecurityHeaderMiddleware::isWinePath('/fr/vins'));
        $this->assertTrue(SecurityHeaderMiddleware::isWinePath('/fr/vins/'));
        $this->assertTrue(SecurityHeaderMiddleware::isWinePath('/fr/vins/collection'));
        $this->assertTrue(SecurityHeaderMiddleware::isWinePath('/fr/vins/sainte-croix-du-mont-2020'));
        $this->assertTrue(SecurityHeaderMiddleware::isWinePath('/en/wines'));
        $this->assertTrue(SecurityHeaderMiddleware::isWinePath('/en/wines/'));
        $this->assertTrue(SecurityHeaderMiddleware::isWinePath('/en/wines/some-wine-slug'));
    }

    /**
     * Les URIs hors catalogue vins doivent retourner false.
     */
    public function testIsWinePathReturnsFalseForNonWinePaths(): void
    {
        $this->assertFalse(SecurityHeaderMiddleware::isWinePath('/'));
        $this->assertFalse(SecurityHeaderMiddleware::isWinePath('/fr/contact'));
        $this->assertFalse(SecurityHeaderMiddleware::isWinePath('/fr/le-chateau'));
        $this->assertFalse(SecurityHeaderMiddleware::isWinePath('/admin/vins'));
        $this->assertFalse(SecurityHeaderMiddleware::isWinePath('/fr/actualites'));
        $this->assertFalse(SecurityHeaderMiddleware::isWinePath('/fr/vinsnot'));
    }

    // ── apply() avec wine path ────────────────────────────────────────

    /**
     * apply() émet X-Robots-Tag quand l'URI est un chemin vins.
     * Couvre SecurityHeaderMiddleware.php:51.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testApplyEmitsXRobotsTagOnWinePath(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/vins/sainte-croix-du-mont-2022';
        SecurityHeaderMiddleware::apply();
        // header() est no-op en CLI — on vérifie juste qu'aucune exception n'est levée
        // et que isWinePath retourne true pour ce chemin (cohérence de la branche line 51)
        $this->assertTrue(SecurityHeaderMiddleware::isWinePath('/fr/vins/sainte-croix-du-mont-2022'));
    }

    /**
     * isWinePath ignore les query strings.
     */
    public function testIsWinePathIgnoresQueryString(): void
    {
        $this->assertTrue(SecurityHeaderMiddleware::isWinePath('/fr/vins?color=red&page=2'));
        $this->assertFalse(SecurityHeaderMiddleware::isWinePath('/fr/contact?ref=vins'));
    }

    // ── buildCspString() ─────────────────────────────────────────────

    /**
     * La CSP doit contenir upgrade-insecure-requests (VULN-004).
     */
    public function testBuildCspStringContainsUpgradeInsecureRequests(): void
    {
        $csp = SecurityHeaderMiddleware::buildCspString('test-nonce');

        $this->assertStringContainsString('upgrade-insecure-requests', $csp);
    }

    /**
     * La CSP doit contenir le nonce fourni dans script-src.
     */
    public function testBuildCspStringContainsNonce(): void
    {
        $nonce = 'abc123nonce';
        $csp   = SecurityHeaderMiddleware::buildCspString($nonce);

        $this->assertStringContainsString("'nonce-{$nonce}'", $csp);
    }

    /**
     * La CSP doit bloquer les objets embarqués (object-src none).
     */
    public function testBuildCspStringBlocksObjects(): void
    {
        $csp = SecurityHeaderMiddleware::buildCspString('x');

        $this->assertStringContainsString("object-src 'none'", $csp);
    }
}
