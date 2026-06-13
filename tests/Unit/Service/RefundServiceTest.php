<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Core\FeatureFlags;
use Model\OrderModel;
use PHPUnit\Framework\TestCase;
use Service\GaeApiInterface;
use Service\MailService;
use Service\RefundService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires de RefundService.
 *
 * Couvre les deux branches ca_enable_gae=true et ca_enable_gae=false.
 */
#[AllowMockObjectsWithoutExpectations]
class RefundServiceTest extends TestCase
{
    private OrderModel&\PHPUnit\Framework\MockObject\MockObject $ordersMock;
    private GaeApiInterface&\PHPUnit\Framework\MockObject\MockObject $gaeMock;
    private MailService&\PHPUnit\Framework\MockObject\MockObject $mailerMock;

    /** @var array<string, mixed> */
    private array $fakeOrder;

    protected function setUp(): void
    {
        $this->ordersMock = $this->createMock(OrderModel::class);
        $this->gaeMock    = $this->createMock(GaeApiInterface::class);
        $this->mailerMock = $this->createMock(MailService::class);

        $this->fakeOrder = [
            'id'              => 42,
            'order_reference' => 'CB-2026-0042',
            'status'          => 'paid',
            'price'           => '120.00',
            'payment_method'  => 'card',
            'ca_numappel'     => '12345',
            'ca_numtrans'     => '67890',
            'email'           => 'client@example.com',
            'firstname'       => 'Jean',
            'lastname'        => 'Dupont',
            'lang'            => 'fr',
        ];
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_URL']);
        FeatureFlags::flush();
    }

    // ----------------------------------------------------------------
    // ca_enable_gae = true
    // ----------------------------------------------------------------

    public function testRefundWithGaeEnabledCallsApiAndSetsRefunded(): void
    {
        FeatureFlags::setCache(['ca_enable_gae' => ['key' => 'ca_enable_gae', 'value' => 'true', 'type' => 'bool']]);
        $_ENV['APP_URL']       = 'https://crabitanbellevue.fr';

        $this->ordersMock->expects($this->once())
            ->method('findByIdForAdmin')
            ->with(42)
            ->willReturn($this->fakeOrder);

        $this->gaeMock->expects($this->once())
            ->method('refund')
            ->with('CB-2026-0042', '12345', '67890', 12000)
            ->willReturn(true);

        $this->ordersMock->expects($this->once())
            ->method('updateStatus')
            ->with(42, 'refunded');

        $this->mailerMock->expects($this->once())
            ->method('sendOrderStatusEmail');

        $service = new RefundService($this->ordersMock, $this->gaeMock, $this->mailerMock);
        $result  = $service->refund(42);

        $this->assertSame('refunded', $result);
    }

    public function testRefundWithGaeEnabledThrowsWhenApiFails(): void
    {
        FeatureFlags::setCache(['ca_enable_gae' => ['key' => 'ca_enable_gae', 'value' => 'true', 'type' => 'bool']]);

        $this->ordersMock->method('findByIdForAdmin')->willReturn($this->fakeOrder);
        $this->gaeMock->method('refund')->willReturn(false);
        $this->ordersMock->expects($this->never())->method('updateStatus');

        $service = new RefundService($this->ordersMock, $this->gaeMock, $this->mailerMock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Échec remboursement GAE/');

        $service->refund(42);
    }

    // ----------------------------------------------------------------
    // ca_enable_gae = false
    // ----------------------------------------------------------------

    public function testRefundWithGaeDisabledSetsRefundPendingAndLogs(): void
    {
        FeatureFlags::setCache(['ca_enable_gae' => ['key' => 'ca_enable_gae', 'value' => 'false', 'type' => 'bool']]);
        $_ENV['APP_URL']       = 'https://crabitanbellevue.fr';

        $this->ordersMock->expects($this->once())
            ->method('findByIdForAdmin')
            ->with(42)
            ->willReturn($this->fakeOrder);

        $this->gaeMock->expects($this->never())->method('refund');

        $this->ordersMock->expects($this->once())
            ->method('updateStatus')
            ->with(42, 'refund_pending');

        $this->mailerMock->expects($this->once())
            ->method('sendRefundPendingEmail');

        $service = new RefundService($this->ordersMock, $this->gaeMock, $this->mailerMock);
        $result  = $service->refund(42);

        $this->assertSame('refund_pending', $result);
    }

    public function testRefundWithGaeDefaultsToDisabled(): void
    {
        FeatureFlags::flush();
        $_ENV['APP_URL'] = 'https://crabitanbellevue.fr';

        $this->ordersMock->method('findByIdForAdmin')->willReturn($this->fakeOrder);
        $this->gaeMock->expects($this->never())->method('refund');

        $this->ordersMock->expects($this->once())
            ->method('updateStatus')
            ->with(42, 'refund_pending');

        $service = new RefundService($this->ordersMock, $this->gaeMock, $this->mailerMock);
        $result  = $service->refund(42);

        $this->assertSame('refund_pending', $result);
    }

    public function testRefundWithGaeEnabledNotifiesOwnerWhenEmailSet(): void
    {
        FeatureFlags::setCache(['ca_enable_gae' => ['key' => 'ca_enable_gae', 'value' => 'true', 'type' => 'bool']]);
        $_ENV['APP_URL']             = 'https://crabitanbellevue.fr';
        $_ENV['CONTACT_OWNER_EMAIL'] = 'owner@crabitanbellevue.fr';

        $this->ordersMock->method('findByIdForAdmin')->willReturn($this->fakeOrder);
        $this->gaeMock->method('refund')->willReturn(true);
        $this->ordersMock->method('updateStatus');
        $this->mailerMock->method('sendOrderStatusEmail');

        $this->mailerMock->expects($this->once())
            ->method('sendOrderStatusEmailToOwner')
            ->with(
                'owner@crabitanbellevue.fr',
                'CB-2026-0042',
                'refunded',
                'Jean Dupont',
                'client@example.com',
                $this->anything(),
                $this->anything(),
                $this->anything()
            );

        $service = new RefundService($this->ordersMock, $this->gaeMock, $this->mailerMock);
        $result  = $service->refund(42);

        $this->assertSame('refunded', $result);
        unset($_ENV['CONTACT_OWNER_EMAIL']);
    }

    public function testRefundThrowsInvalidArgumentWhenOrderNotFound(): void
    {
        $this->ordersMock->method('findByIdForAdmin')->willReturn(null);

        $service = new RefundService($this->ordersMock, $this->gaeMock, $this->mailerMock);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Commande introuvable/');

        $service->refund(999);
    }

    public function testRefundSkipsEmailForPurgedAccount(): void
    {
        FeatureFlags::setCache(['ca_enable_gae' => ['key' => 'ca_enable_gae', 'value' => 'false', 'type' => 'bool']]);

        $purgedOrder         = $this->fakeOrder;
        $purgedOrder['email'] = 'user@purged.invalid';

        $this->ordersMock->method('findByIdForAdmin')->willReturn($purgedOrder);
        $this->ordersMock->method('updateStatus');

        $this->mailerMock->expects($this->never())->method('sendRefundPendingEmail');
        $this->mailerMock->expects($this->never())->method('sendOrderStatusEmail');

        $service = new RefundService($this->ordersMock, $this->gaeMock, $this->mailerMock);
        $result  = $service->refund(42);

        $this->assertSame('refund_pending', $result);
    }

    // ----------------------------------------------------------------
    // Couverture des catch \Throwable dans sendRefundInitiatedEmail
    // et notifyOwnerRefundPending
    // ----------------------------------------------------------------

    /**
     * Si sendRefundPendingEmail lève une exception, elle est avalée.
     *
     * Couvre le catch \Throwable dans sendRefundInitiatedEmail (ligne ~178).
     */
    public function testRefundPendingSmtpErrorInSendRefundInitiatedEmailIsSwallowed(): void
    {
        FeatureFlags::setCache(['ca_enable_gae' => ['key' => 'ca_enable_gae', 'value' => 'false', 'type' => 'bool']]);
        $_ENV['APP_URL']       = 'https://crabitanbellevue.fr';

        $this->ordersMock->method('findByIdForAdmin')->willReturn($this->fakeOrder);
        $this->ordersMock->method('updateStatus');

        $this->mailerMock->method('sendRefundPendingEmail')
            ->willThrowException(new \RuntimeException('SMTP error'));

        // notifyOwnerRefundPending s'exécute après — ne pas lever non plus
        $this->mailerMock->method('sendRefundRequestedToOwner');

        $service = new RefundService($this->ordersMock, $this->gaeMock, $this->mailerMock);
        $result  = $service->refund(42);

        $this->assertSame('refund_pending', $result);
    }

    /**
     * Si sendRefundRequestedToOwner lève une exception, elle est avalée.
     *
     * Couvre le catch \Throwable dans notifyOwnerRefundPending (ligne ~142).
     */
    public function testRefundPendingSmtpErrorInNotifyOwnerIsSwallowed(): void
    {
        FeatureFlags::setCache(['ca_enable_gae' => ['key' => 'ca_enable_gae', 'value' => 'false', 'type' => 'bool']]);
        $_ENV['APP_URL']       = 'https://crabitanbellevue.fr';

        $this->ordersMock->method('findByIdForAdmin')->willReturn($this->fakeOrder);
        $this->ordersMock->method('updateStatus');

        $this->mailerMock->method('sendRefundPendingEmail');

        $this->mailerMock->method('sendRefundRequestedToOwner')
            ->willThrowException(new \RuntimeException('SMTP owner error'));

        $service = new RefundService($this->ordersMock, $this->gaeMock, $this->mailerMock);
        $result  = $service->refund(42);

        $this->assertSame('refund_pending', $result);
    }
}
