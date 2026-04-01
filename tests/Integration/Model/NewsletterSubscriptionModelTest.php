<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\NewsletterSubscriptionModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour NewsletterSubscriptionModel.
 *
 * Vérifie le flux complet : inscription pending → confirmation → statut actif.
 * Chaque test s'exécute dans une transaction rollbackée (voir IntegrationTestCase).
 */
class NewsletterSubscriptionModelTest extends IntegrationTestCase
{
    private NewsletterSubscriptionModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new NewsletterSubscriptionModel();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un abonnement newsletter en état pending et retourne l'id.
     *
     * @param string $email
     * @param string $tokenHash SHA-256 du token brut
     * @param string $expiresAt Datetime d'expiration du token
     * @return int
     */
    private function insertPendingSubscription(
        string $email,
        string $tokenHash,
        string $expiresAt
    ): int {
        return (int) self::$db->insert(
            "INSERT INTO newsletter_subscriptions
             (email, newsletter_token_hash, newsletter_token_expires_at,
              newsletter_confirmed, consent_ip, lang, attempts_24h, last_attempt_at)
             VALUES (?, ?, ?, 0, '127.0.0.1', 'fr', 1, NOW())",
            [$email, $tokenHash, $expiresAt]
        );
    }

    // ----------------------------------------------------------------
    // findPendingByTokenHash — token valide non expiré
    // ----------------------------------------------------------------

    public function testFindPendingByTokenHashReturnsRowWhenValid(): void
    {
        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expires     = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $this->insertPendingSubscription('pending@example.com', $hashedToken, $expires);

        $row = $this->model->findPendingByTokenHash($hashedToken);

        $this->assertIsArray($row);
        $this->assertSame('pending@example.com', $row['email']);
        $this->assertSame(0, (int) $row['newsletter_confirmed']);
    }

    // ----------------------------------------------------------------
    // findPendingByTokenHash — token inconnu
    // ----------------------------------------------------------------

    public function testFindPendingByTokenHashReturnsNullWhenUnknown(): void
    {
        $result = $this->model->findPendingByTokenHash('unknownhash');
        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // findPendingByTokenHash — token déjà confirmé
    // ----------------------------------------------------------------

    public function testFindPendingByTokenHashReturnsNullWhenAlreadyConfirmed(): void
    {
        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expires     = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $id = $this->insertPendingSubscription('confirmed@example.com', $hashedToken, $expires);

        // Confirmer manuellement
        self::$db->execute(
            "UPDATE newsletter_subscriptions SET newsletter_confirmed = 1,
             newsletter_consent_date = NOW() WHERE id = ?",
            [$id]
        );

        $result = $this->model->findPendingByTokenHash($hashedToken);
        $this->assertNull($result);
    }

    // ----------------------------------------------------------------
    // confirmByTokenHash — passe confirmed = 1
    // ----------------------------------------------------------------

    public function testConfirmByTokenHashSetsConfirmedStatus(): void
    {
        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expires     = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $id = $this->insertPendingSubscription('toconfirm@example.com', $hashedToken, $expires);

        $this->model->confirmByTokenHash($hashedToken, '192.168.1.1');

        $row = self::$db->fetchOne(
            "SELECT newsletter_confirmed, newsletter_consent_date, newsletter_consent_ip
             FROM newsletter_subscriptions WHERE id = ?",
            [$id]
        );

        $this->assertIsArray($row);
        $this->assertSame(1, (int) $row['newsletter_confirmed']);
        $this->assertNotNull($row['newsletter_consent_date']);
        $this->assertSame('192.168.1.1', $row['newsletter_consent_ip']);
    }

    // ----------------------------------------------------------------
    // upsertPending — crée un nouveau enregistrement pending
    // ----------------------------------------------------------------

    public function testUpsertPendingCreatesNewRecord(): void
    {
        $rawToken    = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $expires     = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $this->model->upsertPending(
            'new@example.com',
            $hashedToken,
            $expires,
            '10.0.0.1',
            'fr'
        );

        $row = self::$db->fetchOne(
            "SELECT email, newsletter_confirmed, consent_ip
             FROM newsletter_subscriptions WHERE email = ?",
            ['new@example.com']
        );

        $this->assertIsArray($row);
        $this->assertSame('new@example.com', $row['email']);
        $this->assertSame(0, (int) $row['newsletter_confirmed']);
        $this->assertSame('10.0.0.1', $row['consent_ip']);
    }

    // ----------------------------------------------------------------
    // countRecentAttempts — compteur d'envois
    // ----------------------------------------------------------------

    public function testCountRecentAttemptsReturnsZeroForNewEmail(): void
    {
        $count = $this->model->countRecentAttempts('nobody@example.com');
        $this->assertSame(0, $count);
    }

    // ----------------------------------------------------------------
    // findByEmail — retourne null si email inconnu
    // ----------------------------------------------------------------

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $result = $this->model->findByEmail('nobody@example.com');
        $this->assertNull($result);
    }
}
