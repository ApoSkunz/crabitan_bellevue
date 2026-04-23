<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\ClickCollectSettingsModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ClickCollectSettingsModel.
 * Couvre getDiscountPerBottle() et setDiscountPerBottle().
 */
#[AllowMockObjectsWithoutExpectations]
class ClickCollectSettingsModelTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    /**
     * Crée un mock strict Database et ClickCollectSettingsModel.
     *
     * @return array{0: \PHPUnit\Framework\MockObject\MockObject, 1: ClickCollectSettingsModel}
     */
    private function makeMockAndModel(): array
    {
        $mock = $this->createMock(Database::class);
        $this->instanceProp->setValue(null, $mock);
        return [$mock, new ClickCollectSettingsModel()];
    }

    // ----------------------------------------------------------------
    // getDiscountPerBottle()
    // ----------------------------------------------------------------

    /**
     * getDiscountPerBottle() retourne la valeur stockée castée en float.
     */
    public function testGetDiscountReturnsStoredValue(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('SELECT discount_per_bottle'))
            ->willReturn(['discount_per_bottle' => '3.50']);

        $this->assertSame(3.5, $model->getDiscountPerBottle());
    }

    /**
     * getDiscountPerBottle() retourne 2.20 (défaut) quand la ligne est absente.
     */
    public function testGetDiscountReturnsDefaultWhenRowMissing(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->method('fetchOne')->willReturn(false);

        $this->assertSame(2.20, $model->getDiscountPerBottle());
    }

    // ----------------------------------------------------------------
    // setDiscountPerBottle()
    // ----------------------------------------------------------------

    /**
     * setDiscountPerBottle() arrondi à 2 décimales et exécute l'upsert.
     */
    public function testSetDiscountRoundsAndExecutesUpsert(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('ON DUPLICATE KEY UPDATE'),
                $this->callback(function (array $params): bool {
                    $this->assertSame(2.35, $params[0]);
                    return true;
                })
            );

        $model->setDiscountPerBottle(2.349);
    }

    /**
     * setDiscountPerBottle() clamp les valeurs négatives à 0.
     */
    public function testSetDiscountClampsNegativeToZero(): void
    {
        [$mock, $model] = $this->makeMockAndModel();

        $mock->expects($this->once())
            ->method('execute')
            ->with(
                $this->anything(),
                $this->callback(function (array $params): bool {
                    $this->assertSame(0.0, $params[0]);
                    return true;
                })
            );

        $model->setDiscountPerBottle(-5.0);
    }
}
