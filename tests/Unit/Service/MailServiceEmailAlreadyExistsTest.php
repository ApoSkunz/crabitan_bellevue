<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires de sendEmailAlreadyExists et de ses corps d'email privés.
 */
class MailServiceEmailAlreadyExistsTest extends TestCase
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
    // emailAlreadyExistsBodyFr
    // ----------------------------------------------------------------

    public function testBodyFrContainsName(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyFr', 'Alice', 'http://login', 'http://reset');
        $this->assertStringContainsString('Alice', $body);
    }

    public function testBodyFrContainsLoginUrl(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyFr', 'Alice', 'http://login', 'http://reset');
        $this->assertStringContainsString('http://login', $body);
    }

    public function testBodyFrContainsResetUrl(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyFr', 'Alice', 'http://login', 'http://reset');
        $this->assertStringContainsString('http://reset', $body);
    }

    public function testBodyFrContainsConnectButton(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyFr', 'Alice', 'http://login', 'http://reset');
        $this->assertStringContainsString('Me connecter', $body);
    }

    public function testBodyFrContainsForgotLink(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyFr', 'Alice', 'http://login', 'http://reset');
        $this->assertStringContainsString('Mot de passe oubli', $body);
    }

    public function testBodyFrEscapesName(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyFr', '<script>', 'http://login', 'http://reset');
        $this->assertStringNotContainsString('<script>', $body);
    }

    // ----------------------------------------------------------------
    // emailAlreadyExistsBodyEn
    // ----------------------------------------------------------------

    public function testBodyEnContainsName(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyEn', 'Bob', 'http://login', 'http://reset');
        $this->assertStringContainsString('Bob', $body);
    }

    public function testBodyEnContainsLoginUrl(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyEn', 'Bob', 'http://login', 'http://reset');
        $this->assertStringContainsString('http://login', $body);
    }

    public function testBodyEnContainsResetUrl(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyEn', 'Bob', 'http://login', 'http://reset');
        $this->assertStringContainsString('http://reset', $body);
    }

    public function testBodyEnContainsLogInButton(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyEn', 'Bob', 'http://login', 'http://reset');
        $this->assertStringContainsString('Log in', $body);
    }

    public function testBodyEnContainsForgotLink(): void
    {
        $body = $this->callPrivate('emailAlreadyExistsBodyEn', 'Bob', 'http://login', 'http://reset');
        $this->assertStringContainsString('Forgot', $body);
    }

    // ----------------------------------------------------------------
    // sendEmailAlreadyExists (méthode publique)
    // ----------------------------------------------------------------

    public function testSendFrDoesNotThrow(): void
    {
        $this->injectMockMailer($this->service);
        $this->service->sendEmailAlreadyExists(
            'user@example.com',
            'Alice',
            'fr',
            'http://localhost/fr?login=1',
            'http://localhost/fr/mot-de-passe-oublie'
        );
        $this->assertTrue(true);
    }

    public function testSendEnDoesNotThrow(): void
    {
        $this->injectMockMailer($this->service);
        $this->service->sendEmailAlreadyExists(
            'user@example.com',
            'Bob',
            'en',
            'http://localhost/en?login=1',
            'http://localhost/en/mot-de-passe-oublie'
        );
        $this->assertTrue(true);
    }
}
