<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendDeletionReminder().
 *
 * Injecte un stub PHPMailer pour capturer subject/body sans SMTP.
 */
class MailServiceDeletionReminderTest extends TestCase
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

    /**
     * Injecte un PHPMailer stub et retourne l'instance pour lire Subject/Body.
     */
    private function injectStubMailer(): PHPMailer
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        $prop->setValue($this->service, $stub); // NOSONAR — test unitaire, accès privé délibéré

        return $stub;
    }

    /**
     * Appelle sendDeletionReminder et retourne [subject, body] du PHPMailer stub.
     *
     * @return array{subject: string, body: string}
     */
    private function capture(
        string $lang,
        int $daysAhead,
        string $deletionDate = '2026-05-10 00:00:00',
        string $token = 'tok123'
    ): array {
        $stub = $this->injectStubMailer();

        $this->service->sendDeletionReminder(
            'user@example.com',
            'Jean',
            $lang,
            $daysAhead,
            $deletionDate,
            $token
        );

        return [
            'subject' => $stub->Subject ?? '',
            'body'    => $stub->Body ?? '',
        ];
    }

    // ------------------------------------------------------------------
    // Objet de l'email
    // ------------------------------------------------------------------

    /**
     * L'objet indique "7 jours" pour un rappel J-7 en français.
     */
    public function testSubjectMentions7DaysFr(): void
    {
        $result = $this->capture('fr', 7);

        $this->assertStringContainsString('7 jours', $result['subject']);
    }

    /**
     * L'objet indique "tomorrow" pour un rappel J-1 en anglais.
     */
    public function testSubjectMentionsTomorrowEn(): void
    {
        $result = $this->capture('en', 1);

        $this->assertStringContainsString('tomorrow', $result['subject']);
    }

    /**
     * L'objet J-1 FR indique "demain".
     */
    public function testSubjectMentionsTomorrowFr(): void
    {
        $result = $this->capture('fr', 1);

        $this->assertStringContainsString('demain', $result['subject']);
    }

    // ------------------------------------------------------------------
    // Corps de l'email
    // ------------------------------------------------------------------

    /**
     * Le corps contient le lien de réactivation avec le token.
     */
    public function testBodyContainsReactivationLink(): void
    {
        $result = $this->capture('fr', 7, '2026-05-10 00:00:00', 'mytoken');

        $this->assertStringContainsString('mytoken', $result['body']);
        $this->assertStringContainsString('/compte/reactiver', $result['body']);
    }

    /**
     * Le corps J-1 contient un avertissement d'urgence en français.
     */
    public function testBodyContainsUrgencyNoteFor1DayFr(): void
    {
        $result = $this->capture('fr', 1);

        $this->assertStringContainsString('Dernier avertissement', $result['body']);
    }

    /**
     * Le corps J-1 contient un avertissement d'urgence en anglais.
     */
    public function testBodyContainsUrgencyNoteFor1DayEn(): void
    {
        $result = $this->capture('en', 1);

        $this->assertStringContainsString('Final warning', $result['body']);
    }

    /**
     * Le corps J-7 ne contient pas de mention d'urgence.
     */
    public function testBodyDoesNotContainUrgencyNoteFor7Days(): void
    {
        $result = $this->capture('fr', 7);

        $this->assertStringNotContainsString('Dernier avertissement', $result['body']);
    }

    /**
     * Le corps contient la date d'anonymisation programmée.
     */
    public function testBodyContainsScheduledDeletionDate(): void
    {
        $result = $this->capture('fr', 7, '2026-05-10 00:00:00');

        $this->assertStringContainsString('2026-05-10', $result['body']);
    }
}
