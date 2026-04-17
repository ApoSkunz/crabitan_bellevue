<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\EncryptionService;

/**
 * Tests unitaires pour EncryptionService (AES-256-GCM).
 */
class EncryptionServiceTest extends TestCase
{
    private EncryptionService $enc;

    protected function setUp(): void
    {
        $this->enc = new EncryptionService('test-secret-key-for-unit-tests');
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plain = 'test@example.com';

        $encrypted = $this->enc->encrypt($plain);
        $decrypted = $this->enc->decrypt($encrypted);

        $this->assertSame($plain, $decrypted);
    }

    public function testEncryptProducesDifferentIvEachCall(): void
    {
        $plain = '192.168.1.1';

        $a = $this->enc->encrypt($plain);
        $b = $this->enc->encrypt($plain);

        // Deux chiffrements du même texte clair doivent être différents (IV aléatoire)
        $this->assertNotSame($a, $b);
    }

    public function testDecryptWithWrongKeyReturnsNull(): void
    {
        $encrypted = $this->enc->encrypt('secret@domain.fr');

        $other = new EncryptionService('completely-different-key-xyz');
        $result = $other->decrypt($encrypted);

        $this->assertNull($result);
    }

    public function testDecryptCorruptedDataReturnsNull(): void
    {
        $this->assertNull($this->enc->decrypt('not-valid-base64-!!@@##'));
    }

    public function testDecryptTooShortDataReturnsNull(): void
    {
        // Moins de IV_LENGTH + TAG_LENGTH + 1 octets après décodage
        $this->assertNull($this->enc->decrypt(base64_encode('short')));
    }

    public function testEncryptEmptyString(): void
    {
        $encrypted = $this->enc->encrypt('');
        $decrypted = $this->enc->decrypt($encrypted);

        $this->assertSame('', $decrypted);
    }

    public function testEncryptIpAddress(): void
    {
        $ip = '203.0.113.42';

        $encrypted = $this->enc->encrypt($ip);

        $this->assertNotSame($ip, $encrypted);
        $this->assertSame($ip, $this->enc->decrypt($encrypted));
    }

    public function testFromEnvUsesDbEncryptKey(): void
    {
        $_ENV['DB_ENCRYPT_KEY'] = 'env-test-key';
        $service = EncryptionService::fromEnv();

        $plain = 'hello@test.com';
        $this->assertSame($plain, $service->decrypt($service->encrypt($plain)));

        unset($_ENV['DB_ENCRYPT_KEY']);
    }

    public function testEncryptedValueHasEncPrefix(): void
    {
        $encrypted = $this->enc->encrypt('some value');
        $this->assertStringStartsWith('ENC:', $encrypted);
    }

    public function testIsEncryptedReturnsTrueForEncPrefixed(): void
    {
        $encrypted = $this->enc->encrypt('test');
        $this->assertTrue($this->enc->isEncrypted($encrypted));
    }

    public function testIsEncryptedReturnsFalseForPlainText(): void
    {
        $this->assertFalse($this->enc->isEncrypted('plain text value'));
    }

    public function testDecryptLegacyValueWithoutPrefix(): void
    {
        // Simule une valeur chiffrée avec l'ancienne version (sans préfixe ENC:)
        $iv         = random_bytes(12);
        $tag        = '';
        $key        = hash('sha256', 'test-secret-key-for-unit-tests', true);
        $ciphertext = openssl_encrypt('legacy-value', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        $legacy     = base64_encode($iv . $tag . $ciphertext);

        $this->assertSame('legacy-value', $this->enc->decrypt($legacy));
    }

    public function testFromEnvThrowsIfKeyMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        unset($_ENV['DB_ENCRYPT_KEY']);
        EncryptionService::fromEnv();
    }
}
