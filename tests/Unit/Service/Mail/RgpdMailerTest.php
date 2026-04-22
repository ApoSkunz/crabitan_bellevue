<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Mail;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Service\Mail\RgpdMailer;
use Service\MailService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires de RgpdMailer — vérifie la délégation vers MailService.
 */
#[AllowMockObjectsWithoutExpectations]
class RgpdMailerTest extends TestCase
{
    private MailService&MockObject $mailServiceMock;
    private RgpdMailer $mailer;

    protected function setUp(): void
    {
        $this->mailServiceMock = $this->createMock(MailService::class);
        $this->mailer          = new RgpdMailer($this->mailServiceMock);
    }

    /**
     * sendRgpdExport délègue correctement à MailService.
     */
    public function testSendRgpdExportDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendRgpdExport')
            ->with('user@example.com', 'Alice', 'zip-binary-content', 'export-alice.zip', 'fr');

        $this->mailer->sendRgpdExport('user@example.com', 'Alice', 'zip-binary-content', 'export-alice.zip', 'fr');
    }

    /**
     * sendRgpdExport transmet correctement la langue anglaise.
     */
    public function testSendRgpdExportDelegatesEnglish(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendRgpdExport')
            ->with('user@example.com', 'Bob', 'zip-data', 'export-bob.zip', 'en');

        $this->mailer->sendRgpdExport('user@example.com', 'Bob', 'zip-data', 'export-bob.zip', 'en');
    }
}
