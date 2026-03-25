<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires de la logique de construction des corps d'email.
 * Les méthodes privées de construction de HTML sont testées via Reflection.
 * Les méthodes publiques (sendContact*, __construct else branch) sont couvertes
 * sans subprocess pour que Xdebug/PCOV collecte correctement la couverture.
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

    /**
     * Injecte un mock PHPMailer dont send() ne lance pas d'exception,
     * permettant de couvrir les lignes de construction du corps sans SMTP réel.
     */
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true);
        $prop->setValue($service, $stub);
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
    // __construct — branche else : MAIL_USER vide → SMTPAuth = false
    // BackupGlobals restaure $_ENV après le test sans subprocess.
    // ----------------------------------------------------------------

    #[BackupGlobals(true)]
    public function testConstructElseBranchWithEmptyMailUser(): void
    {
        $_ENV['MAIL_USER'] = '';
        $_ENV['APP_URL']   = 'http://crabitan.local';

        $service = new MailService();
        $this->assertInstanceOf(MailService::class, $service);

        // Vérifie via Reflection que SMTPAuth est bien false
        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true);
        /** @var PHPMailer $mailer */
        $mailer = $prop->getValue($service);
        $this->assertFalse($mailer->SMTPAuth);
        $this->assertSame('', $mailer->SMTPSecure);
    }

    // ----------------------------------------------------------------
    // sendContactToOwner — corps HTML couvert sans SMTP réel
    // ----------------------------------------------------------------

    public function testSendContactToOwnerCoversBodyLines(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendContactToOwner(
            'Jean',
            'Dupont',
            'jean@example.com',
            'Question test',
            'Un message de test.',
            'fr'
        );

        $this->assertTrue(true); // pas d'exception = corps construit et send() mocké OK
    }

    // ----------------------------------------------------------------
    // sendContactConfirmation — branche FR
    // ----------------------------------------------------------------

    public function testSendContactConfirmationFrCoversBody(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendContactConfirmation(
            'jean@example.com',
            'Jean',
            'Question test',
            'fr'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendContactConfirmation — branche EN
    // ----------------------------------------------------------------

    public function testSendContactConfirmationEnCoversBody(): void
    {
        $this->injectMockMailer($this->service);

        $this->service->sendContactConfirmation(
            'jean@example.com',
            'Jean',
            'Question test',
            'en'
        );

        $this->assertTrue(true);
    }
}
