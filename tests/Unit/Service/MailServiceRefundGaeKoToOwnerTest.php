<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendRefundGaeKoToOwner().
 *
 * Couvre : nominal avec lien admin, sans orderId, fallback MAIL_USER, montant zéro.
 */
class MailServiceRefundGaeKoToOwnerTest extends TestCase
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
        $_ENV['MAIL_USER']           = 'noreply@crabitanbellevue.fr';
        $_ENV['CONTACT_OWNER_EMAIL'] = 'owner@crabitanbellevue.fr';
        $_ENV['APP_URL']             = 'https://crabitanbellevue.fr';
    }

    protected function tearDown(): void
    {
        unset($_ENV['CONTACT_OWNER_EMAIL'], $_ENV['MAIL_USER'], $_ENV['APP_URL']);
    }

    // ----------------------------------------------------------------
    // Nominal avec orderId — lien admin inclus
    // ----------------------------------------------------------------

    /**
     * Appel nominal avec orderId : lien admin inclus, aucune exception levée.
     */
    public function testSendRefundGaeKoToOwnerWithOrderIdDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendRefundGaeKoToOwner('CB-2026-0042', 125.50, 42);

        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // orderId = 0 — pas de lien admin
    // ----------------------------------------------------------------

    /**
     * orderId = 0 : pas de lien admin, aucune exception levée.
     */
    public function testSendRefundGaeKoToOwnerWithoutOrderIdDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendRefundGaeKoToOwner('CB-2026-0099', 75.00, 0);

        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // Fallback MAIL_USER
    // ----------------------------------------------------------------

    /**
     * CONTACT_OWNER_EMAIL absent → fallback sur MAIL_USER sans erreur.
     */
    public function testSendRefundGaeKoToOwnerFallsBackToMailUser(): void
    {
        unset($_ENV['CONTACT_OWNER_EMAIL']);

        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendRefundGaeKoToOwner('CB-2026-0099', 75.00);

        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // Montant zéro
    // ----------------------------------------------------------------

    /**
     * Montant à 0 : formatage sans erreur.
     */
    public function testSendRefundGaeKoToOwnerWithZeroAmountDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendRefundGaeKoToOwner('CB-2026-0000', 0.0);

        $this->addToAssertionCount(1);
    }
}
