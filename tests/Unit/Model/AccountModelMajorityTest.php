<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\AccountModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour AccountModel::hasDeclaredMajority() et declareMajority().
 */
class AccountModelMajorityTest extends TestCase
{
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    /**
     * Crée un stub DB configuré et retourne [stub, model].
     *
     * Le modèle est instancié APRÈS le stub pour que Database::getInstance()
     * capture la bonne instance.
     *
     * @return array{0: \PHPUnit\Framework\MockObject\Stub, 1: AccountModel}
     */
    private function makeStubAndModel(): array
    {
        $stub = $this->createStub(Database::class);
        $this->instanceProp->setValue(null, $stub);
        return [$stub, new AccountModel()];
    }

    // ----------------------------------------------------------------
    // hasDeclaredMajority
    // ----------------------------------------------------------------

    /**
     * Compte avec majority_declared_at renseigné → true.
     */
    public function testHasDeclaredMajorityReturnsTrueWhenDeclared(): void
    {
        [$stub, $model] = $this->makeStubAndModel();
        $stub->method('fetchOne')->willReturn(['majority_declared_at' => '2025-01-01 00:00:00']);

        $this->assertTrue($model->hasDeclaredMajority(1));
    }

    /**
     * Compte avec majority_declared_at = null → false.
     */
    public function testHasDeclaredMajorityReturnsFalseWhenNull(): void
    {
        [$stub, $model] = $this->makeStubAndModel();
        $stub->method('fetchOne')->willReturn(['majority_declared_at' => null]);

        $this->assertFalse($model->hasDeclaredMajority(1));
    }

    /**
     * Compte inexistant (fetchOne retourne false) → false.
     */
    public function testHasDeclaredMajorityReturnsFalseWhenAccountNotFound(): void
    {
        [$stub, $model] = $this->makeStubAndModel();
        $stub->method('fetchOne')->willReturn(false);

        $this->assertFalse($model->hasDeclaredMajority(999));
    }

    // ----------------------------------------------------------------
    // declareMajority
    // ----------------------------------------------------------------

    /**
     * declareMajority exécute l'UPDATE sans lever d'exception.
     */
    public function testDeclareMajorityExecutesUpdate(): void
    {
        [$stub, $model] = $this->makeStubAndModel();
        $stub->method('execute')->willReturn(1);

        // Ne doit pas lever d'exception
        $model->declareMajority(1, '127.0.0.1');
        $this->assertTrue(true); // assertion sentinelle
    }
}
