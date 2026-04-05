<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendInvoiceToClient() et sendIpnSignatureAlert().
 *
 * Couvre les branches FR/EN de sendInvoiceToClient ainsi que l'alerte IPN.
 * Le mailer PHPMailer est stubé pour éviter toute connexion SMTP réelle.
 */
class MailServiceInvoiceTest extends TestCase
{
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    // ----------------------------------------------------------------
    // sendInvoiceToClient — branche FR
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendInvoiceToClient ne lève pas d'exception en français.
     */
    public function testSendInvoiceToClientFrDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendInvoiceToClient(
            'client@example.com',
            'Jean Dupont',
            'WEB-VB-AABBCCDD-2026',
            'WEB-INVOICE-2026-0042',
            '/tmp/fake-invoice.pdf',
            'fr'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendInvoiceToClient — branche EN
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendInvoiceToClient ne lève pas d'exception en anglais.
     */
    public function testSendInvoiceToClientEnDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendInvoiceToClient(
            'client@example.com',
            'John Smith',
            'WEB-CB-AABBCCDD-2026',
            'WEB-INVOICE-2026-0043',
            '/tmp/fake-invoice.pdf',
            'en'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendInvoiceToClient — sujet contient le numéro de facture (FR)
    // ----------------------------------------------------------------

    /**
     * Vérifie que le sujet de l'email contient le numéro de facture en FR.
     */
    public function testSendInvoiceToClientFrSubjectContainsInvoiceNumber(): void
    {
        $service = new MailService();

        $mailerMock = $this->createMock(PHPMailer::class);
        $mailerMock->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true); // NOSONAR — test unitaire
        $prop->setValue($service, $mailerMock); // NOSONAR — test unitaire

        $service->sendInvoiceToClient(
            'client@example.com',
            'Jean Dupont',
            'WEB-VB-AABB-2026',
            'WEB-INVOICE-2026-0099',
            '/tmp/fake-invoice.pdf',
            'fr'
        );

        $subjectProp = new \ReflectionProperty(PHPMailer::class, 'Subject');
        $subjectProp->setAccessible(true); // NOSONAR — test unitaire
        $this->assertStringContainsString('WEB-INVOICE-2026-0099', (string) $mailerMock->Subject);
    }

    // ----------------------------------------------------------------
    // sendIpnSignatureAlert — ne lève pas d'exception
    // ----------------------------------------------------------------

    /**
     * Vérifie que sendIpnSignatureAlert ne lève pas d'exception.
     */
    public function testSendIpnSignatureAlertDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendIpnSignatureAlert(
            'admin@example.com',
            '2026-04-05 14:30:00',
            '192.168.1.1'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendIpnSignatureAlert — sujet contient ALERTE
    // ----------------------------------------------------------------

    /**
     * Vérifie que le sujet de l'alerte IPN contient le mot ALERTE.
     */
    public function testSendIpnSignatureAlertSubjectContainsAlerte(): void
    {
        $service = new MailService();

        $mailerMock = $this->createMock(PHPMailer::class);
        $mailerMock->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true); // NOSONAR — test unitaire
        $prop->setValue($service, $mailerMock); // NOSONAR — test unitaire

        $service->sendIpnSignatureAlert('admin@example.com', '2026-04-05 10:00:00', '10.0.0.1');

        $this->assertStringContainsString('ALERTE', (string) $mailerMock->Subject);
    }
}
