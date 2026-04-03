<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires MailService — sendGoogleAccountInfo() et les corps d'email associés.
 */
class MailServiceGoogleOAuthTest extends TestCase
{
    private MailService $service;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $_ENV['APP_URL']        = 'http://localhost';
        $_ENV['MAIL_FROM']      = 'noreply@example.com';
        $_ENV['MAIL_FROM_NAME'] = 'Crabitan Bellevue';

        $this->service    = new MailService();
        $this->reflection = new ReflectionClass(MailService::class);
    }

    /**
     * Injecte un stub PHPMailer pour éviter tout envoi SMTP réel.
     */
    private function injectMockMailer(): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        $prop->setValue($this->service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    private function callPrivate(string $method, mixed ...$args): string
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        return (string) $m->invoke($this->service, ...$args);
    }

    // ----------------------------------------------------------------
    // sendGoogleAccountInfo()
    // ----------------------------------------------------------------

    public function testSendGoogleAccountInfoFrDoesNotThrow(): void
    {
        $this->injectMockMailer();
        $this->expectNotToPerformAssertions();
        $this->service->sendGoogleAccountInfo('test@example.com', 'Jean Dupont', 'fr');
    }

    public function testSendGoogleAccountInfoEnDoesNotThrow(): void
    {
        $this->injectMockMailer();
        $this->expectNotToPerformAssertions();
        $this->service->sendGoogleAccountInfo('test@example.com', 'John Doe', 'en');
    }

    // ----------------------------------------------------------------
    // googleAccountInfoBodyFr()
    // ----------------------------------------------------------------

    public function testGoogleAccountInfoBodyFrContainsName(): void
    {
        $html = $this->callPrivate('googleAccountInfoBodyFr', 'Marie Curie');
        $this->assertStringContainsString('Marie Curie', $html);
    }

    public function testGoogleAccountInfoBodyFrContainsGoogleMention(): void
    {
        $html = $this->callPrivate('googleAccountInfoBodyFr', 'Jean');
        $this->assertStringContainsString('Google', $html);
    }

    public function testGoogleAccountInfoBodyFrEscapesHtml(): void
    {
        $html = $this->callPrivate('googleAccountInfoBodyFr', '<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $html);
    }

    // ----------------------------------------------------------------
    // googleAccountInfoBodyEn()
    // ----------------------------------------------------------------

    public function testGoogleAccountInfoBodyEnContainsName(): void
    {
        $html = $this->callPrivate('googleAccountInfoBodyEn', 'John Doe');
        $this->assertStringContainsString('John Doe', $html);
    }

    public function testGoogleAccountInfoBodyEnContainsGoogleMention(): void
    {
        $html = $this->callPrivate('googleAccountInfoBodyEn', 'John');
        $this->assertStringContainsString('Google', $html);
    }

    public function testGoogleAccountInfoBodyEnEscapesHtml(): void
    {
        $html = $this->callPrivate('googleAccountInfoBodyEn', '<script>xss</script>');
        $this->assertStringNotContainsString('<script>', $html);
    }
}
