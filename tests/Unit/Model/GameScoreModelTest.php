<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\GameScoreModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour GameScoreModel.
 * La BDD est mockée — aucune connexion réelle requise.
 */
#[AllowMockObjectsWithoutExpectations]
class GameScoreModelTest extends TestCase
{
    private Database $dbMock;
    private GameScoreModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new GameScoreModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // getBestScore
    // ----------------------------------------------------------------

    /**
     * Retourne le score entier quand une ligne est trouvée en BDD.
     *
     * @return void
     */
    public function testGetBestScoreReturnsScoreWhenRowFound(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(['score' => 42]);

        $result = $this->model->getBestScore('memo');

        $this->assertSame(42, $result);
    }

    /**
     * Retourne 0 quand aucune ligne n'est trouvée en BDD.
     *
     * @return void
     */
    public function testGetBestScoreReturnsZeroWhenNoRow(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->model->getBestScore('memo');

        $this->assertSame(0, $result);
    }

    // ----------------------------------------------------------------
    // updateIfBetter
    // ----------------------------------------------------------------

    /**
     * Retourne false et n'exécute pas l'INSERT quand le score soumis
     * est inférieur ou égal au record actuel.
     *
     * @return void
     */
    public function testUpdateIfBetterReturnsFalseWhenScoreNotBetter(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(['score' => 100]);

        $this->dbMock->expects($this->never())->method('execute');

        $result = $this->model->updateIfBetter('memo', 50);

        $this->assertFalse($result);
    }

    /**
     * Retourne false quand le score soumis est égal au record actuel.
     *
     * @return void
     */
    public function testUpdateIfBetterReturnsFalseWhenScoreEqual(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(['score' => 75]);

        $this->dbMock->expects($this->never())->method('execute');

        $result = $this->model->updateIfBetter('memo', 75);

        $this->assertFalse($result);
    }

    /**
     * Retourne true et exécute l'UPSERT quand le score soumis
     * est strictement supérieur au record actuel.
     *
     * @return void
     */
    public function testUpdateIfBetterReturnsTrueAndExecutesWhenScoreHigher(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(['score' => 30]);

        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('ON DUPLICATE KEY UPDATE'),
                $this->equalTo(['memo', 99, 99])
            );

        $result = $this->model->updateIfBetter('memo', 99);

        $this->assertTrue($result);
    }

    /**
     * Retourne true quand il n'y a aucun score existant (getBestScore retourne 0).
     *
     * @return void
     */
    public function testUpdateIfBetterReturnsTrueWhenNoPreviousScore(): void
    {
        $this->dbMock
            ->method('fetchOne')
            ->willReturn(false);

        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $result = $this->model->updateIfBetter('memo', 1);

        $this->assertTrue($result);
    }
}
