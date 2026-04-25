<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Controller\CronController;
use Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour CronController.
 *
 * Couvre getJob() — mapping slug → callable — sans BDD ni HTTP réel.
 */
class CronControllerTest extends TestCase
{
    private CronController $controller;
    private \ReflectionMethod $getJob;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/cron/test';

        $this->controller = new CronController(new Request());
        $this->getJob     = new \ReflectionMethod(CronController::class, 'getJob');
        $this->getJob->setAccessible(true);
    }

    // ----------------------------------------------------------------
    // getJob — slugs connus
    // ----------------------------------------------------------------

    #[DataProvider('knownSlugsProvider')]
    public function testGetJobReturnsCallableForKnownSlug(string $slug): void
    {
        $fn = $this->getJob->invoke($this->controller, $slug);
        $this->assertIsCallable($fn, "getJob('{$slug}') doit retourner un callable");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function knownSlugsProvider(): array
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
            'purge-order-rating-tokens'      => ['purge-order-rating-tokens'],
            'send-newsletter-queue'            => ['send-newsletter-queue'],
            'send-newsletter-welcome-sequence' => ['send-newsletter-welcome-sequence'],
            'send-reconquest'                  => ['send-reconquest'],
            'send-review-request'              => ['send-review-request'],
            'send-wine-stock-report'           => ['send-wine-stock-report'],
        ];
    }

    // ----------------------------------------------------------------
    // getJob — slug inconnu
    // ----------------------------------------------------------------

    public function testGetJobReturnsNullForUnknownSlug(): void
    {
        $this->assertNull($this->getJob->invoke($this->controller, 'unknown-job'));
    }

    public function testGetJobReturnsNullForEmptySlug(): void
    {
        $this->assertNull($this->getJob->invoke($this->controller, ''));
    }

    // ----------------------------------------------------------------
    // applyTrackingLinks
    // ----------------------------------------------------------------

    public function testApplyTrackingLinksEmptyMapReturnsOriginal(): void
    {
        $method = new \ReflectionMethod(CronController::class, 'applyTrackingLinks');
        $result = $method->invoke($this->controller, 'Hello world', [], 'http://example.com', 1, 'tok');
        $this->assertSame('Hello world', $result);
    }

    public function testApplyTrackingLinksReplacesUrlWithTracking(): void
    {
        $method  = new \ReflectionMethod(CronController::class, 'applyTrackingLinks');
        $linkMap = ['http://example.com/wine' => 42];
        $result  = $method->invoke($this->controller, 'http://example.com/wine', $linkMap, 'http://app.local', 7, 'unsub123');
        $this->assertStringContainsString('/track/click/7/42/', $result);
        $this->assertStringContainsString('unsub123', $result);
    }

    public function testApplyTrackingLinksWithoutUnsubNoTokenInUrl(): void
    {
        $method  = new \ReflectionMethod(CronController::class, 'applyTrackingLinks');
        $linkMap = ['http://example.com/wine' => 5];
        $result  = $method->invoke($this->controller, 'http://example.com/wine', $linkMap, 'http://app.local', 3, '');
        $this->assertStringContainsString('/track/click/3/5', $result);
        $this->assertStringNotContainsString('/track/click/3/5/', $result);
    }

    // ----------------------------------------------------------------
    // processNewsletterQueueItem — dry-run branches
    // ----------------------------------------------------------------

    public function testProcessNewsletterQueueItemDryRunWineType(): void
    {
        $method = new \ReflectionMethod(CronController::class, 'processNewsletterQueueItem');

        $logged  = [];
        $log     = static function (string $msg) use (&$logged): void {
            $logged[] = $msg;
        };
        $context = ['sent' => 0, 'failed' => 0, 'campaignCache' => [], 'linkMapCache' => []];
        $item    = [
            'id'              => 1,
            'wine_id'         => 3,
            'newsletter_id'   => 10,
            'recipient_email' => 'test@example.com',
        ];

        $args   = [[], $log, $item, true, 'http://app.local', 0];
        $args[] = &$context;
        $method->invokeArgs($this->controller, $args);

        $this->assertStringContainsString('[DRY-RUN]', $logged[0]);
        $this->assertStringContainsString('wine#3', $logged[0]);
        $this->assertSame(0, $context['sent']);
    }

    public function testProcessNewsletterQueueItemDryRunAdminType(): void
    {
        $method = new \ReflectionMethod(CronController::class, 'processNewsletterQueueItem');

        $logged  = [];
        $log     = static function (string $msg) use (&$logged): void {
            $logged[] = $msg;
        };
        $context = ['sent' => 0, 'failed' => 0, 'campaignCache' => [], 'linkMapCache' => []];
        $item    = [
            'id'              => 2,
            'wine_id'         => null,
            'newsletter_id'   => 5,
            'recipient_email' => 'admin@example.com',
        ];

        $args   = [[], $log, $item, true, 'http://app.local', 0];
        $args[] = &$context;
        $method->invokeArgs($this->controller, $args);

        $this->assertStringContainsString('[DRY-RUN]', $logged[0]);
        $this->assertStringContainsString('admin-nl#5', $logged[0]);
        $this->assertSame(0, $context['sent']);
    }
}
