<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Model\AddressModel;
use PHPUnit\Framework\TestCase;
use Service\OrderPdfContextBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * TU OrderPdfContextBuilder — couvre toutes les branches de build().
 */
final class OrderPdfContextBuilderTest extends TestCase
{
    public function testReturnsNullWhenUserIdMissing(): void
    {
        $addresses = $this->createMock(AddressModel::class);
        $addresses->expects($this->never())->method('findByIdForUser');

        $builder = new OrderPdfContextBuilder($addresses);
        $this->assertNull($builder->build([
            'billing_address_id'  => 10,
            'delivery_address_id' => 0,
        ]));
    }

    public function testReturnsNullWhenBillingIdMissing(): void
    {
        $addresses = $this->createMock(AddressModel::class);
        $addresses->expects($this->never())->method('findByIdForUser');

        $builder = new OrderPdfContextBuilder($addresses);
        $this->assertNull($builder->build([
            'user_id'             => 42,
            'billing_address_id'  => 0,
            'delivery_address_id' => 10,
        ]));
    }

    public function testReturnsNullWhenBillingNotFound(): void
    {
        $addresses = $this->createMock(AddressModel::class);
        $addresses->expects($this->once())
            ->method('findByIdForUser')
            ->with(10, 42)
            ->willReturn(null);

        $builder = new OrderPdfContextBuilder($addresses);
        $this->assertNull($builder->build([
            'user_id'             => 42,
            'billing_address_id'  => 10,
            'delivery_address_id' => 11,
        ]));
    }

    public function testReturnsBillingOnlyWhenNoDelivery(): void
    {
        $billing = ['id' => 10, 'street' => '1 rue A', 'city' => 'Paris'];
        $addresses = $this->createMock(AddressModel::class);
        $addresses->expects($this->once())
            ->method('findByIdForUser')
            ->with(10, 42)
            ->willReturn($billing);

        $builder = new OrderPdfContextBuilder($addresses);
        $result = $builder->build([
            'user_id'             => 42,
            'billing_address_id'  => 10,
            'delivery_address_id' => 0,
        ]);

        $this->assertIsArray($result);
        $this->assertSame($billing, $result['billing']);
        $this->assertNull($result['delivery']);
        $this->assertArrayHasKey('ordered_at', $result);
    }

    public function testDeliverySameAsBillingReusesBilling(): void
    {
        $billing = ['id' => 10, 'street' => '1 rue A'];
        $addresses = $this->createMock(AddressModel::class);
        $addresses->expects($this->once())
            ->method('findByIdForUser')
            ->with(10, 42)
            ->willReturn($billing);

        $builder = new OrderPdfContextBuilder($addresses);
        $result = $builder->build([
            'user_id'             => 42,
            'billing_address_id'  => 10,
            'delivery_address_id' => 10,
        ]);

        $this->assertIsArray($result);
        $this->assertSame($billing, $result['delivery']);
    }

    public function testDeliveryLoadedSeparatelyWhenDifferent(): void
    {
        $billing  = ['id' => 10, 'street' => '1 rue A'];
        $delivery = ['id' => 11, 'street' => '2 rue B'];
        $addresses = $this->createMock(AddressModel::class);
        $addresses->expects($this->exactly(2))
            ->method('findByIdForUser')
            ->willReturnCallback(function (int $id, int $userId) use ($billing, $delivery) {
                $this->assertSame(42, $userId);
                return match ($id) {
                    10      => $billing,
                    11      => $delivery,
                    default => null,
                };
            });

        $builder = new OrderPdfContextBuilder($addresses);
        $result = $builder->build([
            'user_id'             => 42,
            'billing_address_id'  => 10,
            'delivery_address_id' => 11,
        ]);

        $this->assertIsArray($result);
        $this->assertSame($billing, $result['billing']);
        $this->assertSame($delivery, $result['delivery']);
    }

    public function testReturnsNullOnException(): void
    {
        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')
            ->willThrowException(new \RuntimeException('DB down'));

        $builder = new OrderPdfContextBuilder($addresses);
        $this->assertNull($builder->build([
            'user_id'             => 42,
            'billing_address_id'  => 10,
            'delivery_address_id' => 11,
        ]));
    }

    public function testOrderedAtIsValidDateTime(): void
    {
        $billing = ['id' => 10];
        $addresses = $this->createStub(AddressModel::class);
        $addresses->method('findByIdForUser')->willReturn($billing);

        $builder = new OrderPdfContextBuilder($addresses);
        $result = $builder->build([
            'user_id'             => 42,
            'billing_address_id'  => 10,
            'delivery_address_id' => 0,
        ]);

        $this->assertIsArray($result);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $result['ordered_at']
        );
    }
}
