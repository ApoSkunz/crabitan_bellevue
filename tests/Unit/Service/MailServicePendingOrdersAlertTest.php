<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendPendingOrdersAlert().
 *
 * Couvre les branches : liste vide, liste avec commandes, labels statut/paiement.
 */
class MailServicePendingOrdersAlertTest extends TestCase
{
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    private function sampleOrders(): array
    {
        return [
            [
                'id'             => 42,
                'order_reference' => 'CB-2026-042',
                'status'         => 'pending',
                'price'          => 75.50,
                'payment_method' => 'cheque',
                'updated_at'     => '2026-04-01 10:00:00',
            ],
            [
                'id'             => 43,
                'order_reference' => 'CB-2026-043',
                'status'         => 'paid',
                'price'          => 120.00,
                'payment_method' => 'virement',
                'updated_at'     => '2026-03-28 14:00:00',
            ],
        ];
    }

    // ----------------------------------------------------------------
    // Liste non vide — ne lève pas d'exception
    // ----------------------------------------------------------------

    public function testSendPendingOrdersAlertWithOrdersDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendPendingOrdersAlert($this->sampleOrders(), 7);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Liste vide — ne lève pas d'exception
    // ----------------------------------------------------------------

    public function testSendPendingOrdersAlertWithEmptyOrdersDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendPendingOrdersAlert([], 7);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Délai personnalisé
    // ----------------------------------------------------------------

    public function testSendPendingOrdersAlertWithCustomDaysDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendPendingOrdersAlert($this->sampleOrders(), 14);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Commande avec paiement carte (label CB) + statut processing
    // ----------------------------------------------------------------

    public function testSendPendingOrdersAlertWithCardAndProcessingStatusDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $orders = [[
            'id'             => 10,
            'order_reference' => 'CB-2026-010',
            'status'         => 'processing',
            'price'          => 55.00,
            'payment_method' => 'card',
            'updated_at'     => '2026-04-02 09:00:00',
        ]];

        $service->sendPendingOrdersAlert($orders, 7);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Commande avec statut inconnu + méthode inconnue + champs manquants
    // ----------------------------------------------------------------

    public function testSendPendingOrdersAlertWithUnknownStatusAndMissingFieldsDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $orders = [['id' => 0]]; // champs optionnels absents

        $service->sendPendingOrdersAlert($orders, 7);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Statuts supplémentaires (shipped, return_requested, refund_refused)
    // ----------------------------------------------------------------

    public function testSendPendingOrdersAlertWithShippedAndReturnStatusesDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $orders = [
            [
                'id' => 50, 'order_reference' => 'CB-2026-050',
                'status' => 'shipped', 'price' => 30.0,
                'payment_method' => 'card', 'updated_at' => '2026-03-30 08:00:00',
            ],
            [
                'id' => 51, 'order_reference' => 'CB-2026-051',
                'status' => 'return_requested', 'price' => 60.0,
                'payment_method' => 'cheque', 'updated_at' => '2026-03-29 08:00:00',
            ],
            [
                'id' => 52, 'order_reference' => 'CB-2026-052',
                'status' => 'refund_refused', 'price' => 90.0,
                'payment_method' => 'virement', 'updated_at' => '2026-03-28 08:00:00',
            ],
        ];

        $service->sendPendingOrdersAlert($orders, 7);

        $this->assertTrue(true);
    }
}
