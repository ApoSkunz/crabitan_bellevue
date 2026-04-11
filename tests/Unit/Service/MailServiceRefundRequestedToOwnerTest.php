<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendRefundRequestedToOwner().
 *
 * Couvre : orderId > 0, orderId = 0, fallback MAIL_USER.
 */
class MailServiceRefundRequestedToOwnerTest extends TestCase
{
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    protected function setUp(): void
    {
        $_ENV['MAIL_USER'] = 'noreply@crabitanbellevue.fr';
        $_ENV['APP_URL']   = 'https://crabitanbellevue.fr';
    }

    protected function tearDown(): void
    {
        unset($_ENV['CONTACT_OWNER_EMAIL'], $_ENV['MAIL_USER'], $_ENV['APP_URL']);
    }

    // ----------------------------------------------------------------
    // orderId > 0 — lien admin généré
    // ----------------------------------------------------------------

    /**
     * Commande avec id > 0 : le lien admin est inclus dans le message.
     */
    public function testSendRefundRequestedToOwnerWithValidOrderIdDoesNotThrow(): void
    {
        $_ENV['CONTACT_OWNER_EMAIL'] = 'owner@crabitanbellevue.fr';

        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendRefundRequestedToOwner(
            ['id' => 42, 'order_reference' => 'CB-2026-0042', 'price' => '125.50'],
            'Jean Dupont',
            'jean@test.com'
        );

        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // orderId = 0 — lien admin absent
    // ----------------------------------------------------------------

    /**
     * Commande sans id (id absent ou 0) : orderLink vide, pas de lien admin.
     */
    public function testSendRefundRequestedToOwnerWithoutOrderIdDoesNotThrow(): void
    {
        $_ENV['CONTACT_OWNER_EMAIL'] = 'owner@crabitanbellevue.fr';

        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendRefundRequestedToOwner(
            ['order_reference' => 'CB-2026-0099', 'price' => '75.00'],
            'Marie Martin',
            'marie@test.com'
        );

        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // Fallback MAIL_USER quand CONTACT_OWNER_EMAIL absent
    // ----------------------------------------------------------------

    /**
     * CONTACT_OWNER_EMAIL absent → fallback sur MAIL_USER sans erreur.
     */
    public function testSendRefundRequestedToOwnerFallsBackToMailUser(): void
    {
        unset($_ENV['CONTACT_OWNER_EMAIL']);
        $_ENV['MAIL_USER'] = 'fallback@crabitanbellevue.fr';

        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendRefundRequestedToOwner(
            ['id' => 7, 'order_reference' => 'CB-2026-0007', 'price' => '199.99'],
            'Paul Durand',
            'paul@test.com'
        );

        $this->addToAssertionCount(1);
    }
}
