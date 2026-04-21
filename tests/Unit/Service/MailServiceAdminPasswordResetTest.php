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
 * Tests unitaires de sendAdminPasswordReset, resetAdminBodyFr, resetAdminBodyEn.
 */
#[BackupGlobals(true)]
class MailServiceAdminPasswordResetTest extends TestCase
{
    private MailService $service;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $_ENV['MAIL_USER']           = 'noreply@test.local';
        $_ENV['MAIL_SECURITY_USER']  = 'security@test.local';
        $_ENV['MAIL_SECURITY_PASS']  = 'pass';
        $_ENV['MAIL_HOST']           = 'smtp.test.local';
        $_ENV['MAIL_PORT']           = '587';
        $_ENV['MAIL_ENCRYPTION']     = 'tls';
        $_ENV['MAIL_FROM_NAME']      = 'Test';
        $_ENV['APP_URL']             = 'https://crabitan.test';

        $this->service    = new MailService();
        $this->reflection = new ReflectionClass(MailService::class);
    }

    private function injectMockMailer(): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);
        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($this->service, $stub); // NOSONAR — test unitaire
    }

    private function callPrivate(string $method, mixed ...$args): string
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true);
        return (string) $m->invoke($this->service, ...$args);
    }

    // ----------------------------------------------------------------
    // resetAdminBodyFr
    // ----------------------------------------------------------------

    /**
     * Le corps FR contient "auprès de l'équipe d'administration".
     */
    public function testResetAdminBodyFrContainsAdminWording(): void
    {
        $body = $this->callPrivate('resetAdminBodyFr', 'Jean', 'https://crabitan.test/fr/reinitialisation/abc');

        $this->assertStringContainsString("auprès de l'équipe d'administration", $body);
    }

    /**
     * Le corps FR contient le lien de réinitialisation.
     */
    public function testResetAdminBodyFrContainsResetUrl(): void
    {
        $body = $this->callPrivate('resetAdminBodyFr', 'Jean', 'https://crabitan.test/fr/reinitialisation/abc123');

        $this->assertStringContainsString('abc123', $body);
    }

    /**
     * Le corps FR échappe les caractères spéciaux dans le nom.
     */
    public function testResetAdminBodyFrEscapesName(): void
    {
        $body = $this->callPrivate('resetAdminBodyFr', '<script>', 'https://crabitan.test/reset');

        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    // ----------------------------------------------------------------
    // resetAdminBodyEn
    // ----------------------------------------------------------------

    /**
     * Le corps EN contient "administration team".
     */
    public function testResetAdminBodyEnContainsAdminWording(): void
    {
        $body = $this->callPrivate('resetAdminBodyEn', 'John', 'https://crabitan.test/en/reinitialisation/xyz');

        $this->assertStringContainsString('administration team', $body);
    }

    /**
     * Le corps EN contient le lien de réinitialisation.
     */
    public function testResetAdminBodyEnContainsResetUrl(): void
    {
        $body = $this->callPrivate('resetAdminBodyEn', 'John', 'https://crabitan.test/en/reinitialisation/xyz789');

        $this->assertStringContainsString('xyz789', $body);
    }

    // ----------------------------------------------------------------
    // sendAdminPasswordReset — canal SECURITY
    // ----------------------------------------------------------------

    /**
     * sendAdminPasswordReset utilise le canal SECURITY (security@).
     */
    public function testSendAdminPasswordResetUsesSECURITYChannel(): void
    {
        $this->injectMockMailer();

        $this->service->sendAdminPasswordReset(
            'user@test.local',
            'Jean Dupont',
            'https://crabitan.test/fr/reinitialisation/tok123',
            'fr'
        );

        $prop = $this->reflection->getProperty('mailer');
        $prop->setAccessible(true);
        /** @var PHPMailer $mailer */
        $mailer = $prop->getValue($this->service);

        $this->assertSame('security@test.local', $mailer->Username);
    }

    /**
     * sendAdminPasswordReset EN utilise le sujet anglais.
     */
    public function testSendAdminPasswordResetEnUsesEnglishSubject(): void
    {
        $this->injectMockMailer();

        $this->service->sendAdminPasswordReset(
            'user@test.local',
            'John',
            'https://crabitan.test/en/reinitialisation/tok456',
            'en'
        );

        $prop = $this->reflection->getProperty('mailer');
        $prop->setAccessible(true);
        /** @var PHPMailer $mailer */
        $mailer = $prop->getValue($this->service);

        $this->assertStringContainsString('administrator', (string) $mailer->Subject);
    }
}
