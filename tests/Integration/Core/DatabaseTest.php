<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Core\Database;
use Tests\Integration\IntegrationTestCase;

class DatabaseTest extends IntegrationTestCase
{
    public function testGetInstanceReturnsSameInstance(): void
    {
        $a = Database::getInstance();
        $b = Database::getInstance();
        $this->assertSame($a, $b);
    }

    public function testInsertAndFetchOne(): void
    {
        $id = self::$db->insert(
            "INSERT INTO accounts (email, lang, newsletter)
             VALUES (?, ?, ?)",
            ['db@test.com', 'fr', 0]
        );

        $this->assertNotEmpty($id);

        $row = self::$db->fetchOne("SELECT * FROM accounts WHERE id = ?", [(int)$id]);
        $this->assertIsArray($row);
        $this->assertSame('db@test.com', $row['email']);
    }

    public function testFetchAll(): void
    {
        self::$db->insert(
            "INSERT INTO accounts (email, lang, newsletter)
             VALUES (?, ?, ?)",
            ['all1@test.com', 'fr', 0]
        );
        self::$db->insert(
            "INSERT INTO accounts (email, lang, newsletter)
             VALUES (?, ?, ?)",
            ['all2@test.com', 'fr', 0]
        );

        $rows = self::$db->fetchAll(
            "SELECT * FROM accounts WHERE email IN (?, ?)",
            ['all1@test.com', 'all2@test.com']
        );

        $this->assertCount(2, $rows);
    }

    public function testExecuteReturnsRowCount(): void
    {
        $id = self::$db->insert(
            "INSERT INTO accounts (email, lang, newsletter)
             VALUES (?, ?, ?)",
            ['delete@test.com', 'fr', 0]
        );

        $affected = self::$db->execute("DELETE FROM accounts WHERE id = ?", [(int)$id]);
        $this->assertSame(1, $affected);
    }

    public function testFetchOneReturnsfalseIfNotFound(): void
    {
        $result = self::$db->fetchOne("SELECT * FROM accounts WHERE id = ?", [999999]);
        $this->assertFalse($result);
    }

    public function testTransactionRollback(): void
    {
        // Cette méthode vérifie que le rollback du setUp fonctionne
        // en s'assurant que les données insérées dans d'autres tests
        // ne sont pas visibles ici (isolation garantie)
        $result = self::$db->fetchOne(
            "SELECT * FROM accounts WHERE email = ?",
            ['db@test.com']
        );
        // Dans ce test indépendant, l'email ne doit pas exister
        // car chaque test roule dans sa propre transaction
        $this->assertFalse($result);
    }
}
