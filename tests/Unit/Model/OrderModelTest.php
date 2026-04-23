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

    // ----------------------------------------------------------------
    // getConversionRate()
    // ----------------------------------------------------------------

    /**
     * Retourne 0.0 quand aucun panier actif (dénominateur = 0).
     */
    public function testGetConversionRateReturnsZeroWhenNoOrders(): void
    {
        $this->dbMock->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                ['cnt' => 0],
                ['cnt' => 0]
            );

        $this->assertSame(0.0, $this->model->getConversionRate(30));
    }

    /**
     * Calcule correctement le ratio : 5 commandes / (5 + 10 paniers abandonnés) = 0.3333.
     */
    public function testGetConversionRateCalculatesCorrectRatio(): void
    {
        $this->dbMock->method('fetchOne')
            ->willReturnOnConsecutiveCalls(
                ['cnt' => 5],
                ['cnt' => 10]
            );

        $this->assertSame(round(5 / 15, 4), $this->model->getConversionRate(30));
    }

    // ----------------------------------------------------------------
    // getDeliveredForRatingEmail
    // ----------------------------------------------------------------

    /**
     * getDeliveredForRatingEmail retourne les lignes renvoyées par fetchAll.
     */
    public function testGetDeliveredForRatingEmailReturnsFetchAllResult(): void
    {
        $expected = [
            ['id' => 1, 'user_id' => 10, 'content' => '[]', 'price' => 45.0],
            ['id' => 2, 'user_id' => 11, 'content' => '[]', 'price' => 60.0],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expected);

        $result = $this->model->getDeliveredForRatingEmail();
        $this->assertSame($expected, $result);
    }

    /**
     * getDeliveredForRatingEmail retourne [] si aucune commande éligible.
     */
    public function testGetDeliveredForRatingEmailReturnsEmptyWhenNone(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->model->getDeliveredForRatingEmail());
    }

    // ----------------------------------------------------------------
    // markRatingEmailSent
    // ----------------------------------------------------------------

    /**
     * markRatingEmailSent appelle execute avec l'orderId.
     */
    public function testMarkRatingEmailSentCallsExecuteWithOrderId(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('rating_email_sent_at'),
                [42]
            );

        $this->model->markRatingEmailSent(42);
    }

    // ----------------------------------------------------------------
    // getDeliveredForReviewEmail
    // ----------------------------------------------------------------

    /**
     * getDeliveredForReviewEmail retourne les lignes renvoyées par fetchAll.
     */
    public function testGetDeliveredForReviewEmailReturnsFetchAllResult(): void
    {
        $expected = [
            ['id' => 5, 'user_id' => 20],
        ];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expected);

        $result = $this->model->getDeliveredForReviewEmail();
        $this->assertSame($expected, $result);
    }

    /**
     * getDeliveredForReviewEmail retourne [] si aucune commande éligible.
     */
    public function testGetDeliveredForReviewEmailReturnsEmptyWhenNone(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->model->getDeliveredForReviewEmail());
    }

    // ----------------------------------------------------------------
    // markReviewEmailSent
    // ----------------------------------------------------------------

    /**
     * markReviewEmailSent appelle execute avec l'orderId.
     */
    public function testMarkReviewEmailSentCallsExecuteWithOrderId(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('review_request_sent_at'),
                [99]
            );

        $this->model->markReviewEmailSent(99);
    }

    // ----------------------------------------------------------------
    // hasOrdersByUserId
    // ----------------------------------------------------------------

    /**
     * hasOrdersByUserId retourne true si une commande existe.
     */
    public function testHasOrdersByUserIdReturnsTrueWhenOrderExists(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['1' => 1]);
        $this->assertTrue($this->model->hasOrdersByUserId(42));
    }

    /**
     * hasOrdersByUserId retourne false si aucune commande.
     */
    public function testHasOrdersByUserIdReturnsFalseWhenNoOrder(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);
        $this->assertFalse($this->model->hasOrdersByUserId(42));
    }

    // ----------------------------------------------------------------
    // getInvoicesForAdmin / countInvoicesForAdmin — branch search non vide
    // ----------------------------------------------------------------

    /**
     * getInvoicesForAdmin() avec $search non vide couvre les lignes L1044-1047
     * (ajout du filtre LIKE sur invoice_number et order_reference).
     *
     * @return void
     */
    public function testGetInvoicesForAdminWithSearchAddsLikeFilter(): void
    {
        $capturedParams = null;
        $this->dbMock->method('fetchAll')->willReturnCallback(
            function (string $sql, array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return [];
            }
        );

        $result = $this->model->getInvoicesForAdmin(2026, null, null, 1, 25, 'CB-2026');

        $this->assertSame([], $result);
        $this->assertNotNull($capturedParams, 'fetchAll doit être appelé');
        // Les 2 paramètres LIKE + LIMIT + OFFSET doivent être dans les params
        $likeParams = array_filter($capturedParams, fn($p) => is_string($p) && str_contains((string) $p, 'CB-2026'));
        $this->assertCount(2, $likeParams, 'Deux paramètres LIKE attendus pour invoice_number et order_reference');
    }

    /**
     * getInvoicesForAdmin() avec $search null ou vide ne modifie pas la requête.
     *
     * @return void
     */
    public function testGetInvoicesForAdminWithNullSearchDoesNotAddLike(): void
    {
        $capturedParams = null;
        $this->dbMock->method('fetchAll')->willReturnCallback(
            function (string $sql, array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return [];
            }
        );

        $this->model->getInvoicesForAdmin(2026, null, null, 1, 25, null);

        $this->assertNotNull($capturedParams);
        // Sans search, seuls LIMIT et OFFSET sont des entiers en fin de params
        $likeParams = array_filter(
            $capturedParams,
            fn($p) => is_string($p) && str_contains((string) $p, '%')
        );
        $this->assertCount(0, $likeParams, 'Aucun paramètre LIKE sans search');
    }

    /**
     * countInvoicesForAdmin() avec $search non vide couvre les lignes L1084-1087
     * (ajout du filtre LIKE sur invoice_number et order_reference).
     *
     * @return void
     */
    public function testCountInvoicesForAdminWithSearchAddsLikeFilter(): void
    {
        $capturedParams = null;
        $this->dbMock->method('fetchOne')->willReturnCallback(
            function (string $sql, array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return ['total' => '0'];
            }
        );

        $result = $this->model->countInvoicesForAdmin(2026, null, null, 'REF-TEST');

        $this->assertSame(0, $result);
        $this->assertNotNull($capturedParams);
        $likeParams = array_filter(
            $capturedParams,
            fn($p) => is_string($p) && str_contains((string) $p, 'REF-TEST')
        );
        $this->assertCount(2, $likeParams, 'Deux paramètres LIKE attendus pour invoice_number et order_reference');
    }

    /**
     * countInvoicesForAdmin() avec $search null ne modifie pas la requête.
     *
     * @return void
     */
    public function testCountInvoicesForAdminWithNullSearchDoesNotAddLike(): void
    {
        $capturedParams = null;
        $this->dbMock->method('fetchOne')->willReturnCallback(
            function (string $sql, array $params) use (&$capturedParams): array {
                $capturedParams = $params;
                return ['total' => '5'];
            }
        );

        $result = $this->model->countInvoicesForAdmin(2026, null, null, null);

        $this->assertSame(5, $result);
        $likeParams = array_filter(
            $capturedParams ?? [],
            fn($p) => is_string($p) && str_contains((string) $p, '%')
        );
        $this->assertCount(0, $likeParams);
    }
}
