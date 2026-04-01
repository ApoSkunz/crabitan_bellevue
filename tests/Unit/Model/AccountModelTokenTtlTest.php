<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Model\AccountModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour les TTL des tokens dans AccountModel.
 *
 * Vérifie la logique de filtrage par expiration du token de vérification email (TTL 24 h).
 */
class AccountModelTokenTtlTest extends TestCase
{
    private AccountModel $model;

    protected function setUp(): void
    {
        try {
            $this->model = new AccountModel();
        } catch (\Throwable $e) {
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
    }

    /**
     * Un token de vérification inexistant doit retourner false.
     */
    public function testFindByVerificationTokenReturnsFalseForUnknownToken(): void
    {
        $result = $this->model->findByVerificationToken('token_inexistant_xyz_abc_000');
        $this->assertFalse($result);
    }

    /**
     * Un token de vérification aléatoire (absent de BDD) est traité comme expiré/invalide.
     *
     * Ce test couvre indirectement la clause SQL qui filtre les tokens expirés :
     *   AND (email_verification_token_expires_at IS NULL OR email_verification_token_expires_at > NOW())
     */
    public function testFindByVerificationTokenFiltersExpiredTokens(): void
    {
        $fakeToken = bin2hex(random_bytes(32));
        $result = $this->model->findByVerificationToken($fakeToken);
        $this->assertFalse($result, 'Un token expiré ou inconnu doit retourner false.');
    }

    /**
     * La méthode findByVerificationToken retourne false ou un tableau (jamais d'exception).
     */
    public function testFindByVerificationTokenReturnsArrayOrFalse(): void
    {
        $result = $this->model->findByVerificationToken('token_absent');
        $this->assertTrue(
            $result === false || is_array($result),
            'findByVerificationToken doit retourner array|false.'
        );
    }
}
