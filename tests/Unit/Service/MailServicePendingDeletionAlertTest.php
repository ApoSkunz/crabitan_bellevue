<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendPendingDeletionLoginAlert().
 *
 * Couvre les deux branches linguistiques (FR/EN) et la présence du lien
 * de réactivation dans le corps de l'email.
 */
class MailServicePendingDeletionAlertTest extends TestCase
{
    private MailService $service;

    protected function setUp(): void
    {
        $_ENV['APP_URL']   = 'http://crabitan.local';
        $_ENV['MAIL_FROM'] = 'noreply@crabitan.local';
        $_ENV['MAIL_HOST'] = 'localhost';
        $_ENV['MAIL_PORT'] = '1025';
        $_ENV['MAIL_USER'] = '';
        $_ENV['MAIL_PASS'] = '';

        $this->service = new MailService();
    }

    private function injectStubMailer(): PHPMailer
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($this->service, $stub); // NOSONAR — test unitaire, accès privé délibéré

        return $stub;
    }

    /**
     * Appelle sendPendingDeletionLoginAlert et retourne [subject, body].
     *
     * @return array{subject: string, body: string}
     */
    private function capture(
        string $lang,
        string $token = 'react-token-123',
        string $deletionDate = '2026-05-10 00:00:00'
    ): array {
        $stub = $this->injectStubMailer();

        $this->service->sendPendingDeletionLoginAlert(
            'user@example.com',
            'Jean',
            $lang,
            $token,
            $deletionDate
        );

        return [
            'subject' => $stub->Subject ?? '',
            'body'    => $stub->Body ?? '',
        ];
    }

    // ----------------------------------------------------------------
    // FR
    // ----------------------------------------------------------------

    /**
     * L'objet FR mentionne "connexion" et "suppression".
     */
    public function testSubjectFrMentionsLoginAndDeletion(): void
    {
        $result = $this->capture('fr');

        $this->assertStringContainsString('connexion', strtolower($result['subject']));
        $this->assertStringContainsString('suppression', strtolower($result['subject']));
    }

    /**
     * Le corps FR contient le lien de réactivation avec le token encodé.
     */
    public function testBodyFrContainsReactivationLink(): void
    {
        $result = $this->capture('fr', 'my-token');

        $this->assertStringContainsString('/compte/reactiver?token=my-token', $result['body']);
        $this->assertStringContainsString('Annuler la suppression', $result['body']);
    }

    /**
     * Le corps FR contient la date de suppression programmée.
     */
    public function testBodyFrContainsDeletionDate(): void
    {
        $result = $this->capture('fr', 'tok', '2026-06-15 12:00:00');

        $this->assertStringContainsString('2026-06-15', $result['body']);
    }

    // ----------------------------------------------------------------
    // EN
    // ----------------------------------------------------------------

    /**
     * L'objet EN mentionne "login" et "deletion".
     */
    public function testSubjectEnMentionsLoginAndDeletion(): void
    {
        $result = $this->capture('en');

        $this->assertStringContainsString('login', strtolower($result['subject']));
        $this->assertStringContainsString('deletion', strtolower($result['subject']));
    }

    /**
     * Le corps EN contient le lien de réactivation et "Cancel deletion".
     */
    public function testBodyEnContainsReactivationLink(): void
    {
        $result = $this->capture('en', 'my-token');

        $this->assertStringContainsString('/compte/reactiver?token=my-token', $result['body']);
        $this->assertStringContainsString('Cancel deletion', $result['body']);
    }

    /**
     * Le corps EN contient la date de suppression programmée.
     */
    public function testBodyEnContainsDeletionDate(): void
    {
        $result = $this->capture('en', 'tok', '2026-06-15 12:00:00');

        $this->assertStringContainsString('2026-06-15', $result['body']);
    }
}
