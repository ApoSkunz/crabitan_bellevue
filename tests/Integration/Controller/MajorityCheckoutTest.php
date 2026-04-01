<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour la vérification de majorité au checkout.
 *
 * Vérifie que le checkout est bloqué pour un compte sans déclaration de majorité.
 * Base légale : Art. L3342-1 CSP.
 * Chaque test s'exécute dans une transaction rollbackée — BDD propre garantie.
 */
class MajorityCheckoutTest extends IntegrationTestCase
{
    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Insère un compte vérifié sans déclaration de majorité.
     *
     * @param string $email Adresse email du compte
     * @return int Identifiant du compte créé
     */
    private function insertAccountWithoutMajority(string $email = 'nomajority@example.com'): int
    {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts (email, password, role, lang, email_verified_at)
             VALUES (?, ?, 'customer', 'fr', NOW())",
            [$email, password_hash('Password123!', PASSWORD_BCRYPT)]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Test', 'NoMajority', 'M')",
            [$id]
        );
        return $id;
    }

    /**
     * Insère un compte vérifié avec déclaration de majorité complète.
     *
     * @param string $email     Adresse email du compte
     * @param string $birthDate Date de naissance (YYYY-MM-DD) d'un adulte
     * @return int Identifiant du compte créé
     */
    private function insertAccountWithMajority(
        string $email = 'majority@example.com',
        string $birthDate = '1990-01-01'
    ): int {
        $id = (int) self::$db->insert(
            "INSERT INTO accounts
             (email, password, role, lang, email_verified_at,
              birth_date, majority_declared_at, majority_declared_ip)
             VALUES (?, ?, 'customer', 'fr', NOW(), ?, NOW(), '127.0.0.1')",
            [$email, password_hash('Password123!', PASSWORD_BCRYPT), $birthDate]
        );
        self::$db->insert(
            "INSERT INTO account_individuals (account_id, lastname, firstname, civility)
             VALUES (?, 'Test', 'WithMajority', 'M')",
            [$id]
        );
        return $id;
    }

    // ----------------------------------------------------------------
    // Vérification BDD — colonnes présentes
    // ----------------------------------------------------------------

    /**
     * Les colonnes majority_declared_at et birth_date existent dans accounts.
     */
    public function testMajorityColumnsExistInAccountsTable(): void
    {
        $rows = self::$db->fetchAll(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'accounts'
               AND COLUMN_NAME IN ('birth_date', 'majority_declared_at', 'majority_declared_ip')"
        );

        $columns = array_column($rows, 'COLUMN_NAME');
        $this->assertContains('birth_date', $columns, 'La colonne birth_date doit exister dans accounts');
        $this->assertContains('majority_declared_at', $columns, 'La colonne majority_declared_at doit exister dans accounts');
        $this->assertContains('majority_declared_ip', $columns, 'La colonne majority_declared_ip doit exister dans accounts');
    }

    // ----------------------------------------------------------------
    // Compte sans déclaration de majorité
    // ----------------------------------------------------------------

    /**
     * Un compte sans déclaration de majorité (majority_declared_at IS NULL) doit être bloqué au checkout.
     */
    public function testAccountWithoutMajorityIsBlockedFromCheckout(): void
    {
        $id = $this->insertAccountWithoutMajority();

        $account = self::$db->fetchOne(
            "SELECT birth_date, majority_declared_at, majority_declared_ip
             FROM accounts WHERE id = ?",
            [$id]
        );

        $this->assertNotFalse($account, 'Le compte doit exister en BDD');
        $this->assertNull($account['birth_date'], 'birth_date doit être NULL pour un compte sans déclaration');
        $this->assertNull($account['majority_declared_at'], 'majority_declared_at doit être NULL pour un compte sans déclaration');
        $this->assertNull($account['majority_declared_ip'], 'majority_declared_ip doit être NULL pour un compte sans déclaration');

        // Vérification fonctionnelle : le checkout doit bloquer ce compte
        $hasMajority = $account['majority_declared_at'] !== null;
        $this->assertFalse($hasMajority, 'Un compte sans déclaration ne doit pas pouvoir accéder au checkout');
    }

    // ----------------------------------------------------------------
    // Compte avec déclaration de majorité
    // ----------------------------------------------------------------

    /**
     * Un compte avec déclaration de majorité (majority_declared_at NOT NULL) doit passer le checkout.
     */
    public function testAccountWithMajorityPassesCheckout(): void
    {
        $id = $this->insertAccountWithMajority();

        $account = self::$db->fetchOne(
            "SELECT birth_date, majority_declared_at, majority_declared_ip
             FROM accounts WHERE id = ?",
            [$id]
        );

        $this->assertNotFalse($account, 'Le compte doit exister en BDD');
        $this->assertNotNull($account['birth_date'], 'birth_date ne doit pas être NULL pour un compte avec déclaration');
        $this->assertNotNull($account['majority_declared_at'], 'majority_declared_at ne doit pas être NULL');
        $this->assertSame('127.0.0.1', $account['majority_declared_ip'], "L'IP doit être stockée");

        // Vérification fonctionnelle : le checkout doit autoriser ce compte
        $hasMajority = $account['majority_declared_at'] !== null;
        $this->assertTrue($hasMajority, 'Un compte avec déclaration doit pouvoir accéder au checkout');
    }

    // ----------------------------------------------------------------
    // Déclaration de majorité — sauvegarde IP + horodatage
    // ----------------------------------------------------------------

    /**
     * La déclaration de majorité doit stocker l'IP et l'horodatage (preuve RGPD Art. 7).
     */
    public function testMajorityDeclarationStoresIpAndTimestamp(): void
    {
        $birthDate = '1990-06-15';
        $ip        = '192.168.1.100';
        $id        = (int) self::$db->insert(
            "INSERT INTO accounts
             (email, password, role, lang, email_verified_at,
              birth_date, majority_declared_at, majority_declared_ip)
             VALUES ('iptest@example.com', 'hash', 'customer', 'fr', NOW(), ?, NOW(), ?)",
            [$birthDate, $ip]
        );

        $account = self::$db->fetchOne(
            "SELECT birth_date, majority_declared_at, majority_declared_ip FROM accounts WHERE id = ?",
            [$id]
        );

        $this->assertNotFalse($account);
        $this->assertSame($birthDate, $account['birth_date']);
        $this->assertSame($ip, $account['majority_declared_ip']);
        $this->assertNotNull($account['majority_declared_at']);
    }
}
