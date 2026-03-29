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

    /**
     * Vérifie que les transactions imbriquées (nested savepoints) ne déclenchent
     * pas de beginTransaction() PDO tant que la profondeur est > 0.
     *
     * Cette branche couvre la ligne `if ($this->transactionDepth === 0)` dans
     * beginTransaction() (profondeur > 0 → PDO::beginTransaction non rappelé)
     * et la ligne correspondante dans commit() (profondeur > 0 → pas de commit PDO).
     *
     * @return void
     */
    public function testNestedBeginTransactionDoesNotCallPdoBeginTransactionTwice(): void
    {
        // On est déjà dans une transaction (setUp l'a démarrée via IntegrationTestCase).
        // Un beginTransaction() supplémentaire ne doit pas planter (profondeur incrémentée)
        // et le commit() correspondant ne doit pas déclencher le commit PDO.
        self::$db->beginTransaction(); // depth : 1 → 2 (pas d'appel PDO)

        // Opération dans la "sous-transaction"
        $id = self::$db->insert(
            "INSERT INTO accounts (email, password, lang, newsletter)
             VALUES (?, ?, 'fr', 0)",
            ['nested_tx@test.com', 'h']
        );
        $this->assertNotEmpty($id);

        self::$db->commit(); // depth : 2 → 1 (pas de commit PDO)

        // La ligne doit toujours être visible dans la transaction englobante
        $row = self::$db->fetchOne(
            "SELECT email FROM accounts WHERE email = ?",
            ['nested_tx@test.com']
        );
        $this->assertIsArray($row);
        $this->assertSame('nested_tx@test.com', $row['email']);
    }
}
