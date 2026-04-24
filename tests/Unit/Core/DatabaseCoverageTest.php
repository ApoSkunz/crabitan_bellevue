<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests de couverture complémentaires pour Database.
 * Couvre la branche commit() → pdo->commit() (transactionDepth === 0 après décrémentation).
 */
class DatabaseCoverageTest extends TestCase
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
     * commit() appelle pdo->commit() quand transactionDepth atteint 0.
     * Couvre Database.php:137.
     */
    public function testCommitCallsPdoCommitWhenDepthReachesZero(): void
    {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())->method('commit');

        // Construire une instance Database sans connexion réelle via Reflection
        $rc       = new \ReflectionClass(Database::class);
        $instance = $rc->newInstanceWithoutConstructor();

        $pdoProp = $rc->getProperty('pdo');
        $pdoProp->setValue($instance, $pdoMock);

        $depthProp = $rc->getProperty('transactionDepth');
        $depthProp->setValue($instance, 1); // depth = 1 → commit decrements to 0 → pdo->commit()

        $this->instanceProp->setValue(null, $instance);

        $instance->commit();
    }

    /**
     * commit() ne call PAS pdo->commit() si transactionDepth > 0 après décrémentation.
     * Couvre la branche «imbrication» (depth reste > 0).
     */
    public function testCommitDoesNotCallPdoCommitWhenDepthRemainsPositive(): void
    {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->never())->method('commit');

        $rc       = new \ReflectionClass(Database::class);
        $instance = $rc->newInstanceWithoutConstructor();

        $pdoProp = $rc->getProperty('pdo');
        $pdoProp->setValue($instance, $pdoMock);

        $depthProp = $rc->getProperty('transactionDepth');
        $depthProp->setValue($instance, 2); // depth = 2 → decrements to 1 → no pdo->commit()

        $this->instanceProp->setValue(null, $instance);

        $instance->commit();
    }
}
