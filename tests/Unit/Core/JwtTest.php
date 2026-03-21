<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Jwt;
use PHPUnit\Framework\TestCase;

class JwtTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['JWT_SECRET'] = 'test-secret-key';
        $_ENV['JWT_EXPIRY'] = '3600';
    }

    public function testEncodeDecodeRoundtrip(): void
    {
        $payload = ['sub' => 1, 'role' => 'customer', 'iat' => time()];

        $token = Jwt::encode($payload);
        $decoded = Jwt::decode($token);

        $this->assertSame(1, $decoded['sub']);
        $this->assertSame('customer', $decoded['role']);
    }

    public function testTokenHasThreeParts(): void
    {
        $token = Jwt::encode(['sub' => 1]);
        $this->assertCount(3, explode('.', $token));
    }

    public function testDecodeThrowsOnInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Token JWT invalide');

        Jwt::decode('not.a.valid.jwt.token.with.too.many.parts');
    }

    public function testDecodeThrowsOnInvalidSignature(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Signature JWT invalide');

        $token = Jwt::encode(['sub' => 1]);
        $parts = explode('.', $token);
        $parts[2] = 'invalidsignature';

        Jwt::decode(implode('.', $parts));
    }

    public function testDecodeThrowsOnExpiredToken(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token JWT expiré');

        $token = Jwt::encode(['sub' => 1, 'exp' => time() - 1]);
        Jwt::decode($token);
    }

    public function testGenerateReturnsValidToken(): void
    {
        $token = Jwt::generate(42, 'admin');
        $decoded = Jwt::decode($token);

        $this->assertSame(42, $decoded['sub']);
        $this->assertSame('admin', $decoded['role']);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
        $this->assertGreaterThan(time(), $decoded['exp']);
    }

    public function testDecodeThrowsOnTwoPartToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Jwt::decode('only.twoparts');
    }
}
