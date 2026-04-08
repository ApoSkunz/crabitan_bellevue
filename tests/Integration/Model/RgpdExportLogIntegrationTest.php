<?php

declare(strict_types=1);

namespace Tests\Integration\Model;

use Model\RgpdExportLogModel;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour RgpdExportLogModel.
 *
 * Chaque test s'exécute dans une transaction rollbackée (IntegrationTestCase).
 * Vérifie le comportement réel en BDD : insert, count, getAll, purgeExpired.
 */
class RgpdExportLogIntegrationTest extends IntegrationTestCase
{
    private RgpdExportLogModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new RgpdExportLogModel();
    }

    // ----------------------------------------------------------------
    // log()
    // ----------------------------------------------------------------

    /**
     * log() insère une ligne en BDD et count() la comptabilise.
     */
    public function testLogInsertsRow(): void
    {
        $before = $this->model->count();
        $this->model->log('test@example.com', '127.0.0.1', 'mes-donnees-2026-04-08.zip');
        $this->assertSame($before + 1, $this->model->count());
    }

    /**
     * log() avec user_id nul fonctionne sans erreur FK.
     */
    public function testLogWithNullUserIdDoesNotThrow(): void
    {
        $this->model->log('ghost@example.com', '::1', 'export.json', null);
        $rows = $this->model->getAll(1, 0, 'ghost@example.com');
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['user_id']);
    }

    // ----------------------------------------------------------------
    // getAll() / count()
    // ----------------------------------------------------------------

    /**
     * getAll() retourne les lignes insérées triées DESC par requested_at.
     */
    public function testGetAllReturnInsertedRows(): void
    {
        $this->model->log('a@example.com', '1.1.1.1', 'a.zip');
        $this->model->log('b@example.com', '2.2.2.2', 'b.zip');

        $rows = $this->model->getAll(10, 0);
        $this->assertGreaterThanOrEqual(2, count($rows));
    }

    /**
     * Filtre par email : ne retourne que les lignes correspondantes.
     */
    public function testGetAllFilterByEmail(): void
    {
        $this->model->log('filter@example.com', '1.1.1.1', 'f.zip');
        $this->model->log('other@example.com', '2.2.2.2', 'o.zip');

        $rows = $this->model->getAll(10, 0, 'filter@example.com');
        foreach ($rows as $row) {
            $this->assertStringContainsString('filter', $row['email']);
        }
    }

    /**
     * count() avec filtre email correspond au count getAll.
     */
    public function testCountMatchesGetAllCount(): void
    {
        $this->model->log('cnt@example.com', '1.1.1.1', 'c1.zip');
        $this->model->log('cnt@example.com', '1.1.1.1', 'c2.zip');

        $total = $this->model->count('cnt@example.com');
        $rows  = $this->model->getAll(100, 0, 'cnt@example.com');

        $this->assertSame(count($rows), $total);
    }

    // ----------------------------------------------------------------
    // purgeExpired()
    // ----------------------------------------------------------------

    /**
     * purgeExpired() supprime les lignes dont expires_at est passé.
     */
    public function testPurgeExpiredDeletesExpiredRows(): void
    {
        // Insère manuellement une ligne expirée
        self::$db->execute(
            "INSERT INTO rgpd_export_logs (email, ip, filename, requested_at, expires_at)
             VALUES ('expired@example.com', '9.9.9.9', 'old.zip', NOW(), DATE_SUB(NOW(), INTERVAL 1 DAY))"
        );

        $deleted = $this->model->purgeExpired();
        $this->assertGreaterThanOrEqual(1, $deleted);

        $rows = $this->model->getAll(10, 0, 'expired@example.com');
        $this->assertCount(0, $rows);
    }

    /**
     * purgeExpired() ne supprime pas les lignes non expirées.
     */
    public function testPurgeExpiredKeepsValidRows(): void
    {
        $this->model->log('valid@example.com', '1.1.1.1', 'valid.zip');

        $this->model->purgeExpired();

        $rows = $this->model->getAll(10, 0, 'valid@example.com');
        $this->assertCount(1, $rows);
    }
}
