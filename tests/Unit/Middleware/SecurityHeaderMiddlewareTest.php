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
}
