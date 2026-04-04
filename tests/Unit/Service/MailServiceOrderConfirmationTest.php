<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendOrderConfirmationToClient().
 *
 * Couvre les branches FR/EN ainsi que les variantes de paiement
 * virement, chèque et carte (pour la note différée et l'intégration complète).
 */
class MailServiceOrderConfirmationTest extends TestCase
{
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    private function sampleItems(): array
    {
        return [
            ['name' => 'Crabitan Rouge', 'qty' => 6, 'price' => 12.0, 'is_cuvee_speciale' => false],
            ['name' => 'Cuvée Spéciale', 'qty' => 6, 'price' => 18.0, 'is_cuvee_speciale' => true],
        ];
    }

    // ----------------------------------------------------------------
    // sendOrderConfirmationToClient — FR + carte
    // ----------------------------------------------------------------

    public function testSendOrderConfirmationToClientFrCardDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendOrderConfirmationToClient(
            'jean@test.com',
            'Jean Dupont',
            'WEB-CB-ABCD1234-2026',
            'card',
            $this->sampleItems(),
            174.0,
            'fr'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendOrderConfirmationToClient — FR + virement (deferredNote IBAN)
    // ----------------------------------------------------------------

    public function testSendOrderConfirmationToClientFrVirementDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendOrderConfirmationToClient(
            'jean@test.com',
            'Jean Dupont',
            'WEB-VB-ABCD1234-2026',
            'virement',
            $this->sampleItems(),
            174.0,
            'fr'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendOrderConfirmationToClient — FR + chèque
    // ----------------------------------------------------------------

    public function testSendOrderConfirmationToClientFrChequeDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendOrderConfirmationToClient(
            'jean@test.com',
            'Jean Dupont',
            'WEB-CHQ-ABCD1234-2026',
            'cheque',
            $this->sampleItems(),
            174.0,
            'fr'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendOrderConfirmationToClient — EN + carte
    // ----------------------------------------------------------------

    public function testSendOrderConfirmationToClientEnCardDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendOrderConfirmationToClient(
            'john@test.com',
            'John Doe',
            'WEB-CB-ABCD5678-2026',
            'card',
            $this->sampleItems(),
            174.0,
            'en'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendOrderConfirmationToClient — EN + virement
    // ----------------------------------------------------------------

    public function testSendOrderConfirmationToClientEnVirementDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendOrderConfirmationToClient(
            'john@test.com',
            'John Doe',
            'WEB-VB-ABCD5678-2026',
            'virement',
            $this->sampleItems(),
            174.0,
            'en'
        );

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // sendOrderConfirmationToClient — EN + chèque
    // ----------------------------------------------------------------

    public function testSendOrderConfirmationToClientEnChequeDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendOrderConfirmationToClient(
            'john@test.com',
            'John Doe',
            'WEB-CHQ-ABCD5678-2026',
            'cheque',
            $this->sampleItems(),
            174.0,
            'en'
        );

        $this->assertTrue(true);
    }
}
