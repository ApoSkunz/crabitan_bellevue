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
}
