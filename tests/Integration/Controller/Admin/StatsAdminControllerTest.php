<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\StatsAdminController;

/**
 * Tests d'intégration pour StatsAdminController.
 */
class StatsAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET'): StatsAdminController
    {
        return new StatsAdminController(
            $this->makeRequest($method, '/admin/statistiques')
        );
    }

    // ----------------------------------------------------------------
    // index — période par défaut (30d)
    // ----------------------------------------------------------------

    /**
     * GET /admin/statistiques sans paramètre affiche la vue avec la période 30d.
     */
    public function testIndexRendersDefaultPeriod(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-card', $output);
    }

    // ----------------------------------------------------------------
    // index — période 3m
    // ----------------------------------------------------------------

    /**
     * GET /admin/statistiques?period=3m affiche la vue 3 mois.
     */
    public function testIndexRenders3mPeriod(): void
    {
        $_GET['period'] = '3m';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-card', $output);
    }

    // ----------------------------------------------------------------
    // index — période 1y
    // ----------------------------------------------------------------

    /**
     * GET /admin/statistiques?period=1y affiche la vue 12 mois.
     */
    public function testIndexRenders1yPeriod(): void
    {
        $_GET['period'] = '1y';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-card', $output);
    }

    // ----------------------------------------------------------------
    // index — période all
    // ----------------------------------------------------------------

    /**
     * GET /admin/statistiques?period=all affiche la vue globale.
     */
    public function testIndexRendersAllPeriod(): void
    {
        $_GET['period'] = 'all';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-card', $output);
    }

    // ----------------------------------------------------------------
    // index — période invalide → retombe sur 30d
    // ----------------------------------------------------------------

    /**
     * Une période invalide retombe sur 30d sans erreur.
     */
    public function testIndexWithInvalidPeriodFallsBackTo30d(): void
    {
        $_GET['period'] = 'invalid_period_xyz';

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-card', $output);
    }

    // ----------------------------------------------------------------
    // index — période = année (ex. 2024)
    // ----------------------------------------------------------------

    /**
     * Une période sous forme d'année valide en BDD est acceptée.
     */
    public function testIndexWithYearPeriodRenders(): void
    {
        $_GET['period'] = (string) date('Y');

        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-card', $output);
    }
}
