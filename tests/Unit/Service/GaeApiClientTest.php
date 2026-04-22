<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\GaeApiClient;
use Service\PaymentService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires de GaeApiClient.
 *
 * Vérifie la délégation vers PaymentService::callGaeCancelOrRefund().
 */
#[AllowMockObjectsWithoutExpectations]
class GaeApiClientTest extends TestCase
{
    public function testRefundDelegatesToPaymentServiceAndReturnsTrue(): void
    {
        $paymentMock = $this->createMock(PaymentService::class);
        $paymentMock->expects($this->once())
            ->method('callGaeCancelOrRefund')
            ->with('CB-2026-0001', '12345', '67890', 5000)
            ->willReturn(true);

        $client = new GaeApiClient($paymentMock);
        $result = $client->refund('CB-2026-0001', '12345', '67890', 5000);

        $this->assertTrue($result);
    }

    public function testRefundDelegatesToPaymentServiceAndReturnsFalse(): void
    {
        $paymentMock = $this->createMock(PaymentService::class);
        $paymentMock->method('callGaeCancelOrRefund')->willReturn(false);

        $client = new GaeApiClient($paymentMock);
        $result = $client->refund('CB-2026-0099', '00001', '00002', 1000);

        $this->assertFalse($result);
    }
}
