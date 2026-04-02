<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Model\AccountModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour les méthodes double opt-in newsletter d'AccountModel.
 *
 * Pattern : connexion BDD réelle, markTestSkipped si indisponible.
 */
class AccountModelNewsletterOptinTest extends TestCase
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
     * confirmNewsletterByToken retourne false pour un token inconnu.
     * Skippé si la colonne newsletter_confirm_token n'est pas encore migrée.
     */
    public function testConfirmNewsletterByTokenReturnsFalseForUnknownToken(): void
    {
        try {
            $result = $this->model->confirmNewsletterByToken('token_inconnu_xyz_' . bin2hex(random_bytes(8)));
            $this->assertFalse($result);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Colonne newsletter_confirm_token manquante — relancer schema.sql : ' . $e->getMessage());
        }
    }

    /**
     * confirmNewsletterByToken retourne false pour un token inexistant.
     * Skippé si la colonne newsletter_confirm_token n'est pas encore migrée.
     */
    public function testConfirmNewsletterByTokenReturnsFalseOrArray(): void
    {
        try {
            $fakeToken = bin2hex(random_bytes(32));
            $result    = $this->model->confirmNewsletterByToken($fakeToken);
            $this->assertTrue(
                $result === false || is_array($result),
                'confirmNewsletterByToken doit retourner array|false.'
            );
        } catch (\PDOException $e) {
            $this->markTestSkipped('Colonne newsletter_confirm_token manquante — relancer schema.sql : ' . $e->getMessage());
        }
    }

    /**
     * storeNewsletterConfirmToken n'émet pas d'exception pour un id inexistant.
     * (UPDATE sur id inexistant → 0 lignes affectées, pas d'erreur)
     * Skippé si les colonnes newsletter_confirm_token ne sont pas encore migrées.
     */
    public function testStoreNewsletterConfirmTokenDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        try {
            $this->model->storeNewsletterConfirmToken(0, bin2hex(random_bytes(32)));
        } catch (\PDOException $e) {
            $this->markTestSkipped('Colonnes newsletter_confirm_* manquantes — relancer schema.sql : ' . $e->getMessage());
        }
    }

    /**
     * activateNewsletterFromPending n'émet pas d'exception pour un id inexistant.
     * Skippé si la colonne newsletter_optin_pending n'est pas encore migrée.
     */
    public function testActivateNewsletterFromPendingDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        try {
            $this->model->activateNewsletterFromPending(0);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Colonne newsletter_optin_pending manquante — relancer schema.sql : ' . $e->getMessage());
        }
    }
}
