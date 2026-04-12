<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Mail;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Service\Mail\NewsletterMailer;
use Service\MailService;

/**
 * Tests unitaires de NewsletterMailer — vérifie la délégation vers MailService.
 */
class NewsletterMailerTest extends TestCase
{
    private MailService&MockObject $mailServiceMock;
    private NewsletterMailer $mailer;

    protected function setUp(): void
    {
        $this->mailServiceMock = $this->createMock(MailService::class);
        $this->mailer          = new NewsletterMailer($this->mailServiceMock);
    }

    /**
     * sendNewsletter délègue correctement à MailService avec valeurs par défaut.
     */
    public function testSendNewsletterDelegatesWithDefaults(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendNewsletter')
            ->with('sub@example.com', 'Alice', 'Nos actualités', '<p>Corps</p>', null, null, null);

        $this->mailer->sendNewsletter('sub@example.com', 'Alice', 'Nos actualités', '<p>Corps</p>');
    }

    /**
     * sendNewsletter transmet les paramètres optionnels quand fournis.
     */
    public function testSendNewsletterDelegatesWithAllParams(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendNewsletter')
            ->with('sub@example.com', 'Bob', 'Sujet', '<p>html</p>', '/tmp/att.pdf', 'pièce.pdf', 'unsub-tok');

        $this->mailer->sendNewsletter(
            'sub@example.com',
            'Bob',
            'Sujet',
            '<p>html</p>',
            '/tmp/att.pdf',
            'pièce.pdf',
            'unsub-tok'
        );
    }

    /**
     * sendNewWineNewsletter délègue correctement à MailService.
     */
    public function testSendNewWineNewsletterDelegates(): void
    {
        $wine = ['id' => 1, 'name_fr' => 'Château Margaux'];

        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendNewWineNewsletter')
            ->with('sub@example.com', 'Alice', 'unsub-tok', $wine, 'https://app.fr', 'fr');

        $this->mailer->sendNewWineNewsletter('sub@example.com', 'Alice', 'unsub-tok', $wine, 'https://app.fr', 'fr');
    }

    /**
     * sendNewsletterConfirmation délègue correctement à MailService.
     */
    public function testSendNewsletterConfirmationDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendNewsletterConfirmation')
            ->with('sub@example.com', 'https://app.fr/confirm', 'fr');

        $this->mailer->sendNewsletterConfirmation('sub@example.com', 'https://app.fr/confirm', 'fr');
    }

    /**
     * sendNewsletterWelcome délègue correctement à MailService.
     */
    public function testSendNewsletterWelcomeDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendNewsletterWelcome')
            ->with('sub@example.com', 'Alice', 'en');

        $this->mailer->sendNewsletterWelcome('sub@example.com', 'Alice', 'en');
    }
}
