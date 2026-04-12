<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Mail;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Service\Mail\AccountMailer;
use Service\MailService;

/**
 * Tests unitaires de AccountMailer — vérifie la délégation vers MailService.
 */
class AccountMailerTest extends TestCase
{
    private MailService&MockObject $mailServiceMock;
    private AccountMailer $mailer;

    protected function setUp(): void
    {
        $this->mailServiceMock = $this->createMock(MailService::class);
        $this->mailer          = new AccountMailer($this->mailServiceMock);
    }

    /**
     * sendGoogleAccountInfo délègue correctement à MailService.
     */
    public function testSendGoogleAccountInfoDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendGoogleAccountInfo')
            ->with('user@gmail.com', 'Alice', 'fr');

        $this->mailer->sendGoogleAccountInfo('user@gmail.com', 'Alice', 'fr');
    }

    /**
     * sendWelcome délègue correctement à MailService avec valeur par défaut.
     */
    public function testSendWelcomeDelegatesWithDefault(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendWelcome')
            ->with('user@example.com', 'Alice', 'fr', false);

        $this->mailer->sendWelcome('user@example.com', 'Alice', 'fr');
    }

    /**
     * sendWelcome transmet isSubscribed=true quand fourni.
     */
    public function testSendWelcomeDelegatesWithSubscribed(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendWelcome')
            ->with('user@example.com', 'Bob', 'en', true);

        $this->mailer->sendWelcome('user@example.com', 'Bob', 'en', true);
    }

    /**
     * sendAccountDeletionConfirmation délègue correctement à MailService.
     */
    public function testSendAccountDeletionConfirmationDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendAccountDeletionConfirmation')
            ->with('user@example.com', 'Alice', 'fr', 'tok123');

        $this->mailer->sendAccountDeletionConfirmation('user@example.com', 'Alice', 'fr', 'tok123');
    }

    /**
     * sendDeletionReminder délègue correctement à MailService.
     */
    public function testSendDeletionReminderDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendDeletionReminder')
            ->with('user@example.com', 'Alice', 'fr', 7, '2026-05-01 00:00:00', 'tok-reactivate');

        $this->mailer->sendDeletionReminder('user@example.com', 'Alice', 'fr', 7, '2026-05-01 00:00:00', 'tok-reactivate');
    }

    /**
     * sendInactivityWarning délègue correctement à MailService.
     */
    public function testSendInactivityWarningDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendInactivityWarning')
            ->with('user@example.com', 'Alice', 'fr', '2026-06-01');

        $this->mailer->sendInactivityWarning('user@example.com', 'Alice', 'fr', '2026-06-01');
    }

    /**
     * sendInactivityDeletion délègue correctement à MailService.
     */
    public function testSendInactivityDeletionDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendInactivityDeletion')
            ->with('user@example.com', 'Alice', 'fr', '2026-05-01', 'tok-reactivate');

        $this->mailer->sendInactivityDeletion('user@example.com', 'Alice', 'fr', '2026-05-01', 'tok-reactivate');
    }

    /**
     * sendFinalDeletion délègue correctement à MailService.
     */
    public function testSendFinalDeletionDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendFinalDeletion')
            ->with('user@example.com', 'Alice', 'en');

        $this->mailer->sendFinalDeletion('user@example.com', 'Alice', 'en');
    }

    /**
     * sendPasswordChangeConfirmation délègue correctement à MailService.
     */
    public function testSendPasswordChangeConfirmationDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendPasswordChangeConfirmation')
            ->with('user@example.com', 'Alice', 'fr', 'confirm-tok');

        $this->mailer->sendPasswordChangeConfirmation('user@example.com', 'Alice', 'fr', 'confirm-tok');
    }

    /**
     * sendPasswordChangeDenied délègue correctement à MailService.
     */
    public function testSendPasswordChangeDeniedDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendPasswordChangeDenied')
            ->with('user@example.com', 'fr');

        $this->mailer->sendPasswordChangeDenied('user@example.com', 'fr');
    }

    /**
     * sendNewDeviceAlert délègue correctement à MailService.
     */
    public function testSendNewDeviceAlertDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendNewDeviceAlert')
            ->with('user@example.com', 'Alice', 'Chrome / Windows', '1.2.3.4', 'fr', 'device-tok');

        $this->mailer->sendNewDeviceAlert('user@example.com', 'Alice', 'Chrome / Windows', '1.2.3.4', 'fr', 'device-tok');
    }

    /**
     * sendNewDeviceAlert transmet ipAddress null si non fourni.
     */
    public function testSendNewDeviceAlertWithNullIp(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendNewDeviceAlert')
            ->with('user@example.com', 'Alice', 'Firefox', null, 'en', '');

        $this->mailer->sendNewDeviceAlert('user@example.com', 'Alice', 'Firefox', null, 'en');
    }

    /**
     * sendEmailChangeConfirmation délègue correctement à MailService.
     */
    public function testSendEmailChangeConfirmationDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendEmailChangeConfirmation')
            ->with('old@example.com', 'Alice', 'https://app.fr/confirm', 'fr', 'new@example.com', 'https://app.fr/revoke');

        $this->mailer->sendEmailChangeConfirmation(
            'old@example.com',
            'Alice',
            'https://app.fr/confirm',
            'fr',
            'new@example.com',
            'https://app.fr/revoke'
        );
    }

    /**
     * sendEmailChangeNotification délègue correctement à MailService.
     */
    public function testSendEmailChangeNotificationDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendEmailChangeNotification')
            ->with('old@example.com', 'fr');

        $this->mailer->sendEmailChangeNotification('old@example.com', 'fr');
    }

    /**
     * sendPendingDeletionLoginAlert délègue correctement à MailService.
     */
    public function testSendPendingDeletionLoginAlertDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendPendingDeletionLoginAlert')
            ->with('user@example.com', 'Alice', 'fr', 'tok-reactivate', '2026-05-01 00:00:00');

        $this->mailer->sendPendingDeletionLoginAlert(
            'user@example.com',
            'Alice',
            'fr',
            'tok-reactivate',
            '2026-05-01 00:00:00'
        );
    }
}
