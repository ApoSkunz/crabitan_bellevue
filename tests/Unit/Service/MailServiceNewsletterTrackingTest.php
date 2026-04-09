<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour les fonctionnalités de tracking newsletter dans MailService.
 * Couvre sendNewsletter() (List-Unsubscribe RFC 8058) et buildNewsletterHtml() (pixel).
 */
class MailServiceNewsletterTrackingTest extends TestCase
{
    private function injectMockMailer(MailService $service): PHPMailer
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
        return $stub;
    }

    // ----------------------------------------------------------------
    // sendNewsletter() — sans token (comportement existant inchangé)
    // ----------------------------------------------------------------

    /**
     * sendNewsletter() sans unsubToken ne lève pas d'exception.
     */
    public function testSendNewsletterWithoutTokenDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendNewsletter('a@b.com', 'Test', 'Sujet', '<p>Corps</p>');

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendNewsletter() — avec unsubToken (RFC 8058 List-Unsubscribe)
    // ----------------------------------------------------------------

    /**
     * sendNewsletter() avec unsubToken ne lève pas d'exception.
     */
    public function testSendNewsletterWithTokenDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendNewsletter(
            'a@b.com',
            'Test',
            'Sujet',
            '<p>Corps</p>',
            null,
            null,
            'my-unsub-token-123'
        );

        $this->assertTrue(true);
    }

    /**
     * sendNewsletter() avec unsubToken appelle clearCustomHeaders() après l'envoi
     * (pas de fuite de headers entre envois).
     */
    public function testSendNewsletterWithTokenClearsCustomHeadersAfterSend(): void
    {
        $service = new MailService();
        $mailer  = $this->createMock(PHPMailer::class);
        $mailer->method('send')->willReturn(true);

        // clearCustomHeaders doit être appelé exactement une fois
        $mailer->expects($this->once())->method('clearCustomHeaders');

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $mailer); // NOSONAR — test unitaire, accès privé délibéré

        $service->sendNewsletter(
            'a@b.com',
            'Test',
            'Sujet',
            '<p>Corps</p>',
            null,
            null,
            'token-abc'
        );
    }

    /**
     * sendNewsletter() sans token ne doit PAS appeler clearCustomHeaders().
     */
    public function testSendNewsletterWithoutTokenDoesNotClearHeaders(): void
    {
        $service = new MailService();
        $mailer  = $this->createMock(PHPMailer::class);
        $mailer->method('send')->willReturn(true);

        $mailer->expects($this->never())->method('clearCustomHeaders');

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $mailer); // NOSONAR — test unitaire, accès privé délibéré

        $service->sendNewsletter('a@b.com', 'Test', 'Sujet', '<p>Corps</p>');
    }

    // ----------------------------------------------------------------
    // buildNewsletterHtml() — avec campaignId + pixel
    // ----------------------------------------------------------------

    /**
     * buildNewsletterHtml() avec campaignId + unsubToken intègre l'URL du pixel.
     */
    public function testBuildNewsletterHtmlWithCampaignIdContainsPixel(): void
    {
        $service = new MailService();
        $html    = $service->buildNewsletterHtml(
            'Newsletter · Abonnés',
            '<p>Corps</p>',
            null,
            'my-unsub-token',
            'fr',
            42
        );

        $this->assertStringContainsString('/track/open/42/', $html);
        $this->assertStringContainsString('width="1"', $html);
        $this->assertStringContainsString('height="1"', $html);
    }

    /**
     * buildNewsletterHtml() sans campaignId ne contient pas de pixel de tracking.
     */
    public function testBuildNewsletterHtmlWithoutCampaignIdHasNoPixel(): void
    {
        $service = new MailService();
        $html    = $service->buildNewsletterHtml(
            'Newsletter · Abonnés',
            '<p>Corps</p>',
            null,
            'my-unsub-token',
            'fr'
            // campaignId absent → null par défaut
        );

        $this->assertStringNotContainsString('/track/open/', $html);
    }

    /**
     * buildNewsletterHtml() avec campaignId mais sans unsubToken n'intègre pas de pixel.
     */
    public function testBuildNewsletterHtmlWithCampaignIdButNoTokenHasNoPixel(): void
    {
        $service = new MailService();
        $html    = $service->buildNewsletterHtml(
            'Newsletter · Abonnés',
            '<p>Corps</p>',
            null,
            null,   // pas de token
            'fr',
            42
        );

        $this->assertStringNotContainsString('/track/open/', $html);
    }

    /**
     * buildNewsletterHtml() encode correctement le token dans l'URL du pixel.
     */
    public function testBuildNewsletterHtmlPixelEncodesToken(): void
    {
        $service = new MailService();
        $token   = 'token with spaces & chars';
        $html    = $service->buildNewsletterHtml(
            'Test',
            '<p>Corps</p>',
            null,
            $token,
            'fr',
            7
        );

        $this->assertStringContainsString(rawurlencode($token), $html);
    }
}
