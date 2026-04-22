<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Mail;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Service\Mail\AuthMailer;
use Service\MailService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires de AuthMailer — vérifie la délégation vers MailService.
 */
#[AllowMockObjectsWithoutExpectations]
class AuthMailerTest extends TestCase
{
    private MailService&MockObject $mailServiceMock;
    private AuthMailer $authMailer;

    protected function setUp(): void
    {
        $this->mailServiceMock = $this->createMock(MailService::class);
        $this->authMailer      = new AuthMailer($this->mailServiceMock);
    }

    /**
     * sendEmailVerification délègue correctement à MailService.
     */
    public function testSendEmailVerificationDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendEmailVerification')
            ->with('user@example.com', 'Alice', 'https://example.com/verify/abc', 'fr');

        $this->authMailer->sendEmailVerification('user@example.com', 'Alice', 'https://example.com/verify/abc', 'fr');
    }

    /**
     * sendPasswordReset délègue correctement à MailService.
     */
    public function testSendPasswordResetDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendPasswordReset')
            ->with('user@example.com', 'Bob', 'https://example.com/reset/xyz', 'en');

        $this->authMailer->sendPasswordReset('user@example.com', 'Bob', 'https://example.com/reset/xyz', 'en');
    }

    /**
     * sendAccountLocked délègue correctement à MailService.
     */
    public function testSendAccountLockedDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendAccountLocked')
            ->with('user@example.com', 'Charlie', 'fr', 'https://example.com/reset');

        $this->authMailer->sendAccountLocked('user@example.com', 'Charlie', 'fr', 'https://example.com/reset');
    }

    /**
     * sendAdminLoginFailureAlert délègue correctement à MailService avec targetName null.
     */
    public function testSendAdminLoginFailureAlertDelegatesWithNullTargetName(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendAdminLoginFailureAlert')
            ->with('admin@example.com', 'Admin', 'fr', '192.168.1.1', null);

        $this->authMailer->sendAdminLoginFailureAlert('admin@example.com', 'Admin', 'fr', '192.168.1.1');
    }

    /**
     * sendAdminLoginFailureAlert transmet targetName quand fourni.
     */
    public function testSendAdminLoginFailureAlertDelegatesWithTargetName(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendAdminLoginFailureAlert')
            ->with('admin@example.com', 'Admin', 'fr', '10.0.0.1', 'TargetAdmin');

        $this->authMailer->sendAdminLoginFailureAlert('admin@example.com', 'Admin', 'fr', '10.0.0.1', 'TargetAdmin');
    }

    /**
     * sendEmailAlreadyExists délègue correctement à MailService.
     */
    public function testSendEmailAlreadyExistsDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendEmailAlreadyExists')
            ->with('user@example.com', 'Diana', 'fr', 'https://example.com/login', 'https://example.com/reset');

        $this->authMailer->sendEmailAlreadyExists(
            'user@example.com',
            'Diana',
            'fr',
            'https://example.com/login',
            'https://example.com/reset'
        );
    }

    /**
     * sendAdminAccountBlocked délègue correctement à MailService.
     */
    public function testSendAdminAccountBlockedDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendAdminAccountBlocked')
            ->with('admin@example.com', 'Admin', 'fr', 'SuperAdmin');

        $this->authMailer->sendAdminAccountBlocked('admin@example.com', 'Admin', 'fr', 'SuperAdmin');
    }

    /**
     * sendAdminAccountUnblocked délègue correctement à MailService.
     */
    public function testSendAdminAccountUnblockedDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendAdminAccountUnblocked')
            ->with('admin@example.com', 'Admin', 'en', 'SuperAdmin');

        $this->authMailer->sendAdminAccountUnblocked('admin@example.com', 'Admin', 'en', 'SuperAdmin');
    }

    /**
     * sendAdminPasswordChangeAlert délègue correctement à MailService.
     */
    public function testSendAdminPasswordChangeAlertDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendAdminPasswordChangeAlert')
            ->with('admin@example.com', 'Admin', 'fr', '127.0.0.1', 'Mozilla/5.0', null);

        $this->authMailer->sendAdminPasswordChangeAlert('admin@example.com', 'Admin', 'fr', '127.0.0.1', 'Mozilla/5.0');
    }
}
