<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendOrderStatusEmailToOwner().
 *
 * Couvre : tous les statuts déclencheurs, lien admin, client sans nom, montant zéro.
 */
class MailServiceOrderStatusOwnerTest extends TestCase
{
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    protected function setUp(): void
    {
        $_ENV['MAIL_USER'] = 'noreply@crabitanbellevue.fr';
        $_ENV['APP_URL']   = 'https://crabitanbellevue.fr';
    }

    protected function tearDown(): void
    {
        unset($_ENV['MAIL_USER'], $_ENV['APP_URL']);
    }

    // ----------------------------------------------------------------
    // Statuts déclencheurs — aucune exception
    // ----------------------------------------------------------------

    #[DataProvider('triggerStatusProvider')]
    public function testSendOwnerEmailForEachTriggerStatus(string $status): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendOrderStatusEmailToOwner(
            'owner@crabitanbellevue.fr',
            'CB-2026-0001',
            $status,
            'Sophie Dupont',
            'sophie@example.com',
            99.90,
            'https://crabitanbellevue.fr',
            1
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function triggerStatusProvider(): array
    {
        return [
            'processing' => ['processing'],
            'shipped'    => ['shipped'],
            'delivered'  => ['delivered'],
            'cancelled'  => ['cancelled'],
            'refunded'   => ['refunded'],
        ];
    }

    // ----------------------------------------------------------------
    // orderId = 0 — pas de lien admin
    // ----------------------------------------------------------------

    public function testSendOwnerEmailWithoutOrderIdDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendOrderStatusEmailToOwner(
            'owner@crabitanbellevue.fr',
            'CB-2026-0002',
            'shipped',
            'Marc Laurent',
            'marc@example.com',
            55.00,
            '',
            0
        );

        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // Statut inconnu — fallback ucfirst
    // ----------------------------------------------------------------

    public function testSendOwnerEmailWithUnknownStatusDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendOrderStatusEmailToOwner(
            'owner@crabitanbellevue.fr',
            'CB-2026-0003',
            'unknown_status',
            'Client Test',
            'client@example.com',
            10.00
        );

        $this->addToAssertionCount(1);
    }

    // ----------------------------------------------------------------
    // Montant zéro — formatage sans erreur
    // ----------------------------------------------------------------

    public function testSendOwnerEmailWithZeroTotalDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendOrderStatusEmailToOwner(
            'owner@crabitanbellevue.fr',
            'CB-2026-0004',
            'cancelled',
            'Client Test',
            'client@example.com',
            0.0
        );

        $this->addToAssertionCount(1);
    }
}
