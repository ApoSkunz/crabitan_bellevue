<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Mail;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Service\Mail\ContactMailer;
use Service\MailService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires de ContactMailer — vérifie la délégation vers MailService.
 */
#[AllowMockObjectsWithoutExpectations]
class ContactMailerTest extends TestCase
{
    private MailService&MockObject $mailServiceMock;
    private ContactMailer $mailer;

    protected function setUp(): void
    {
        $this->mailServiceMock = $this->createMock(MailService::class);
        $this->mailer          = new ContactMailer($this->mailServiceMock);
    }

    /**
     * sendContactToOwner délègue correctement à MailService.
     */
    public function testSendContactToOwnerDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendContactToOwner')
            ->with('Jean', 'Dupont', 'jean@example.com', 'order', 'Mon message', 'fr');

        $this->mailer->sendContactToOwner('Jean', 'Dupont', 'jean@example.com', 'order', 'Mon message', 'fr');
    }

    /**
     * sendContactConfirmation délègue correctement à MailService avec valeur par défaut.
     */
    public function testSendContactConfirmationDelegatesWithDefault(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendContactConfirmation')
            ->with('jean@example.com', 'Jean', 'order', 'fr', '');

        $this->mailer->sendContactConfirmation('jean@example.com', 'Jean', 'order', 'fr');
    }

    /**
     * sendContactConfirmation transmet le message utilisateur quand fourni.
     */
    public function testSendContactConfirmationDelegatesWithUserMessage(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendContactConfirmation')
            ->with('jean@example.com', 'Jean', 'delivery', 'en', 'Mon colis est perdu');

        $this->mailer->sendContactConfirmation('jean@example.com', 'Jean', 'delivery', 'en', 'Mon colis est perdu');
    }
}
