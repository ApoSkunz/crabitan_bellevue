<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Mail;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Service\Mail\OrderMailer;
use Service\MailService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires de OrderMailer — vérifie la délégation vers MailService.
 */
#[AllowMockObjectsWithoutExpectations]
class OrderMailerTest extends TestCase
{
    private MailService&MockObject $mailServiceMock;
    private OrderMailer $mailer;

    protected function setUp(): void
    {
        $this->mailServiceMock = $this->createMock(MailService::class);
        $this->mailer          = new OrderMailer($this->mailServiceMock);
    }

    /**
     * sendOrderConfirmationToClient délègue correctement à MailService.
     */
    public function testSendOrderConfirmationToClientDelegates(): void
    {
        $items = [['name' => 'Bordeaux', 'qty' => 2, 'price' => 24.90]];

        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendOrderConfirmationToClient')
            ->with('client@example.com', 'Alice', 'CMD-001', 'card', $items, 49.80, 'fr', 0.0);

        $this->mailer->sendOrderConfirmationToClient(
            'client@example.com',
            'Alice',
            'CMD-001',
            'card',
            $items,
            49.80,
            'fr'
        );
    }

    /**
     * sendOrderConfirmationToClient transmet le shippingDiscount quand fourni.
     */
    public function testSendOrderConfirmationToClientWithShippingDiscount(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendOrderConfirmationToClient')
            ->with('client@example.com', 'Bob', 'CMD-002', 'virement', [], 99.00, 'en', 5.90);

        $this->mailer->sendOrderConfirmationToClient(
            'client@example.com',
            'Bob',
            'CMD-002',
            'virement',
            [],
            99.00,
            'en',
            5.90
        );
    }

    /**
     * sendOrderConfirmationToOwner délègue correctement à MailService.
     */
    public function testSendOrderConfirmationToOwnerDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendOrderConfirmationToOwner')
            ->with('client@example.com', 'Alice', 'CMD-001', 'card', [], 49.80, 0.0);

        $this->mailer->sendOrderConfirmationToOwner('client@example.com', 'Alice', 'CMD-001', 'card', [], 49.80);
    }

    /**
     * sendOrderStatusEmail délègue correctement à MailService.
     */
    public function testSendOrderStatusEmailDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendOrderStatusEmail')
            ->with('client@example.com', 'Alice', 'CMD-001', 'shipped', 'fr', 'https://app.fr', [], [], '', 0);

        $this->mailer->sendOrderStatusEmail('client@example.com', 'Alice', 'CMD-001', 'shipped', 'fr', 'https://app.fr');
    }

    /**
     * sendReturnRequestedToOwner délègue correctement à MailService.
     */
    public function testSendReturnRequestedToOwnerDelegates(): void
    {
        $order = ['id' => 42, 'ref' => 'CMD-042'];

        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendReturnRequestedToOwner')
            ->with($order, 'Alice', 'client@example.com');

        $this->mailer->sendReturnRequestedToOwner($order, 'Alice', 'client@example.com');
    }

    /**
     * sendRefundGaeKoToOwner délègue correctement à MailService.
     */
    public function testSendRefundGaeKoToOwnerDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendRefundGaeKoToOwner')
            ->with('CMD-001', 49.80, 0);

        $this->mailer->sendRefundGaeKoToOwner('CMD-001', 49.80);
    }

    /**
     * sendRefundRequestedToOwner délègue correctement à MailService.
     */
    public function testSendRefundRequestedToOwnerDelegates(): void
    {
        $order = ['id' => 7];

        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendRefundRequestedToOwner')
            ->with($order, 'Bob', 'bob@example.com');

        $this->mailer->sendRefundRequestedToOwner($order, 'Bob', 'bob@example.com');
    }

    /**
     * sendPendingOrdersAlert délègue correctement à MailService avec délai par défaut.
     */
    public function testSendPendingOrdersAlertDelegates(): void
    {
        $orders = [['id' => 1], ['id' => 2]];

        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendPendingOrdersAlert')
            ->with($orders, 7);

        $this->mailer->sendPendingOrdersAlert($orders);
    }

    /**
     * sendReturnConfirmedToClient délègue correctement à MailService.
     */
    public function testSendReturnConfirmedToClientDelegates(): void
    {
        $order = ['id' => 42];

        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendReturnConfirmedToClient')
            ->with('client@example.com', 'Alice', $order, '/tmp/bon-retour.pdf', 'fr');

        $this->mailer->sendReturnConfirmedToClient(
            'client@example.com',
            'Alice',
            $order,
            '/tmp/bon-retour.pdf',
            'fr'
        );
    }

    /**
     * sendInvoiceToClient délègue correctement à MailService.
     */
    public function testSendInvoiceToClientDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendInvoiceToClient')
            ->with('client@example.com', 'Alice', 'CMD-001', 'FA-2026-001', '/tmp/fa.pdf', 'fr');

        $this->mailer->sendInvoiceToClient('client@example.com', 'Alice', 'CMD-001', 'FA-2026-001', '/tmp/fa.pdf', 'fr');
    }

    /**
     * sendRefundPendingEmail délègue correctement à MailService.
     */
    public function testSendRefundPendingEmailDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendRefundPendingEmail')
            ->with('client@example.com', 'Alice', 'CMD-001', 'fr', 'https://app.fr');

        $this->mailer->sendRefundPendingEmail('client@example.com', 'Alice', 'CMD-001', 'fr', 'https://app.fr');
    }

    /**
     * sendIpnSignatureAlert délègue correctement à MailService.
     */
    public function testSendIpnSignatureAlertDelegates(): void
    {
        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendIpnSignatureAlert')
            ->with('admin@example.com', '2026-04-12', '1.2.3.4');

        $this->mailer->sendIpnSignatureAlert('admin@example.com', '2026-04-12', '1.2.3.4');
    }

    /**
     * sendPostPurchase délègue correctement à MailService.
     */
    public function testSendPostPurchaseDelegates(): void
    {
        $items = [['id' => 1]];

        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendPostPurchase')
            ->with('client@example.com', 'Alice', 42, 7, $items, 'fr');

        $this->mailer->sendPostPurchase('client@example.com', 'Alice', 42, 7, $items, 'fr');
    }

    /**
     * sendAbandonedCart délègue correctement à MailService.
     */
    public function testSendAbandonedCartDelegates(): void
    {
        $items = [['id' => 3]];

        $this->mailServiceMock
            ->expects($this->once())
            ->method('sendAbandonedCart')
            ->with('client@example.com', 'Alice', $items, 'fr', 'https://app.fr/panier');

        $this->mailer->sendAbandonedCart('client@example.com', 'Alice', $items, 'fr', 'https://app.fr/panier');
    }
}
