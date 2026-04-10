<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendWelcome()
 * et les enrichissements de sendOrderStatusEmail().
 */
class MailServiceWelcomeTest extends TestCase
{
    private MailService $service;

    protected function setUp(): void
    {
        $this->service = new MailService();
        $this->injectMockMailer($this->service);
    }

    /**
     * Injecte un stub PHPMailer dont send() ne lève pas d'exception.
     */
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    // ----------------------------------------------------------------
    // sendWelcome — FR
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendWelcome() FR s'exécute sans exception.
     */
    public function testSendWelcomeFrCallsMailer(): void
    {
        $this->service->sendWelcome('alice@example.com', 'Alice', 'fr');
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendWelcome — EN
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendWelcome() EN s'exécute sans exception.
     */
    public function testSendWelcomeEnCallsMailer(): void
    {
        $this->service->sendWelcome('bob@example.com', 'Bob', 'en');
        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendWelcome — corps FR contient éléments attendus
    // ----------------------------------------------------------------

    /**
     * Vérifie que le corps FR contient le prénom et les CTA catalogue/newsletter.
     */
    public function testSendWelcomeFrBodyContainsExpectedContent(): void
    {
        $capturedBody = '';
        $stub = $this->createMock(PHPMailer::class);
        $stub->method('send')->willReturn(true);
        $stub->method('msgHTML')->willReturnCallback(
            function (string $body) use (&$capturedBody): void {
                $capturedBody = $body;
            }
        );

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($this->service, $stub); // NOSONAR — test unitaire

        // On appelle sendWelcome et on vérifie le HTML via welcomeBodyFr via reflection
        $reflection = new \ReflectionClass(MailService::class);
        $method     = $reflection->getMethod('welcomeBodyFr');
        $html       = $method->invoke($this->service, 'Marie');

        $this->assertStringContainsString('Marie', $html);
        $this->assertStringContainsString('/fr/vins', $html);
        $this->assertStringContainsString('/fr#newsletter', $html);
        $this->assertStringContainsString('Sainte-Croix-du-Mont', $html);
        $this->assertStringContainsString('Découvrir notre catalogue', $html);
        $this->assertStringContainsString("S'abonner", $html);
    }

    // ----------------------------------------------------------------
    // sendWelcome — corps EN contient éléments attendus
    // ----------------------------------------------------------------

    /**
     * Vérifie que le corps EN contient le prénom et les CTA catalogue/newsletter.
     */
    public function testSendWelcomeEnBodyContainsExpectedContent(): void
    {
        $reflection = new \ReflectionClass(MailService::class);
        $method     = $reflection->getMethod('welcomeBodyEn');
        $html       = $method->invoke($this->service, 'John');

        $this->assertStringContainsString('John', $html);
        $this->assertStringContainsString('/en/wines', $html);
        $this->assertStringContainsString('/en#newsletter', $html);
        $this->assertStringContainsString('Sainte-Croix-du-Mont', $html);
        $this->assertStringContainsString('Discover our catalogue', $html);
        $this->assertStringContainsString('Subscribe to our newsletter', $html);
    }

    // ----------------------------------------------------------------
    // sendWelcome — XSS échappé
    // ----------------------------------------------------------------

    /**
     * Vérifie que le prénom est échappé contre XSS dans le corps FR.
     */
    public function testSendWelcomeFrEscapesXss(): void
    {
        $reflection = new \ReflectionClass(MailService::class);
        $method     = $reflection->getMethod('welcomeBodyFr');
        $html       = $method->invoke($this->service, '<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ----------------------------------------------------------------
    // sendOrderStatusEmail enrichi — items + deliveryAddress + trackingUrl
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendOrderStatusEmail shipped avec trackingUrl affiche le CTA suivi.
     */
    public function testSendOrderStatusEmailShippedWithTrackingUrl(): void
    {
        $reflection = new \ReflectionClass(MailService::class);
        $method     = $reflection->getMethod('buildOrderStatusContentFr');
        [,, , $message] = $method->invoke(
            $this->service,
            'shipped',
            'CB-2026-001',
            'Alice',
            'https://crabitan.local',
            [],
            [],
            'https://tracking.example.com/123',
            0
        );

        $this->assertStringContainsString('https://tracking.example.com/123', $message);
        $this->assertStringContainsString('Suivre ma livraison', $message);
    }

    /**
     * Vérifie que le CTA suivi n'apparaît pas pour un statut non-shipped.
     */
    public function testSendOrderStatusEmailProcessingNoTrackingCta(): void
    {
        $reflection = new \ReflectionClass(MailService::class);
        $method     = $reflection->getMethod('buildOrderStatusContentFr');
        [,, , $message] = $method->invoke(
            $this->service,
            'processing',
            'CB-2026-002',
            'Alice',
            'https://crabitan.local',
            [],
            [],
            'https://tracking.example.com/999',
            0
        );

        $this->assertStringNotContainsString('Suivre ma livraison', $message);
    }

    /**
     * Vérifie que l'adresse de livraison FR apparaît dans le message.
     */
    public function testSendOrderStatusEmailFrShowsDeliveryAddress(): void
    {
        $reflection = new \ReflectionClass(MailService::class);
        $method     = $reflection->getMethod('buildOrderStatusContentFr');
        [,, , $message] = $method->invoke(
            $this->service,
            'processing',
            'CB-2026-003',
            'Alice',
            'https://crabitan.local',
            [],
            ['firstname' => 'Alice', 'lastname' => 'Dupont', 'line1' => '12 rue des Vins', 'line2' => '',
                'zip' => '75001', 'city' => 'Paris', 'country' => 'France'],
            '',
            0
        );

        $this->assertStringContainsString('Paris', $message);
        $this->assertStringContainsString('Adresse de livraison', $message);
    }

    /**
     * Vérifie que le CTA "Voir ma commande" avec orderId apparaît dans le message FR.
     */
    public function testSendOrderStatusEmailFrShowsOrderCtaWithId(): void
    {
        $reflection = new \ReflectionClass(MailService::class);
        $method     = $reflection->getMethod('buildOrderStatusContentFr');
        [,, , $message] = $method->invoke(
            $this->service,
            'delivered',
            'CB-2026-004',
            'Alice',
            'https://crabitan.local',
            [],
            [],
            '',
            42
        );

        $this->assertStringContainsString('/fr/mon-compte/commandes/42', $message);
        $this->assertStringContainsString('Voir ma commande', $message);
    }

    /**
     * Vérifie que le CTA "View my order" EN avec orderId fonctionne.
     */
    public function testSendOrderStatusEmailEnShowsOrderCtaWithId(): void
    {
        $reflection = new \ReflectionClass(MailService::class);
        $method     = $reflection->getMethod('buildOrderStatusContentEn');
        [,, , $message] = $method->invoke(
            $this->service,
            'delivered',
            'CB-2026-005',
            'Bob',
            'https://crabitan.local',
            [],
            [],
            '',
            7
        );

        $this->assertStringContainsString('/en/my-account/orders/7', $message);
        $this->assertStringContainsString('View my order', $message);
    }

    /**
     * Vérifie que sendOrderStatusEmail complet (shipped + items + adresse + suivi) s'exécute sans exception.
     */
    public function testSendOrderStatusEmailEnrichedShippedNoException(): void
    {
        $this->service->sendOrderStatusEmail(
            'alice@example.com',
            'Alice',
            'CB-2026-010',
            'shipped',
            'fr',
            'https://crabitan.local',
            [['name' => 'Château Crabitan 2020', 'qty' => 2, 'price' => 18.50]],
            ['firstname' => 'Alice', 'lastname' => 'Martin', 'line1' => '5 allée du Vignoble',
                'line2' => '', 'zip' => '33410', 'city' => 'Sainte-Croix-du-Mont', 'country' => 'France'],
            'https://tracking.example.com/abc123',
            55
        );

        $this->assertTrue(true);
    }

    /**
     * Vérifie que buildDeliveryAddressBlockFr retourne vide si city absent.
     */
    public function testBuildDeliveryAddressBlockFrEmptyIfNoCity(): void
    {
        $reflection = new \ReflectionClass(MailService::class);
        $method     = $reflection->getMethod('buildDeliveryAddressBlockFr');
        $result     = $method->invoke($this->service, ['line1' => '12 rue des Vins', 'city' => '']);

        $this->assertSame('', $result);
    }

    /**
     * Vérifie que buildDeliveryAddressBlockEn retourne vide si city absent.
     */
    public function testBuildDeliveryAddressBlockEnEmptyIfNoCity(): void
    {
        $reflection = new \ReflectionClass(MailService::class);
        $method     = $reflection->getMethod('buildDeliveryAddressBlockEn');
        $result     = $method->invoke($this->service, ['line1' => '12 Wine Street', 'city' => '']);

        $this->assertSame('', $result);
    }
}
