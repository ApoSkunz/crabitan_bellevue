<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Core\Database;
use Model\LegalDocumentModel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour LegalDocumentModel.
 * La BDD est mockée — aucune connexion réelle.
 */
#[AllowMockObjectsWithoutExpectations]
class LegalDocumentModelTest extends TestCase
{
    private Database $dbMock;
    private LegalDocumentModel $model;
    private \ReflectionProperty $instanceProp;

    protected function setUp(): void
    {
        $this->dbMock       = $this->createMock(Database::class);
        $this->instanceProp = new \ReflectionProperty(Database::class, 'instance');
        $this->instanceProp->setValue(null, $this->dbMock);

        $this->model = new LegalDocumentModel();
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    // ----------------------------------------------------------------
    // findAll()
    // ----------------------------------------------------------------

    /**
     * findAll() retourne un tableau indexé par slug.
     */
    public function testFindAllIndexesBySlug(): void
    {
        $rows = [
            ['slug' => 'cgv', 'label' => 'CGV', 'version' => '1.0', 'updated_at' => '2026-04-04',
             'requires_notification' => 1, 'last_notified_at' => null, 'notified_count' => 0,
             'updated_by' => null, 'created_at' => '2026-04-04 00:00:00'],
            ['slug' => 'mentions_legales', 'label' => 'Mentions', 'version' => '1.1', 'updated_at' => '2026-04-07',
             'requires_notification' => 0, 'last_notified_at' => null, 'notified_count' => 0,
             'updated_by' => null, 'created_at' => '2026-04-07 00:00:00'],
        ];

        $this->dbMock->method('fetchAll')->willReturn($rows);

        $result = $this->model->findAll();

        $this->assertArrayHasKey('cgv', $result);
        $this->assertArrayHasKey('mentions_legales', $result);
        $this->assertSame('1.0', $result['cgv']['version']);
        $this->assertSame('1.1', $result['mentions_legales']['version']);
    }

    /**
     * findAll() retourne un tableau vide si fetchAll renvoie [].
     */
    public function testFindAllReturnsEmptyArrayWhenNoRows(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->model->findAll());
    }

    // ----------------------------------------------------------------
    // findBySlug()
    // ----------------------------------------------------------------

    /**
     * findBySlug() retourne le document correspondant.
     */
    public function testFindBySlugReturnsDocument(): void
    {
        $row = ['id' => 1, 'slug' => 'cgv', 'version' => '1.0', 'updated_at' => '2026-04-04',
                'requires_notification' => 1, 'label' => 'CGV',
                'last_notified_at' => null, 'notified_count' => 0,
                'updated_by' => null, 'created_at' => '2026-04-04 00:00:00'];

        $this->dbMock->method('fetchOne')->willReturn($row);

        $result = $this->model->findBySlug('cgv');

        $this->assertNotNull($result);
        $this->assertSame('cgv', $result['slug']);
    }

    /**
     * findBySlug() retourne null si le slug n'existe pas.
     */
    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        $this->dbMock->method('fetchOne')->willReturn(false);

        $this->assertNull($this->model->findBySlug('inexistant'));
    }

    // ----------------------------------------------------------------
    // update()
    // ----------------------------------------------------------------

    /**
     * update() exécute un UPDATE et retourne true si une ligne est modifiée.
     */
    public function testUpdateReturnsTrueOnSuccess(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('UPDATE'),
                ['1.2', '2026-05-01', 3, 'cgv']
            )
            ->willReturn(1);

        $this->assertTrue($this->model->update('cgv', '1.2', '2026-05-01', 3));
    }

    /**
     * update() retourne false si aucune ligne n'est modifiée.
     */
    public function testUpdateReturnsFalseWhenNoRowAffected(): void
    {
        $this->dbMock->method('execute')->willReturn(0);

        $this->assertFalse($this->model->update('inexistant', '1.0', '2026-04-01', 1));
    }

    // ----------------------------------------------------------------
    // findEligibleAccounts()
    // ----------------------------------------------------------------

    /**
     * findEligibleAccounts() retourne les comptes éligibles pour cgv.
     */
    public function testFindEligibleAccountsCgv(): void
    {
        $rows = [
            ['id' => 1, 'email' => 'a@test.com', 'lang' => 'fr', 'display_name' => 'A B'],
        ];
        $this->dbMock->method('fetchAll')->willReturn($rows);

        $result = $this->model->findEligibleAccounts('cgv');

        $this->assertCount(1, $result);
        $this->assertSame('a@test.com', $result[0]['email']);
    }

    /**
     * findEligibleAccounts() retourne [] pour un slug sans notification.
     */
    public function testFindEligibleAccountsReturnsEmptyForInternalDoc(): void
    {
        $result = $this->model->findEligibleAccounts('mentions_legales');

        $this->assertSame([], $result);
    }

    // ----------------------------------------------------------------
    // logNotification()
    // ----------------------------------------------------------------

    /**
     * logNotification() exécute un INSERT IGNORE.
     */
    public function testLogNotificationExecutesInsertIgnore(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('INSERT IGNORE'),
                [1, '1.2', 42]
            );

        $this->model->logNotification(1, '1.2', 42);
    }

    // ----------------------------------------------------------------
    // updateNotificationStats()
    // ----------------------------------------------------------------

    /**
     * updateNotificationStats() met à jour last_notified_at et notified_count.
     */
    public function testUpdateNotificationStatsExecutesUpdate(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('last_notified_at'),
                [5, 1]
            );

        $this->model->updateNotificationStats(1, 5);
    }

    // ----------------------------------------------------------------
    // logHistory()
    // ----------------------------------------------------------------

    /**
     * logHistory() insère une entrée dans legal_document_history.
     */
    public function testLogHistoryExecutesInsert(): void
    {
        $this->dbMock->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('legal_document_history'),
                [1, '1.2', '2026-04-10', 3]
            );

        $this->model->logHistory(1, '1.2', '2026-04-10', 3);
    }

    // ----------------------------------------------------------------
    // findHistoryByDocumentId()
    // ----------------------------------------------------------------

    /**
     * findHistoryByDocumentId() retourne les entrées triées du plus récent au plus ancien.
     */
    public function testFindHistoryByDocumentIdReturnsSortedRows(): void
    {
        $rows = [
            ['version' => '1.2', 'updated_at' => '2026-04-10', 'created_at' => '2026-04-10 12:00:00', 'updated_by_name' => 'Admin'],
            ['version' => '1.1', 'updated_at' => '2026-04-05', 'created_at' => '2026-04-05 10:00:00', 'updated_by_name' => 'Admin'],
        ];

        $this->dbMock->method('fetchAll')->willReturn($rows);

        $result = $this->model->findHistoryByDocumentId(1);

        $this->assertCount(2, $result);
        $this->assertSame('1.2', $result[0]['version']);
        $this->assertSame('1.1', $result[1]['version']);
    }

    /**
     * findHistoryByDocumentId() retourne [] si aucune entrée.
     */
    public function testFindHistoryByDocumentIdReturnsEmptyArray(): void
    {
        $this->dbMock->method('fetchAll')->willReturn([]);

        $this->assertSame([], $this->model->findHistoryByDocumentId(99));
    }
}
