<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires de sendAccountLocked et de ses corps d'email privés.
 */
class MailServiceAccountLockedTest extends TestCase
{
    private MailService $service;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->service    = new MailService();
        $this->reflection = new ReflectionClass(MailService::class);
    }

    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    private function callPrivate(string $method, mixed ...$args): string
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        return $m->invoke($this->service, ...$args);
    }

    // ----------------------------------------------------------------
    // accountLockedBodyFr
    // ----------------------------------------------------------------

    public function testBodyFrContainsName(): void
    {
        $body = $this->callPrivate('accountLockedBodyFr', 'Alice', 'http://reset');
        $this->assertStringContainsString('Alice', $body);
    }

    public function testBodyFrContainsResetUrl(): void
    {
        $body = $this->callPrivate('accountLockedBodyFr', 'Alice', 'http://reset');
        $this->assertStringContainsString('http://reset', $body);
    }

    public function testBodyFrMentionsFailedAttempts(): void
    {
        $body = $this->callPrivate('accountLockedBodyFr', 'Alice', 'http://reset');
        $this->assertStringContainsStringIgnoringCase('tentative', $body);
    }

    public function testBodyFrContainsResetButton(): void
    {
        $body = $this->callPrivate('accountLockedBodyFr', 'Alice', 'http://reset');
        $this->assertStringContainsString('mot de passe', strtolower($body));
    }

    public function testBodyFrEscapesName(): void
    {
        $body = $this->callPrivate('accountLockedBodyFr', '<script>xss</script>', 'http://reset');
        $this->assertStringNotContainsString('<script>', $body);
    }

    public function testBodyFrEscapesResetUrl(): void
    {
        $body = $this->callPrivate('accountLockedBodyFr', 'Alice', 'http://reset?a=1&b=2');
        $this->assertStringNotContainsString('"http://reset?a=1&b=2"', $body);
    }

    // ----------------------------------------------------------------
    // accountLockedBodyEn
    // ----------------------------------------------------------------

    public function testBodyEnContainsName(): void
    {
        $body = $this->callPrivate('accountLockedBodyEn', 'Bob', 'http://reset');
        $this->assertStringContainsString('Bob', $body);
    }

    public function testBodyEnContainsResetUrl(): void
    {
        $body = $this->callPrivate('accountLockedBodyEn', 'Bob', 'http://reset');
        $this->assertStringContainsString('http://reset', $body);
    }

    public function testBodyEnMentionsFailedAttempts(): void
    {
        $body = $this->callPrivate('accountLockedBodyEn', 'Bob', 'http://reset');
        $this->assertStringContainsStringIgnoringCase('attempt', $body);
    }

    public function testBodyEnContainsResetButton(): void
    {
        $body = $this->callPrivate('accountLockedBodyEn', 'Bob', 'http://reset');
        $this->assertStringContainsStringIgnoringCase('password', $body);
    }

    public function testBodyEnEscapesName(): void
    {
        $body = $this->callPrivate('accountLockedBodyEn', '<b>hack</b>', 'http://reset');
        $this->assertStringNotContainsString('<b>hack</b>', $body);
    }

    // ----------------------------------------------------------------
    // sendAccountLocked (méthode publique)
    // ----------------------------------------------------------------

    public function testSendFrDoesNotThrow(): void
    {
        $this->injectMockMailer($this->service);
        $this->service->sendAccountLocked(
            'user@example.com',
            'Alice',
            'fr',
            'http://localhost/fr/mot-de-passe-oublie'
        );
        $this->assertTrue(true);
    }

    public function testSendEnDoesNotThrow(): void
    {
        $this->injectMockMailer($this->service);
        $this->service->sendAccountLocked(
            'user@example.com',
            'Bob',
            'en',
            'http://localhost/en/mot-de-passe-oublie'
        );
        $this->assertTrue(true);
    }
}
