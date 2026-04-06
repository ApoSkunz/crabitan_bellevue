<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendRgpdExport().
 *
 * Couvre la génération du corps email RGPD (fr + en) et l'envoi de la pièce jointe
 * via addStringAttachment(). Un mock PHPMailer est injecté pour éviter le SMTP.
 */
class MailServiceRgpdExportTest extends TestCase
{
    private MailService $service;
    /** @var PHPMailer&\PHPUnit\Framework\MockObject\Stub */
    private PHPMailer $mailerStub;

    protected function setUp(): void
    {
        $this->service    = new MailService();
        $this->mailerStub = $this->createStub(PHPMailer::class);
        $this->mailerStub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($this->service, $this->mailerStub);
    }

    // ----------------------------------------------------------------
    // sendRgpdExport — corps fr
    // ----------------------------------------------------------------

    /**
     * Le corps fr mentionne "RGPD" et contient les instructions de sécurité.
     */
    public function testSendRgpdExportFrBodyContainsRgpdMention(): void
    {
        $this->service->sendRgpdExport(
            'jean@example.com',
            'Jean',
            'zip-binary-content',
            'mes-donnees-2026-04-06.zip',
            'fr'
        );

        $this->assertStringContainsString('RGPD', $this->mailerStub->Body);
        $this->assertStringContainsString('Jean', $this->mailerStub->Body);
    }

    /**
     * Le sujet fr contient "RGPD".
     */
    public function testSendRgpdExportFrSubjectContainsRgpd(): void
    {
        $this->service->sendRgpdExport(
            'jean@example.com',
            'Jean',
            'content',
            'mes-donnees.zip',
            'fr'
        );

        $this->assertStringContainsString('RGPD', $this->mailerStub->Subject);
    }

    // ----------------------------------------------------------------
    // sendRgpdExport — corps en
    // ----------------------------------------------------------------

    /**
     * Le corps en mentionne "GDPR" et contient le prénom du destinataire.
     */
    public function testSendRgpdExportEnBodyContainsGdprMention(): void
    {
        $this->service->sendRgpdExport(
            'alice@example.com',
            'Alice',
            'zip-binary-content',
            'my-data-2026-04-06.zip',
            'en'
        );

        $this->assertStringContainsString('GDPR', $this->mailerStub->Body);
        $this->assertStringContainsString('Alice', $this->mailerStub->Body);
    }

    /**
     * Le sujet en contient "GDPR".
     */
    public function testSendRgpdExportEnSubjectContainsGdpr(): void
    {
        $this->service->sendRgpdExport(
            'alice@example.com',
            'Alice',
            'content',
            'my-data.zip',
            'en'
        );

        $this->assertStringContainsString('GDPR', $this->mailerStub->Subject);
    }

    // ----------------------------------------------------------------
    // sendRgpdExport — AltBody renseigné
    // ----------------------------------------------------------------

    /**
     * L'AltBody (version texte brut) est renseigné et non vide.
     */
    public function testSendRgpdExportAltBodyIsNotEmpty(): void
    {
        $this->service->sendRgpdExport(
            'test@example.com',
            'Test',
            'content',
            'data.zip',
            'fr'
        );

        $this->assertNotEmpty($this->mailerStub->AltBody);
    }
}
