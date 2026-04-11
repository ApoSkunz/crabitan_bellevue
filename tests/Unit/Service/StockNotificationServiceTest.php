<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\StockNotificationService;

/**
 * Tests unitaires de StockNotificationService.
 *
 * Utilise des stubs manuels pour WineModel et MailService
 * afin d'éviter les conflits d'autoloading entre le worktree et le repo principal.
 */
class StockNotificationServiceTest extends TestCase
{
    private StubWineModelForStock $wineModel;
    private StubMailServiceForStock $mailService;
    private StockNotificationService $service;

    protected function setUp(): void
    {
        $this->wineModel   = new StubWineModelForStock();
        $this->mailService = new StubMailServiceForStock();
        $this->service     = new StockNotificationService($this->wineModel, $this->mailService);
        $_ENV['STOCK_ALERTE_LIMIT'] = '300';
        $_ENV['APP_URL'] = 'https://crabitanbellevue.fr';
    }

    // ----------------------------------------------------------------
    // getStockAlertLimit()
    // ----------------------------------------------------------------

    public function testGetStockAlertLimitReadsFromEnv(): void
    {
        $_ENV['STOCK_ALERTE_LIMIT'] = '150';
        $service = new StockNotificationService($this->wineModel, $this->mailService);
        $this->assertSame(150, $service->getStockAlertLimit());
    }

    public function testGetStockAlertLimitDefaultsTo300WhenEnvMissing(): void
    {
        unset($_ENV['STOCK_ALERTE_LIMIT']);
        $service = new StockNotificationService($this->wineModel, $this->mailService);
        $this->assertSame(300, $service->getStockAlertLimit());
    }

    // ----------------------------------------------------------------
    // notifyIfThresholdCrossed() — pas de notification
    // ----------------------------------------------------------------

    public function testNoNotificationWhenOldStockAlreadyBelowThreshold(): void
    {
        $wine   = $this->makeWine(oldStock: 100, notifiedAt: null);
        $result = $this->service->notifyIfThresholdCrossed($wine, 50);
        $this->assertFalse($result);
        $this->assertSame(0, $this->mailService->sendCount);
    }

    public function testNoNotificationWhenNewStockStillAboveThreshold(): void
    {
        $wine   = $this->makeWine(oldStock: 500, notifiedAt: null);
        $result = $this->service->notifyIfThresholdCrossed($wine, 400);
        $this->assertFalse($result);
        $this->assertSame(0, $this->mailService->sendCount);
    }

    public function testNoNotificationWhenAlreadyNotified(): void
    {
        $wine   = $this->makeWine(oldStock: 500, notifiedAt: '2026-01-01 10:00:00');
        $result = $this->service->notifyIfThresholdCrossed($wine, 100);
        $this->assertFalse($result);
        $this->assertSame(0, $this->mailService->sendCount);
    }

    // ----------------------------------------------------------------
    // notifyIfThresholdCrossed() — notification déclenchée
    // ----------------------------------------------------------------

    public function testNotifiesCartUsersWhenThresholdCrossed(): void
    {
        $wine = $this->makeWine(oldStock: 500, notifiedAt: null);
        $this->wineModel->cartUsers = [
            ['email' => 'client@example.com', 'first_name' => 'Alice', 'lang' => 'fr', 'newsletter_unsubscribe_token' => 'tok1'],
        ];

        $result = $this->service->notifyIfThresholdCrossed($wine, 100);

        $this->assertTrue($result);
        $this->assertSame(1, $this->mailService->sendCount);
        $this->assertSame(42, $this->wineModel->notifiedWineId);
    }

    public function testNotifiesNewsletterSubscribersWhenThresholdCrossed(): void
    {
        $wine = $this->makeWine(oldStock: 500, notifiedAt: null);
        $this->wineModel->newsletterSubs = [
            ['email' => 'newsletter@example.com', 'first_name' => 'Bob', 'lang' => 'en', 'unsub_token' => 'tok2'],
        ];

        $result = $this->service->notifyIfThresholdCrossed($wine, 100);

        $this->assertTrue($result);
        $this->assertSame(1, $this->mailService->sendCount);
        $this->assertSame(42, $this->wineModel->notifiedWineId);
    }

    public function testDeduplicatesCartUserAndNewsletterSubscriber(): void
    {
        $wine = $this->makeWine(oldStock: 500, notifiedAt: null);
        $this->wineModel->cartUsers = [
            ['email' => 'shared@example.com', 'first_name' => 'Charlie', 'lang' => 'fr', 'newsletter_unsubscribe_token' => 'tok3'],
        ];
        $this->wineModel->newsletterSubs = [
            ['email' => 'shared@example.com', 'first_name' => 'Charlie', 'lang' => 'fr', 'unsub_token' => 'tok3'],
        ];

        $result = $this->service->notifyIfThresholdCrossed($wine, 100);

        $this->assertTrue($result);
        // Un seul email envoyé (dédupliqué)
        $this->assertSame(1, $this->mailService->sendCount);
    }

    public function testEmailFailureSilentlyIgnored(): void
    {
        $wine = $this->makeWine(oldStock: 500, notifiedAt: null);
        $this->wineModel->cartUsers = [
            ['email' => 'fail@example.com', 'first_name' => 'Dave', 'lang' => 'fr', 'newsletter_unsubscribe_token' => ''],
        ];
        $this->mailService->throwOnSend = true;

        // Ne doit pas propager l'exception
        $result = $this->service->notifyIfThresholdCrossed($wine, 100);

        $this->assertTrue($result);
        $this->assertSame(42, $this->wineModel->notifiedWineId);
    }

    public function testNewStockExactlyAtThresholdTriggersNotification(): void
    {
        $wine = $this->makeWine(oldStock: 500, notifiedAt: null);

        // newStock = seuil exactement (≤ seuil) → déclenche
        $result = $this->service->notifyIfThresholdCrossed($wine, 300);

        $this->assertTrue($result);
        $this->assertSame(42, $this->wineModel->notifiedWineId);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function makeWine(int $oldStock, ?string $notifiedAt): array
    {
        return [
            'id'                    => 42,
            'label_name'            => 'Crabitan Blanc',
            'vintage'               => 2022,
            'stock_quantity'        => $oldStock,
            'low_stock_notified_at' => $notifiedAt,
            'slug'                  => 'crabitan-blanc-2022',
            'image_path'            => 'crabitan.jpg',
            'wine_color'            => 'white',
        ];
    }
}

// ----------------------------------------------------------------
// Stubs manuels — indépendants de l'autoloading du repo principal
// ----------------------------------------------------------------

/**
 * Stub WineModel pour les tests de StockNotificationService.
 */
class StubWineModelForStock extends \Model\WineModel // phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
{
    /** @var array<int, array<string, mixed>> */
    public array $cartUsers = [];

    /** @var array<int, array<string, mixed>> */
    public array $newsletterSubs = [];

    public ?int $notifiedWineId = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCartUsersWithWine(int $wineId): array
    {
        return $this->cartUsers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActiveNewsletterSubscribers(): array
    {
        return $this->newsletterSubs;
    }

    public function setLowStockNotifiedAt(int $id): void
    {
        $this->notifiedWineId = $id;
    }
}

/**
 * Stub MailService pour les tests de StockNotificationService.
 */
class StubMailServiceForStock extends \Service\MailService // phpcs:ignore PSR1.Classes.ClassDeclaration.MultipleClasses
{
    public int $sendCount = 0;
    public bool $throwOnSend = false;

    public function buildNewsletterHtml(
        string $title,
        string $htmlContent,
        ?string $imageUrl = null,
        ?string $unsubToken = null,
        string $lang = 'fr',
        ?int $campaignId = null
    ): string {
        return '<html><body>' . $htmlContent . '</body></html>';
    }

    public function sendLowStockAlert(string $to, string $name, string $subject, string $body): void
    {
        if ($this->throwOnSend) {
            throw new \RuntimeException('SMTP error');
        }
        $this->sendCount++;
    }
}
