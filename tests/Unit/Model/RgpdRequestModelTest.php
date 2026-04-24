<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\RgpdRequestModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour RgpdRequestModel.
 * La BDD est mockée — aucune connexion réelle.
 */
#[AllowMockObjectsWithoutExpectations]
class RgpdRequestModelTest extends TestCase
{
    private Database $dbMock;
    private RgpdRequestModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new RgpdRequestModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    public function testValidTypesContainsFiveRights(): void
    {
        $this->assertCount(5, RgpdRequestModel::VALID_TYPES);
        $this->assertContains('acces', RgpdRequestModel::VALID_TYPES);
        $this->assertContains('rectification', RgpdRequestModel::VALID_TYPES);
        $this->assertContains('effacement', RgpdRequestModel::VALID_TYPES);
        $this->assertContains('portabilite', RgpdRequestModel::VALID_TYPES);
        $this->assertContains('opposition', RgpdRequestModel::VALID_TYPES);
    }

    public function testAlertDaysIs25(): void
    {
        $this->assertSame(25, RgpdRequestModel::ALERT_DAYS);
    }

    /**
     * isOverdue() retourne false quand received_at est absent (null).
     * Couvre RgpdRequestModel.php:280.
     */
    public function testIsOverdueReturnsFalseWhenReceivedAtIsNull(): void
    {
        $request = ['status' => 'nouvelle', 'received_at' => null];
        $this->assertFalse($this->model->isOverdue($request));
    }

    public function testValidStatusesContainsFourValues(): void
    {
        $this->assertCount(4, RgpdRequestModel::VALID_STATUSES);
        $this->assertContains('nouvelle', RgpdRequestModel::VALID_STATUSES);
        $this->assertContains('en_cours', RgpdRequestModel::VALID_STATUSES);
        $this->assertContains('traitee', RgpdRequestModel::VALID_STATUSES);
        $this->assertContains('refusee', RgpdRequestModel::VALID_STATUSES);
    }

    public function testCreateCallsInsert(): void
    {
        $this->dbMock->expects($this->once())
            ->method('insert')
            ->with($this->stringContains('INSERT INTO'), $this->arrayHasKey(0))
            ->willReturn('42');

        $id = $this->model->create('acces', 'test@example.com', 'Jean Dupont');
        $this->assertSame(42, $id);
    }

    public function testCreateThrowsOnInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->model->create('type_invalide', 'test@example.com', 'Jean Dupont');
    }

    public function testCreateAcceptsCustomDate(): void
    {
        $this->dbMock->expects($this->once())->method('insert')->willReturn('1');
        $id = $this->model->create('effacement', 'a@b.fr', 'Alice', '2026-01-15 10:00:00');
        $this->assertSame(1, $id);
    }

    public function testFindByIdReturnsFalseWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);
        $this->assertFalse($this->model->findById(999));
    }

    public function testFindByIdReturnsArrayWhenFound(): void
    {
        $row = ['id' => 1, 'type' => 'acces', 'status' => 'nouvelle'];
        $this->dbMock->method('fetchOne')->willReturn($row);
        $this->assertSame($row, $this->model->findById(1));
    }

    public function testGetAllReturnsEmptyArrayWhenDbReturnsEmpty(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);
        $this->assertSame([], $this->model->getAll());
    }

    public function testGetAllReturnsList(): void
    {
        $rows = [
            ['id' => 1, 'type' => 'acces', 'status' => 'nouvelle'],
            ['id' => 2, 'type' => 'effacement', 'status' => 'traitee'],
        ];
        $this->dbMock->method('fetchAll')->willReturn($rows);
        $this->assertCount(2, $this->model->getAll());
    }

    public function testCountReturnsZeroWhenDbReturnsFalse(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);
        $this->assertSame(0, $this->model->count());
    }

    public function testCountReturnsCorrectNumber(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(['cnt' => 7]);
        $this->assertSame(7, $this->model->count());
    }

    public function testUpdateStatusReturnsFalseForInvalidStatus(): void
    {
        $this->assertFalse($this->model->updateStatus(1, 'statut_inconnu', 1));
    }

    public function testUpdateStatusReturnsTrueWhenOneRowAffected(): void
    {
        $this->dbMock->method('execute')->willReturn(1);
        $this->assertTrue($this->model->updateStatus(1, 'en_cours', 2));
    }

    public function testUpdateStatusReturnsFalseWhenZeroRowsAffected(): void
    {
        $this->dbMock->method('execute')->willReturn(0);
        $this->assertFalse($this->model->updateStatus(999, 'traitee', 1));
    }

    public function testDeleteReturnsOneWhenRowDeleted(): void
    {
        $this->dbMock->method('execute')->willReturn(1);
        $this->assertSame(1, $this->model->delete(1));
    }

    public function testDeleteReturnsZeroWhenNoRowDeleted(): void
    {
        $this->dbMock->method('execute')->willReturn(0);
        $this->assertSame(0, $this->model->delete(999));
    }

    public function testGetOverdueRequestsReturnsEmptyArray(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);
        $this->assertSame([], $this->model->getOverdueRequests());
    }

    public function testIsOverdueReturnsFalseForTreatedRequest(): void
    {
        $request = ['status' => 'traitee', 'received_at' => date('Y-m-d H:i:s', strtotime('-30 days'))];
        $this->assertFalse($this->model->isOverdue($request));
    }

    public function testIsOverdueReturnsTrueWhenOver25Days(): void
    {
        $request = ['status' => 'nouvelle', 'received_at' => date('Y-m-d H:i:s', strtotime('-26 days'))];
        $this->assertTrue($this->model->isOverdue($request));
    }

    public function testIsOverdueReturnsFalseWhenUnder25Days(): void
    {
        $request = ['status' => 'en_cours', 'received_at' => date('Y-m-d H:i:s', strtotime('-10 days'))];
        $this->assertFalse($this->model->isOverdue($request));
    }
}
