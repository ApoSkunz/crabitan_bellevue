<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\OrderModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour OrderModel.
 * La BDD est mockée — aucune connexion réelle.
 */
#[AllowMockObjectsWithoutExpectations]
class OrderModelTest extends TestCase
{
    private Database $dbMock;
    private OrderModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new OrderModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // CANCEL_WINDOW_DAYS
    // ----------------------------------------------------------------

    /**
     * La constante doit valoir 14 jours (art. L221-18 Code conso).
     */
    public function testCancelWindowDaysIs14(): void
    {
        $this->assertSame(14, OrderModel::CANCEL_WINDOW_DAYS);
    }

    // ----------------------------------------------------------------
    // requestReturnForUser()
    // ----------------------------------------------------------------

    /**
     * Retourne false si la commande n'existe pas ou n'appartient pas à l'utilisateur.
     */
    public function testRequestReturnReturnsFalseWhenOrderNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertFalse($this->model->requestReturnForUser(99, 1));
    }

    /**
     * Retourne false si le statut n'est pas "delivered".
     */
    public function testRequestReturnReturnsFalseWhenStatusNotDelivered(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'status'       => 'shipped',
            'delivered_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
        ]);

        $this->assertFalse($this->model->requestReturnForUser(1, 1));
    }

    /**
     * Retourne false si delivered_at est null (admin n'a pas confirmé la livraison).
     */
    public function testRequestReturnReturnsFalseWhenDeliveredAtNull(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'status'       => 'delivered',
            'delivered_at' => null,
        ]);

        $this->assertFalse($this->model->requestReturnForUser(1, 1));
    }

    /**
     * Retourne false si la fenêtre de 14 jours est dépassée.
     */
    public function testRequestReturnReturnsFalseWhenWindowExpired(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'status'       => 'delivered',
            'delivered_at' => date('Y-m-d H:i:s', strtotime('-15 days')),
        ]);

        $this->assertFalse($this->model->requestReturnForUser(1, 1));
    }

    /**
     * Retourne true et exécute UPDATE quand toutes les conditions sont réunies.
     */
    public function testRequestReturnReturnsTrueWithinWindow(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'status'       => 'delivered',
            'delivered_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
        ]);
        $this->dbMock->expects($this->once())->method('execute');

        $this->assertTrue($this->model->requestReturnForUser(1, 1));
    }

    /**
     * Retourne true le dernier jour de la fenêtre (J+14, même heure).
     */
    public function testRequestReturnReturnsTrueOnLastDay(): void
    {
        $this->dbMock->method('fetchOne')->willReturn([
            'status'       => 'delivered',
            // 14 jours - 1 minute = encore dans la fenêtre
            'delivered_at' => date('Y-m-d H:i:s', time() - (14 * 86400) + 60),
        ]);
        $this->dbMock->expects($this->once())->method('execute');

        $this->assertTrue($this->model->requestReturnForUser(1, 1));
    }

    // ----------------------------------------------------------------
    // updateStatus() — delivered_at
    // ----------------------------------------------------------------

    /**
     * Passer au statut "delivered" doit déclencher un UPDATE avec delivered_at = NOW().
     */
    public function testUpdateStatusSetsDeliveredAt(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('delivered_at'),
                $this->anything()
            );

        $this->model->updateStatus(1, 'delivered');
    }

    /**
     * Passer à un autre statut ne doit PAS inclure delivered_at dans la requête.
     */
    public function testUpdateStatusDoesNotSetDeliveredAtForOtherStatus(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->logicalNot($this->stringContains('delivered_at')),
                $this->anything()
            );

        $this->model->updateStatus(1, 'shipped');
    }

    /**
     * Un statut invalide ne doit déclencher aucun appel BDD.
     */
    public function testUpdateStatusIgnoresInvalidStatus(): void
    {
        $this->dbMock->expects($this->never())->method('execute');

        $this->model->updateStatus(1, 'invalid_status');
    }

    // ----------------------------------------------------------------
    // pseudonymizeExpired()
    // ----------------------------------------------------------------

    /**
     * pseudonymizeExpired() doit appeler execute() avec une requête UPDATE incluant pseudonymized_at.
     */
    public function testPseudonymizeExpiredExecutesUpdateQuery(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with($this->stringContains('pseudonymized_at'))
            ->willReturn(3);

        $result = $this->model->pseudonymizeExpired();

        $this->assertSame(3, $result);
    }

    /**
     * pseudonymizeExpired() retourne 0 quand aucun snapshot n'est expiré.
     */
    public function testPseudonymizeExpiredReturnsZeroWhenNoRows(): void
    {
        $this->dbMock->method('execute')->willReturn(0);

        $this->assertSame(0, $this->model->pseudonymizeExpired());
    }

    // ----------------------------------------------------------------
    // getStaleOrders()
    // ----------------------------------------------------------------

    /**
     * getStaleOrders() appelle fetchAll avec une requête excluant les états finaux.
     */
    public function testGetStaleOrdersCallsFetchAllWithCorrectQuery(): void
    {
        $expected = [
            [
                'id' => 1, 'order_reference' => 'CB-2026-001', 'status' => 'pending',
                'price' => 45.0, 'payment_method' => 'cheque',
                'ordered_at' => '2026-03-01', 'updated_at' => '2026-03-01',
            ],
        ];

        $this->dbMock->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains("status NOT IN ('delivered', 'cancelled', 'refunded')"),
                [7]
            )
            ->willReturn($expected);

        $result = $this->model->getStaleOrders(7);

        $this->assertSame($expected, $result);
    }

    /**
     * getStaleOrders() retourne un tableau vide si aucune commande ne stagne.
     */
    public function testGetStaleOrdersReturnsEmptyArrayWhenNone(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->model->getStaleOrders());
    }

    /**
     * getStaleOrders() transmet le seuil de jours personnalisé au paramètre.
     */
    public function testGetStaleOrdersUsesCustomDaysThreshold(): void
    {
        $this->dbMock->expects($this->once())
            ->method('fetchAll')
            ->with($this->anything(), [14])
            ->willReturn([]);

        $this->model->getStaleOrders(14);
    }
}
