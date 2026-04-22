<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\WineNutritionModel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Tests unitaires pour WineNutritionModel.
 * La base de données est mockée via Database — aucune connexion réelle requise.
 */
#[AllowMockObjectsWithoutExpectations]
class WineNutritionModelTest extends TestCase
{
    private Database $dbMock;
    private WineNutritionModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(Database::class);

        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new WineNutritionModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ── findByWineId ────────────────────────────────────────────────────────

    /**
     * Vérifie que findByWineId retourne un tableau si la ligne existe.
     */
    public function testFindByWineIdReturnsRowWhenFound(): void
    {
        $expected = ['id' => 1, 'wine_id' => 42, 'energy_kj' => '336', 'alcohol_degree' => '12,5'];

        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($expected);

        $result = $this->model->findByWineId(42);

        $this->assertSame($expected, $result);
    }

    /**
     * Vérifie que findByWineId retourne false si aucune ligne n'existe.
     */
    public function testFindByWineIdReturnsFalseWhenNotFound(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->model->findByWineId(99);

        $this->assertFalse($result);
    }

    // ── upsert ──────────────────────────────────────────────────────────────

    /**
     * Vérifie que upsert exécute une requête INSERT … ON DUPLICATE KEY UPDATE
     * quand au moins un champ est non vide.
     */
    public function testUpsertExecutesQueryWhenDataPresent(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('execute');

        $this->model->upsert(1, ['energy_kj' => '336']);
    }

    /**
     * Vérifie que upsert n'exécute aucune requête si tous les champs sont vides.
     */
    public function testUpsertDoesNothingWhenAllFieldsEmpty(): void
    {
        $this->dbMock
            ->expects($this->never())
            ->method('execute');

        $this->model->upsert(1, [
            'energy_kj' => '', 'fat_g' => '',
            'saturated_fat_g' => '', 'carbohydrate_g' => '', 'sugars_g' => '',
            'protein_g' => '', 'salt_g' => '', 'ingredients' => '',
        ]);
    }

    /**
     * Vérifie que upsert accepte les valeurs contenant "<" (ex: "< 0,2").
     */
    public function testUpsertAcceptsLessThanValues(): void
    {
        $capturedParams = [];

        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (string $sql, array $params) use (&$capturedParams): int {
                $capturedParams = $params;
                return 1;
            });

        $this->model->upsert(5, ['protein_g' => '< 0,2', 'salt_g' => '< 0,2']);

        $this->assertContains('< 0,2', $capturedParams);
    }

    /**
     * Vérifie que upsert ignore les champs inconnus du POST.
     */
    public function testUpsertIgnoresUnknownFields(): void
    {
        $capturedSql = '';

        $this->dbMock
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (string $sql, array $params) use (&$capturedSql): int {
                $capturedSql = $sql;
                return 1;
            });

        $this->model->upsert(3, [
            'energy_kj'    => '336',
            'unknown_field' => 'hack',
            'injection'    => 'DROP TABLE',
        ]);

        $this->assertStringNotContainsString('unknown_field', $capturedSql);
        $this->assertStringNotContainsString('injection', $capturedSql);
    }
}
