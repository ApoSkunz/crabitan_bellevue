<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Model\ConnectionModel;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ConnectionModel::purgeExpired().
 *
 * Vérifie le comportement sans BDD : retour entier, délégation au modèle.
 */
class ConnectionModelPurgeTest extends TestCase
{
    private ConnectionModel $model;

    protected function setUp(): void
    {
        try {
            $this->model = new ConnectionModel();
        } catch (\Throwable $e) {
            $this->markTestSkipped('BDD indisponible : ' . $e->getMessage());
        }
    }

    /**
     * purgeExpired() retourne un entier (0 ou plus).
     */
    public function testPurgeExpiredReturnsInt(): void
    {
        $result = $this->model->purgeExpired();
        $this->assertIsInt($result);
    }

    /**
     * purgeExpired() retourne une valeur ≥ 0.
     */
    public function testPurgeExpiredReturnsNonNegative(): void
    {
        $this->assertGreaterThanOrEqual(0, $this->model->purgeExpired());
    }
}
