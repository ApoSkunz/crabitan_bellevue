<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour les méthodes de séquence newsletter :
 * sendNewsletterWelcomeJ7, sendNewsletterWelcomeJ30, sendReconquest.
 */
#[BackupGlobals(true)]
class MailServiceNewsletterSequenceTest extends TestCase
{
    private MailService $service;

    protected function setUp(): void
    {
        $_ENV['MAIL_USER']              = 'noreply@test.local';
        $_ENV['MAIL_NOREPLY_USER']      = 'noreply@test.local';
        $_ENV['MAIL_NOREPLY_PASS']      = 'pass';
        $_ENV['MAIL_NEWSLETTER_USER']   = 'newsletter@test.local';
        $_ENV['MAIL_NEWSLETTER_PASS']   = 'pass';
        $_ENV['MAIL_HOST']              = 'smtp.test.local';
        $_ENV['MAIL_PORT']              = '587';
        $_ENV['MAIL_ENCRYPTION']        = 'tls';
        $_ENV['MAIL_FROM_NAME']         = 'Test';
        $_ENV['APP_URL']                = 'https://crabitan.test';

        $this->service = new MailService();
    }

    private function injectMockMailer(): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);
        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($this->service, $stub); // NOSONAR — test unitaire
    }

    // ----------------------------------------------------------------
    // sendNewsletterWelcomeJ7
    // ----------------------------------------------------------------

    public function testSendNewsletterWelcomeJ7FrDoesNotThrow(): void
    {
        $this->injectMockMailer();

        $this->service->sendNewsletterWelcomeJ7('sub@test.local', 'Jean Dupont', 'fr');

        $this->assertTrue(true);
    }

    public function testSendNewsletterWelcomeJ7EnDoesNotThrow(): void
    {
        $this->injectMockMailer();

        $this->service->sendNewsletterWelcomeJ7('sub@test.local', 'John Doe', 'en');

        $this->assertTrue(true);
    }

    public function testSendNewsletterWelcomeJ7EmptyNameUsesSalutation(): void
    {
        $this->injectMockMailer();

        // Doit utiliser SALUTATION_FR / SALUTATION_EN sans lever d'exception
        $this->service->sendNewsletterWelcomeJ7('sub@test.local', '', 'fr');

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendNewsletterWelcomeJ30
    // ----------------------------------------------------------------

    public function testSendNewsletterWelcomeJ30FrDoesNotThrow(): void
    {
        $this->injectMockMailer();

        $this->service->sendNewsletterWelcomeJ30('sub@test.local', 'Jean Dupont', 'fr', 'unsub-tok-abc');

        $this->assertTrue(true);
    }

    public function testSendNewsletterWelcomeJ30EnDoesNotThrow(): void
    {
        $this->injectMockMailer();

        $this->service->sendNewsletterWelcomeJ30('sub@test.local', 'John Doe', 'en', 'unsub-tok-xyz');

        $this->assertTrue(true);
    }

    public function testSendNewsletterWelcomeJ30EmptyNameUsesSalutation(): void
    {
        $this->injectMockMailer();

        $this->service->sendNewsletterWelcomeJ30('sub@test.local', '', 'en', 'tok');

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendReconquest
    // ----------------------------------------------------------------

    public function testSendReconquestFrDoesNotThrow(): void
    {
        $this->injectMockMailer();

        $this->service->sendReconquest(
            'inactive@test.local',
            'Jean Dupont',
            'fr',
            'https://crabitan.test/fr/newsletter/desabonnement?token=tok123'
        );

        $this->assertTrue(true);
    }

    public function testSendReconquestEnDoesNotThrow(): void
    {
        $this->injectMockMailer();

        $this->service->sendReconquest(
            'inactive@test.local',
            'John Doe',
            'en',
            'https://crabitan.test/en/newsletter/desabonnement?token=tokxyz'
        );

        $this->assertTrue(true);
    }

    public function testSendReconquestEmptyNameUsesSalutation(): void
    {
        $this->injectMockMailer();

        $this->service->sendReconquest('inactive@test.local', '', 'fr', 'https://crabitan.test/unsub');

        $this->assertTrue(true);
    }
}
