<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\AccountModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour AccountModel::getAccountsPendingDeletionReminder()
 * et AccountModel::markDeletionReminderSent().
 */
class AccountModelDeletionReminderTest extends IntegrationTestCase
{
    private AccountModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new AccountModel();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return int userId
     */
    private function createAccountWithScheduledDeletion(string $email, int $daysFromNow): int
    {
        $id = (int) $this->model->create(
            'individual',
            $email,
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Dupont',
            'Jean',
            ''
        );

        // Simuler une demande de suppression : deleted_at + scheduled_deletion_at
        self::$db->execute(
            "UPDATE accounts
             SET deleted_at = NOW(),
                 scheduled_deletion_at = DATE_ADD(NOW(), INTERVAL ? DAY),
                 reactivation_token = ?
             WHERE id = ?",
            [$daysFromNow, bin2hex(random_bytes(16)), $id]
        );

        return $id;
    }

    // ------------------------------------------------------------------
    // getAccountsPendingDeletionReminder — J-7
    // ------------------------------------------------------------------

    /**
     * Détecte un compte dont scheduled_deletion_at est dans ~7 jours (fenêtre ±12h).
     */
    public function testGetPendingReminderDetects7DayWindow(): void
    {
        $id = $this->createAccountWithScheduledDeletion('reminder7@test.local', 7);

        $rows = $this->model->getAccountsPendingDeletionReminder(7);

        $ids = array_column($rows, 'id');
        $this->assertContains($id, $ids, 'Le compte J-7 doit être détecté dans la fenêtre');
    }

    /**
     * N'inclut PAS un compte dont scheduled_deletion_at est dans 14 jours (hors fenêtre J-7).
     */
    public function testGetPendingReminderExcludesOutOfWindow(): void
    {
        $id = $this->createAccountWithScheduledDeletion('reminder14@test.local', 14);

        $rows = $this->model->getAccountsPendingDeletionReminder(7);

        $ids = array_column($rows, 'id');
        $this->assertNotContains($id, $ids, 'Un compte J-14 ne doit pas être inclus dans la fenêtre J-7');
    }

    /**
     * N'inclut PAS un compte dont le rappel J-7 a déjà été envoyé.
     */
    public function testGetPendingReminderExcludesAlreadySent7d(): void
    {
        $id = $this->createAccountWithScheduledDeletion('reminder7sent@test.local', 7);

        // Simuler un rappel déjà envoyé
        self::$db->execute(
            "UPDATE accounts SET deletion_reminder_7d_sent_at = NOW() WHERE id = ?",
            [$id]
        );

        $rows = $this->model->getAccountsPendingDeletionReminder(7);

        $ids = array_column($rows, 'id');
        $this->assertNotContains($id, $ids, 'Un rappel déjà envoyé ne doit pas être relancé');
    }

    /**
     * N'inclut PAS un compte non marqué pour suppression (deleted_at IS NULL).
     */
    public function testGetPendingReminderExcludesActiveAccounts(): void
    {
        $id = (int) $this->model->create(
            'individual',
            'active@test.local',
            password_hash('pass', PASSWORD_BCRYPT),
            'fr',
            0,
            bin2hex(random_bytes(16)),
            'M',
            'Actif',
            'User',
            ''
        );

        $rows = $this->model->getAccountsPendingDeletionReminder(7);

        $ids = array_column($rows, 'id');
        $this->assertNotContains($id, $ids, 'Un compte actif ne doit pas être inclus');
    }

    // ------------------------------------------------------------------
    // getAccountsPendingDeletionReminder — J-1
    // ------------------------------------------------------------------

    /**
     * Détecte un compte dont scheduled_deletion_at est dans ~1 jour (fenêtre ±12h).
     */
    public function testGetPendingReminderDetects1DayWindow(): void
    {
        $id = $this->createAccountWithScheduledDeletion('reminder1@test.local', 1);

        $rows = $this->model->getAccountsPendingDeletionReminder(1);

        $ids = array_column($rows, 'id');
        $this->assertContains($id, $ids, 'Le compte J-1 doit être détecté dans la fenêtre');
    }

    /**
     * N'inclut PAS un compte dont le rappel J-1 a déjà été envoyé.
     */
    public function testGetPendingReminderExcludesAlreadySent1d(): void
    {
        $id = $this->createAccountWithScheduledDeletion('reminder1sent@test.local', 1);

        self::$db->execute(
            "UPDATE accounts SET deletion_reminder_1d_sent_at = NOW() WHERE id = ?",
            [$id]
        );

        $rows = $this->model->getAccountsPendingDeletionReminder(1);

        $ids = array_column($rows, 'id');
        $this->assertNotContains($id, $ids);
    }

    // ------------------------------------------------------------------
    // getAccountsPendingDeletionReminder — validation
    // ------------------------------------------------------------------

    /**
     * Lève une InvalidArgumentException pour une valeur daysAhead invalide.
     */
    public function testGetPendingReminderThrowsForInvalidDays(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->model->getAccountsPendingDeletionReminder(3);
    }

    // ------------------------------------------------------------------
    // markDeletionReminderSent
    // ------------------------------------------------------------------

    /**
     * markDeletionReminderSent(7) renseigne deletion_reminder_7d_sent_at.
     */
    public function testMarkReminderSent7d(): void
    {
        $id = $this->createAccountWithScheduledDeletion('mark7d@test.local', 7);

        $this->model->markDeletionReminderSent($id, 7);

        $row = self::$db->fetchOne(
            "SELECT deletion_reminder_7d_sent_at FROM accounts WHERE id = ?",
            [$id]
        );
        $this->assertNotNull($row['deletion_reminder_7d_sent_at'], 'deletion_reminder_7d_sent_at doit être renseigné');
    }

    /**
     * markDeletionReminderSent(1) renseigne deletion_reminder_1d_sent_at.
     */
    public function testMarkReminderSent1d(): void
    {
        $id = $this->createAccountWithScheduledDeletion('mark1d@test.local', 1);

        $this->model->markDeletionReminderSent($id, 1);

        $row = self::$db->fetchOne(
            "SELECT deletion_reminder_1d_sent_at FROM accounts WHERE id = ?",
            [$id]
        );
        $this->assertNotNull($row['deletion_reminder_1d_sent_at'], 'deletion_reminder_1d_sent_at doit être renseigné');
    }

    /**
     * markDeletionReminderSent lève une InvalidArgumentException pour une valeur invalide.
     */
    public function testMarkReminderSentThrowsForInvalidDays(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->model->markDeletionReminderSent(1, 5);
    }
}
