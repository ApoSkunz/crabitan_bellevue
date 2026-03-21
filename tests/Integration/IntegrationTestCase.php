<?php

declare(strict_types=1);

namespace Tests\Integration;

use Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Base class pour tous les tests d'intégration.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static Database $db;

    public static function setUpBeforeClass(): void
    {
        self::$db = Database::getInstance();
    }

    protected function setUp(): void
    {
        self::$db->beginTransaction();
    }

    protected function tearDown(): void
    {
        self::$db->rollback();
    }

    /**
     * Réinitialise le singleton Database entre les classes de test.
     */
    public static function tearDownAfterClass(): void
    {
        $reflection = new \ReflectionClass(Database::class);
        $prop = $reflection->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}
