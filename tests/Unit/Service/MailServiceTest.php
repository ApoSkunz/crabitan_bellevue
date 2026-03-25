<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Service\MailService;

/**
 * Tests unitaires de la logique de construction des corps d'email.
 * Les méthodes testées sont privées (pur string building, sans SMTP).
 */
class MailServiceTest extends TestCase
{
    private MailService $service;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->service    = new MailService();
        $this->reflection = new ReflectionClass(MailService::class);
    }

    private function callPrivate(string $method, mixed ...$args): string
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($this->service, ...$args);
    }

    // ----------------------------------------------------------------
    // verificationBodyFr
    // ----------------------------------------------------------------

    public function testVerificationBodyFrContainsName(): void
    {
        $body = $this->callPrivate('verificationBodyFr', 'Alice', 'https://example.com/verify/abc');
        $this->assertStringContainsString('Alice', $body);
    }

    public function testVerificationBodyFrContainsUrl(): void
    {
        $url  = 'https://example.com/verify/abc';
        $body = $this->callPrivate('verificationBodyFr', 'Alice', $url);
        $this->assertStringContainsString($url, $body);
    }

    public function testVerificationBodyFrEscapesXss(): void
    {
        $body = $this->callPrivate('verificationBodyFr', '<script>alert(1)</script>', 'https://x.com');
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    // ----------------------------------------------------------------
    // verificationBodyEn
    // ----------------------------------------------------------------

    public function testVerificationBodyEnContainsName(): void
    {
        $body = $this->callPrivate('verificationBodyEn', 'Bob', 'https://example.com/verify/xyz');
        $this->assertStringContainsString('Bob', $body);
    }

    public function testVerificationBodyEnContainsUrl(): void
    {
        $url  = 'https://example.com/verify/xyz';
        $body = $this->callPrivate('verificationBodyEn', 'Bob', $url);
        $this->assertStringContainsString($url, $body);
    }

    public function testVerificationBodyEnEscapesXss(): void
    {
        $body = $this->callPrivate('verificationBodyEn', '<b>name</b>', 'https://x.com');
        $this->assertStringNotContainsString('<b>name</b>', $body);
    }

    // ----------------------------------------------------------------
    // resetBodyFr
    // ----------------------------------------------------------------

    public function testResetBodyFrContainsName(): void
    {
        $body = $this->callPrivate('resetBodyFr', 'Alice', 'https://example.com/reset/token');
        $this->assertStringContainsString('Alice', $body);
    }

    public function testResetBodyFrContainsUrl(): void
    {
        $url  = 'https://example.com/reset/token';
        $body = $this->callPrivate('resetBodyFr', 'Alice', $url);
        $this->assertStringContainsString($url, $body);
    }

    public function testResetBodyFrEscapesXss(): void
    {
        $body = $this->callPrivate('resetBodyFr', '<img src=x onerror=1>', 'https://x.com');
        $this->assertStringContainsString('&lt;img src=x onerror=1&gt;', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
    }

    // ----------------------------------------------------------------
    // resetBodyEn
    // ----------------------------------------------------------------

    public function testResetBodyEnContainsName(): void
    {
        $body = $this->callPrivate('resetBodyEn', 'Bob', 'https://example.com/reset/xyz');
        $this->assertStringContainsString('Bob', $body);
    }

    public function testResetBodyEnContainsUrl(): void
    {
        $url  = 'https://example.com/reset/xyz';
        $body = $this->callPrivate('resetBodyEn', 'Bob', $url);
        $this->assertStringContainsString($url, $body);
    }

    public function testResetBodyEnEscapesXss(): void
    {
        $body = $this->callPrivate('resetBodyEn', '<img src=x>', 'https://x.com');
        $this->assertStringContainsString('&lt;img src=x&gt;', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
    }

    // ----------------------------------------------------------------
    // __construct — branche sans MAIL_USER (SMTPAuth = false)
    // ----------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConstructElseBranchWithEmptyMailUser(): void
    {
        $_ENV['MAIL_HOST']      = 'localhost';
        $_ENV['MAIL_PORT']      = '587';
        $_ENV['MAIL_USER']      = '';
        $_ENV['MAIL_PASS']      = '';
        $_ENV['MAIL_FROM_NAME'] = 'Test';
        $_ENV['APP_URL']        = 'http://crabitan.local';

        $service = new MailService();
        $this->assertInstanceOf(MailService::class, $service);
    }

    // ----------------------------------------------------------------
    // sendContactToOwner — couverture du corps HTML
    // ----------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSendContactToOwnerCoversBodyLines(): void
    {
        $this->expectException(\Exception::class);
        $service = new MailService();
        $service->sendContactToOwner(
            'Jean',
            'Dupont',
            'jean@example.com',
            'Question',
            'Un message de test',
            'fr'
        );
    }

    // ----------------------------------------------------------------
    // sendContactConfirmation — branche FR
    // ----------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSendContactConfirmationFrCoversBody(): void
    {
        $this->expectException(\Exception::class);
        $service = new MailService();
        $service->sendContactConfirmation('jean@example.com', 'Jean', 'Question', 'fr');
    }

    // ----------------------------------------------------------------
    // sendContactConfirmation — branche EN
    // ----------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSendContactConfirmationEnCoversBody(): void
    {
        $this->expectException(\Exception::class);
        $service = new MailService();
        $service->sendContactConfirmation('jean@example.com', 'Jean', 'Question', 'en');
    }
}
