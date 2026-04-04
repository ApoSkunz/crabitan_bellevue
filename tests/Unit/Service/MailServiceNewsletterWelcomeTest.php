<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendNewsletterWelcome().
 *
 * Vérifie que l'email de bienvenue newsletter ne lève pas d'exception
 * pour les combinaisons de langue et de nom.
 */
class MailServiceNewsletterWelcomeTest extends TestCase
{
    /**
     * Injecte un mock PHPMailer dont send() ne lance pas d'exception,
     * permettant de couvrir les lignes de construction du corps sans SMTP réel.
     *
     * @param MailService $service Instance à patcher
     * @return void
     */
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    // ----------------------------------------------------------------
    // sendNewsletterWelcome — lang FR
    // ----------------------------------------------------------------

    public function testSendNewsletterWelcomeFrDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendNewsletterWelcome('test@test.com', 'Jean Dupont', 'fr');

        $this->assertTrue(true); // Pas d'exception levée
    }

    // ----------------------------------------------------------------
    // sendNewsletterWelcome — lang EN
    // ----------------------------------------------------------------

    public function testSendNewsletterWelcomeEnDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendNewsletterWelcome('test@test.com', 'John Doe', 'en');

        $this->assertTrue(true); // Pas d'exception levée
    }

    // ----------------------------------------------------------------
    // sendNewsletterWelcome — nom vide (fallback salutation)
    // ----------------------------------------------------------------

    public function testSendNewsletterWelcomeEmptyNameDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendNewsletterWelcome('test@test.com', '', 'fr');

        $this->assertTrue(true); // Pas d'exception levée
    }

    public function testSendNewsletterWelcomeEmptyNameEnDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendNewsletterWelcome('test@test.com', '', 'en');

        $this->assertTrue(true); // Pas d'exception levée
    }
}
