<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\DashboardController;

class DashboardControllerTest extends AdminIntegrationTestCase
{
    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function testIndexRendersAdminDashboard(): void
    {
        ob_start();
        (new DashboardController($this->makeRequest('GET', '/admin')))->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-stat-card', $output);
        $this->assertStringContainsString('admin-stats', $output);
    }

    public function testIndexContainsBreadcrumb(): void
    {
        ob_start();
        (new DashboardController($this->makeRequest('GET', '/admin')))->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Vins au catalogue', $output);
    }
}
