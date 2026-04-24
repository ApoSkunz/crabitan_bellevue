<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Tests de couverture complémentaires pour EncryptionService.
 *
 * Couvre la branche encrypt() → openssl_encrypt() retourne false → RuntimeException (line 78).
 * Nécessite RunInSeparateProcess car la fonction openssl_encrypt est surchargée dans le namespace Service.
 */
class EncryptionServiceCoverageTest extends TestCase
{
    /**
     * Quand openssl_encrypt() échoue (retourne false), encrypt() lève une RuntimeException.
     * Couvre EncryptionService.php:78.
     *
     * La fonction openssl_encrypt est surchargée dans le namespace Service pour ce test isolé.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEncryptThrowsRuntimeExceptionWhenOpensslFails(): void
    {
        // Charger l'autoloader et la classe avant de surcharger la fonction
        require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

        // Surcharge openssl_encrypt dans le namespace Service
        eval('
            namespace Service;
            function openssl_encrypt(
                string $data,
                string $cipher_algo,
                string $passphrase,
                int $options = 0,
                string $iv = "",
                string &$tag = null,
                string $aad = "",
                int $tag_length = 16
            ): string|false {
                return false;
            }
        ');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/chiffrement échoué/');

        $service = new \Service\EncryptionService('test-key');
        $service->encrypt('some plaintext');
    }
}
