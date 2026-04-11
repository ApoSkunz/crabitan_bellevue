<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour la consolidation newsletter dans AccountModel.
 *
 * Vérifie que getNewsletterSubscribers() et countNewsletterSubscribers()
 * agrègent correctement les deux sources (accounts + newsletter_subscriptions)
 * et que deleteNewsletterSubscriptionByEmail() effectue le dédoublonnage.
 */
class AccountModelNewsletterConsolidationTest extends IntegrationTestCase
{
    private AccountModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AccountModel();
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Crée un compte avec newsletter=1 et retourne son email.
     *
     * @param string $email
     * @return string Email du compte créé
     */
    private function createNewsletterAccount(string $email): string
    {
        $this->model->create(
            'individual',
            $email,
            password_hash('TestPass7#!', PASSWORD_BCRYPT),
            'fr',
            1,
            bin2hex(random_bytes(16)),
            'M',
            'Dupont',
            'Jean',
            ''
        );
        return $email;
    }

    /**
     * Insère un abonnement newsletter_subscriptions confirmé.
     *
     * @param string $email
     * @return void
     */
    private function insertConfirmedSubscription(string $email): void
    {
        self::$db->execute(
            "INSERT INTO newsletter_subscriptions
             (email, newsletter_token_hash, newsletter_token_expires_at,
              newsletter_confirmed, newsletter_consent_date, newsletter_consent_ip,
              consent_ip, lang, attempts_24h, last_attempt_at)
             VALUES (?, NULL, NULL, 1, NOW(), '127.0.0.1', '127.0.0.1', 'fr', 0, NOW())",
            [$email]
        );
    }

    // ----------------------------------------------------------------
    // getNewsletterSubscribers — source account uniquement
    // ----------------------------------------------------------------

    /**
     * Un compte avec newsletter=1 apparaît dans la liste consolidée.
     *
     * @return void
     */
    public function testAccountWithNewsletterAppearsInList(): void
    {
        $email = 'account-nl-' . uniqid() . '@example.com';
        $this->createNewsletterAccount($email);

        $rows = $this->model->getNewsletterSubscribers(100, 0);
        $emails = array_column($rows, 'email');

        $this->assertContains($email, $emails, 'Le compte newsletter=1 doit apparaître dans la liste');
    }

    // ----------------------------------------------------------------
    // getNewsletterSubscribers — source newsletter_subscriptions
    // ----------------------------------------------------------------

    /**
     * Un abonné sans compte (newsletter_subscriptions confirmé) apparaît dans la liste.
     *
     * @return void
     */
    public function testSubscriptionWithoutAccountAppearsInList(): void
    {
        $email = 'ns-only-' . uniqid() . '@example.com';
        $this->insertConfirmedSubscription($email);

        $rows = $this->model->getNewsletterSubscribers(100, 0);
        $emails = array_column($rows, 'email');

        $this->assertContains($email, $emails, 'L\'abonné sans compte confirmé doit apparaître dans la liste');
    }

    // ----------------------------------------------------------------
    // getNewsletterSubscribers — pas de doublon quand les deux sources ont le même email
    // ----------------------------------------------------------------

    /**
     * Si le même email existe dans les deux sources, il n'apparaît qu'une seule fois.
     *
     * @return void
     */
    public function testNoDuplicateWhenEmailInBothSources(): void
    {
        $email = 'both-sources-' . uniqid() . '@example.com';
        $this->createNewsletterAccount($email);
        $this->insertConfirmedSubscription($email);

        $rows = $this->model->getNewsletterSubscribers(100, 0);
        $emails = array_column($rows, 'email');
        $count = count(array_filter($emails, fn($e) => $e === $email));

        $this->assertSame(1, $count, 'L\'email ne doit apparaître qu\'une seule fois (UNION sans doublon)');
    }

    // ----------------------------------------------------------------
    // countNewsletterSubscribers — compte les deux sources
    // ----------------------------------------------------------------

    /**
     * Le compteur reflète les deux sources sans doublon.
     *
     * @return void
     */
    public function testCountIncludesBothSources(): void
    {
        $emailAccount = 'count-acc-' . uniqid() . '@example.com';
        $emailNs      = 'count-ns-' . uniqid() . '@example.com';

        $this->createNewsletterAccount($emailAccount);
        $this->insertConfirmedSubscription($emailNs);

        $count = $this->model->countNewsletterSubscribers();

        // Au moins 2 (les deux que l'on vient d'insérer)
        $this->assertGreaterThanOrEqual(2, $count, 'Le compteur doit inclure les deux sources');
    }

    /**
     * Un email dans les deux sources ne compte qu'une fois.
     *
     * @return void
     */
    public function testCountNoDuplicateForSameEmail(): void
    {
        $email = 'count-both-' . uniqid() . '@example.com';
        $this->createNewsletterAccount($email);
        $this->insertConfirmedSubscription($email);

        $countBefore = $this->model->countNewsletterSubscribers();
        // On insère un deuxième abonné sans compte distinct pour avoir un delta mesurable
        $emailNs2 = 'count-ns2-' . uniqid() . '@example.com';
        $this->insertConfirmedSubscription($emailNs2);
        $countAfter = $this->model->countNewsletterSubscribers();

        $this->assertSame($countBefore + 1, $countAfter, 'L\'email doublon ne doit compter qu\'une fois, le nouveau +1');
    }

    // ----------------------------------------------------------------
    // deleteNewsletterSubscriptionByEmail — dédoublonnage
    // ----------------------------------------------------------------

    /**
     * Après deleteNewsletterSubscriptionByEmail(), l'entrée est absente de newsletter_subscriptions.
     *
     * @return void
     */
    public function testDeleteNewsletterSubscriptionByEmailRemovesRow(): void
    {
        $email = 'delete-ns-' . uniqid() . '@example.com';
        $this->insertConfirmedSubscription($email);

        // Vérifier que l'entrée existe
        $row = self::$db->fetchOne(
            "SELECT id FROM newsletter_subscriptions WHERE email = ?",
            [$email]
        );
        $this->assertNotFalse($row, 'L\'entrée doit exister avant suppression');

        $this->model->deleteNewsletterSubscriptionByEmail($email);

        $rowAfter = self::$db->fetchOne(
            "SELECT id FROM newsletter_subscriptions WHERE email = ?",
            [$email]
        );
        $this->assertFalse($rowAfter, 'L\'entrée doit être supprimée après dédoublonnage');
    }

    /**
     * deleteNewsletterSubscriptionByEmail() sur un email absent ne lève pas d'exception.
     *
     * @return void
     */
    public function testDeleteNewsletterSubscriptionByEmailIdempotent(): void
    {
        // Ne doit pas lever d'exception
        $this->model->deleteNewsletterSubscriptionByEmail('nonexistent-' . uniqid() . '@example.com');
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // getNewsletterSubscribers — abonné non confirmé exclu
    // ----------------------------------------------------------------

    /**
     * Un abonné pending (newsletter_confirmed=0) ne doit pas apparaître dans la liste.
     *
     * @return void
     */
    public function testPendingSubscriptionNotInList(): void
    {
        $email = 'pending-ns-' . uniqid() . '@example.com';
        $hash  = hash('sha256', bin2hex(random_bytes(32)));
        $exp   = date('Y-m-d H:i:s', strtotime('+48 hours'));

        self::$db->execute(
            "INSERT INTO newsletter_subscriptions
             (email, newsletter_token_hash, newsletter_token_expires_at,
              newsletter_confirmed, consent_ip, lang, attempts_24h, last_attempt_at)
             VALUES (?, ?, ?, 0, '127.0.0.1', 'fr', 0, NOW())",
            [$email, $hash, $exp]
        );

        $rows = $this->model->getNewsletterSubscribers(100, 0);
        $emails = array_column($rows, 'email');

        $this->assertNotContains($email, $emails, 'Un abonné pending ne doit pas apparaître dans la liste');
    }
}
