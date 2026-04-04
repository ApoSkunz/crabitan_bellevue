<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\OrderModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests de OrderModel::create() et OrderModel::findByReference().
 */
class OrderModelCreateTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\Stub $dbMock;
    private \ReflectionProperty $instanceProp;
    private OrderModel $model;

    protected function setUp(): void
    {
        $this->dbMock = $this->createStub(Database::class);

        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new OrderModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ================================================================
    // create()
    // ================================================================

    public function testCreateReturnsOrderReference(): void
    {
        $this->dbMock->method('insert')->willReturn('1');

        $reference = $this->model->create(
            userId: 1,
            items: [['wine_id' => 1, 'qty' => 2, 'price' => 15.0, 'name' => 'Test']],
            price: 30.0,
            paymentMethod: 'card',
            shippingDiscount: 0.0,
            idBillingAddress: 5,
            idDeliveryAddress: null,
            cgvVersion: '1.0'
        );

        $this->assertStringStartsWith('ORD-', $reference);
        $this->assertStringEndsWith('-' . date('Y'), $reference);
    }

    public function testCreateFallsBackToCardForInvalidPaymentMethod(): void
    {
        $capturedParams = [];
        $this->dbMock->method('insert')->willReturnCallback(
            function (string $sql, array $params) use (&$capturedParams): string {
                $capturedParams = $params;
                return '1';
            }
        );

        $this->model->create(
            userId: 1,
            items: [],
            price: 10.0,
            paymentMethod: 'invalid_method',
            shippingDiscount: 0.0,
            idBillingAddress: 1,
            idDeliveryAddress: null,
            cgvVersion: '1.0'
        );

        // Payment method is at index 4 (0-indexed: userId, reference, content, price, payment)
        $this->assertSame('card', $capturedParams[4]);
    }

    public function testCreateAcceptsAllValidPaymentMethods(): void
    {
        $this->dbMock->method('insert')->willReturn('1');

        foreach (OrderModel::VALID_PAYMENT_METHODS as $method) {
            $reference = $this->model->create(1, [], 0.0, $method, 0.0, 1, null, '1.0');
            $this->assertNotEmpty($reference);
        }
    }

    // ================================================================
    // findByReference()
    // ================================================================

    public function testFindByReferenceReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $result = $this->model->findByReference('ORD-UNKNOWN-2025', 1);

        $this->assertNull($result);
    }

    public function testFindByReferenceReturnsArrayWhenFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'id'              => 1,
            'order_reference' => 'ORD-ABCD1234-2025',
            'user_id'         => 1,
            'price'           => '50.00',
            'payment_method'  => 'virement',
        ]);

        $result = $this->model->findByReference('ORD-ABCD1234-2025', 1);

        $this->assertIsArray($result);
        $this->assertSame('ORD-ABCD1234-2025', $result['order_reference']);
    }
}
