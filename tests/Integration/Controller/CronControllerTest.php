<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\CronController;
use Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour CronController.
 *
 * Vérifie le flux HTTP complet : token → job → code retour.
 * Chaque test s'exécute dans une transaction rollbackée.
 */
class CronControllerTest extends IntegrationTestCase
{
    private const TOKEN = 'test-cron-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['CRON_SECRET_TOKEN'] = self::TOKEN;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/cron/test';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_GET = [];
        unset($_ENV['CRON_SECRET_TOKEN']);
    }

    private function makeController(string $job): CronController
    {
        $_SERVER['REQUEST_URI'] = '/cron/' . $job;
        return new CronController(new Request());
    }

    private function runJob(string $job, bool $dryRun = true): string
    {
        $_GET = ['token' => self::TOKEN];
        if ($dryRun) {
            $_GET['dry_run'] = '1';
        }

        ob_start();
        $this->makeController($job)->run(['job' => $job]);
        return (string) ob_get_clean();
    }

    // ----------------------------------------------------------------
    // Token valide + job inconnu → HTTP 404
    // ----------------------------------------------------------------

    public function testRunWithUnknownJobReturns404(): void
    {
        $_GET = ['token' => self::TOKEN];

        ob_start();
        $this->makeController('unknown')->run(['job' => 'unknown-job']);
        $output = ob_get_clean();

        $this->assertSame(404, http_response_code());
        $this->assertStringContainsString('Unknown job', (string) $output);
    }

    // ----------------------------------------------------------------
    // Tous les jobs en dry_run=1 → HTTP 200 + output DRY-RUN ou START
    // ----------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function allJobsProvider(): array
    {
        return [
            'purge-accounts'                 => ['purge-accounts'],
            'purge-inactive-accounts'        => ['purge-inactive-accounts'],
            'pseudonymize-orders'            => ['pseudonymize-orders'],
            'purge-connections'              => ['purge-connections'],
            'purge-addresses'                => ['purge-addresses'],
            'purge-newsletter-subscriptions' => ['purge-newsletter-subscriptions'],
            'purge-rate-limits'              => ['purge-rate-limits'],
            'send-deletion-reminders'        => ['send-deletion-reminders'],
            'send-abandoned-cart'            => ['send-abandoned-cart'],
            'send-post-purchase'             => ['send-post-purchase'],
            'alert-pending-orders'           => ['alert-pending-orders'],
            'clean-expired-tokens'           => ['clean-expired-tokens'],
            'purge-newsletter-tracking'      => ['purge-newsletter-tracking'],
        ];
    }

    #[DataProvider('allJobsProvider')]
    public function testAllJobsDryRunReturn200(string $job): void
    {
        $output = $this->runJob($job, dryRun: true);

        $this->assertSame(200, http_response_code());
        $this->assertNotEmpty($output);
    }

    #[DataProvider('allJobsProvider')]
    public function testAllJobsDryRunOutputStartAndEnd(string $job): void
    {
        $output = $this->runJob($job, dryRun: true);

        $this->assertStringContainsString('START', $output);
        $this->assertStringContainsString('END', $output);
    }

    // ----------------------------------------------------------------
    // Jobs sans boucle — exécution réelle (DB safe, pas d'email)
    // ----------------------------------------------------------------

    public function testPurgeRateLimitsRealRunReturns200(): void
    {
        $output = $this->runJob('purge-rate-limits', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('rate_limits', $output);
    }

    public function testPurgeConnectionsRealRunReturns200(): void
    {
        $output = $this->runJob('purge-connections', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('session(s)', $output);
    }

    public function testPseudonymizeOrdersRealRunReturns200(): void
    {
        $output = $this->runJob('pseudonymize-orders', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('snapshot(s)', $output);
    }

    public function testPurgeNewsletterSubscriptionsRealRunReturns200(): void
    {
        $output = $this->runJob('purge-newsletter-subscriptions', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('abonnement(s)', $output);
    }

    public function testPurgeNewsletterTrackingRealRunReturns200(): void
    {
        $output = $this->runJob('purge-newsletter-tracking', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('tracking', $output);
    }

    public function testCleanExpiredTokensRealRunReturns200(): void
    {
        $output = $this->runJob('clean-expired-tokens', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('password_reset', $output);
    }

    // ----------------------------------------------------------------
    // Jobs avec boucle — exécution réelle avec données seedées
    // ----------------------------------------------------------------

    public function testPurgeAddressesDryRunWithOrphanedAddress(): void
    {
        $userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW())",
            ['orphan-addr@cron-test.com']
        );

        self::$db->execute(
            "INSERT INTO addresses
             (user_id, type, firstname, lastname, street, city, zip_code, country, phone, preserved_for_order)
             VALUES (?, 'billing', 'Test', 'User', 'ENC:dummystreet', 'Bordeaux', '33000', 'FR',
                     'ENC:dummyphone', 1)",
            [$userId]
        );

        $output = $this->runJob('purge-addresses', dryRun: true);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('Adresses orphelines', $output);
        $this->assertStringContainsString('DRY-RUN', $output);
    }

    public function testPurgeAccountsRealRunWithNoDueAccounts(): void
    {
        $output = $this->runJob('purge-accounts', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('anonymisation', $output);
    }

    // ----------------------------------------------------------------
    // purge-accounts — avec compte éligible (foreach body)
    // ----------------------------------------------------------------

    public function testPurgeAccountsDryRunWithEligibleAccount(): void
    {
        self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, scheduled_deletion_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW(), DATE_SUB(NOW(), INTERVAL 1 DAY))",
            ['purge-eligible-dry@cron-test.com']
        );

        $output = $this->runJob('purge-accounts', dryRun: true);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('DRY-RUN', $output);
    }

    public function testPurgeAccountsRealRunWithEligibleAccount(): void
    {
        self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, scheduled_deletion_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW(), DATE_SUB(NOW(), INTERVAL 1 DAY))",
            ['purge-eligible-real@cron-test.com']
        );

        $output = $this->runJob('purge-accounts', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('anonymisation', $output);
    }

    // ----------------------------------------------------------------
    // send-deletion-reminders — branche !dryRun + compte éligible J-7
    // ----------------------------------------------------------------

    public function testSendDeletionRemindersRealRunReturns200(): void
    {
        $output = $this->runJob('send-deletion-reminders', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('rappel(s)', $output);
    }

    public function testSendDeletionRemindersDryRunWithEligibleAccount(): void
    {
        self::$db->insert(
            "INSERT INTO accounts
             (email, password, role, lang, email_verified_at, deleted_at, scheduled_deletion_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW(), NOW(), DATE_ADD(NOW(), INTERVAL 168 HOUR))",
            ['reminder-7d@cron-test.com']
        );

        $output = $this->runJob('send-deletion-reminders', dryRun: true);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('DRY-RUN', $output);
    }

    // ----------------------------------------------------------------
    // purge-inactive-accounts — phase 1 et 2 avec comptes éligibles
    // ----------------------------------------------------------------

    public function testPurgeInactiveAccountsDryRunWithInactiveAccount(): void
    {
        self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, created_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW(), DATE_SUB(NOW(), INTERVAL 37 MONTH))",
            ['inactive-dry@cron-test.com']
        );

        $output = $this->runJob('purge-inactive-accounts', dryRun: true);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('DRY-RUN', $output);
    }

    public function testPurgeInactiveAccountsRealRunWithInactiveAccount(): void
    {
        self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at, created_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW(), DATE_SUB(NOW(), INTERVAL 37 MONTH))",
            ['inactive-real@cron-test.com']
        );

        $output = $this->runJob('purge-inactive-accounts', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('avertissement(s)', $output);
    }

    public function testPurgeInactiveAccountsDryRunWithSoftDeletableAccount(): void
    {
        self::$db->insert(
            "INSERT INTO accounts
             (email, password, role, lang, email_verified_at, inactivity_warning_sent_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW(), DATE_SUB(NOW(), INTERVAL 31 DAY))",
            ['softdelete-dry@cron-test.com']
        );

        $output = $this->runJob('purge-inactive-accounts', dryRun: true);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('DRY-RUN', $output);
    }

    public function testPurgeInactiveAccountsRealRunWithSoftDeletableAccount(): void
    {
        self::$db->insert(
            "INSERT INTO accounts
             (email, password, role, lang, email_verified_at, inactivity_warning_sent_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW(), DATE_SUB(NOW(), INTERVAL 31 DAY))",
            ['softdelete-real@cron-test.com']
        );

        $output = $this->runJob('purge-inactive-accounts', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('avertissement(s)', $output);
    }

    // ----------------------------------------------------------------
    // purge-addresses — exécution réelle avec suppression
    // ----------------------------------------------------------------

    public function testPurgeAddressesRealRunDeletesOrphanedAddress(): void
    {
        $userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW())",
            ['orphan-addr-real@cron-test.com']
        );

        self::$db->execute(
            "INSERT INTO addresses
             (user_id, type, firstname, lastname, street, city, zip_code, country, phone, preserved_for_order)
             VALUES (?, 'billing', 'Test', 'User', 'ENC:dummystreet', 'Bordeaux', '33000', 'FR',
                     'ENC:dummyphone', 1)",
            [$userId]
        );

        $output = $this->runJob('purge-addresses', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('adresse(s)', $output);
    }

    // ----------------------------------------------------------------
    // send-abandoned-cart — avec panier abandonné seedé
    // ----------------------------------------------------------------

    public function testSendAbandonedCartDryRunWithAbandonedCart(): void
    {
        $wineId = self::insertTestWine('cron-abandoned', 'red', 'Wine Cron Test');

        $userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW())",
            ['abandoned-cart@cron-test.com']
        );

        self::$db->execute(
            "INSERT INTO carts (user_id, content, total_quantity, updated_at)
             VALUES (?, ?, 1, DATE_SUB(NOW(), INTERVAL 84 HOUR))",
            [$userId, json_encode([['wine_id' => $wineId, 'qty' => 1]])]
        );

        $output = $this->runJob('send-abandoned-cart', dryRun: true);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('DRY-RUN', $output);
    }

    public function testSendAbandonedCartRealRunWithAbandonedCart(): void
    {
        $wineId = self::insertTestWine('cron-abandoned-real', 'red', 'Wine Cron Real');

        $userId = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, 'hash', 'customer', 'fr', NOW())",
            ['abandoned-cart-real@cron-test.com']
        );

        self::$db->execute(
            "INSERT INTO carts (user_id, content, total_quantity, updated_at)
             VALUES (?, ?, 1, DATE_SUB(NOW(), INTERVAL 84 HOUR))",
            [$userId, json_encode([['wine_id' => $wineId, 'qty' => 1]])]
        );

        $output = $this->runJob('send-abandoned-cart', dryRun: false);

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('Paniers', $output);
    }
}
