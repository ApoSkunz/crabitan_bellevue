<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Controller\CronController;
use Core\Request;
use Tests\Integration\IntegrationTestCase;

/**
 * Tests d'intégration pour CronController.
 *
 * Vérifie le flux HTTP complet : token → job → code retour.
 * Chaque test s'exécute dans une transaction rollbackée.
 */
class CronControllerTest extends IntegrationTestCase
{
    private const TOKEN = 'test-cron-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['CRON_SECRET_TOKEN']  = self::TOKEN;
        $_SERVER['REQUEST_METHOD']  = 'GET';
        $_SERVER['REQUEST_URI']     = '/cron/purge-rate-limits';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_GET = [];
        unset($_ENV['CRON_SECRET_TOKEN']);
    }

    private function makeController(string $uri = '/cron/purge-rate-limits'): CronController
    {
        $_SERVER['REQUEST_URI'] = $uri;
        return new CronController(new Request());
    }

    // ----------------------------------------------------------------
    // Token valide + job connu → HTTP 200
    // ----------------------------------------------------------------

    public function testRunWithValidTokenReturns200(): void
    {
        $_GET = ['token' => self::TOKEN];

        ob_start();
        $this->makeController()->run(['job' => 'purge-rate-limits']);
        ob_end_clean();

        $this->assertSame(200, http_response_code());
    }

    // ----------------------------------------------------------------
    // Token valide + job inconnu → HTTP 404
    // ----------------------------------------------------------------

    public function testRunWithUnknownJobReturns404(): void
    {
        $_GET = ['token' => self::TOKEN];

        ob_start();
        $this->makeController('/cron/unknown')->run(['job' => 'unknown-job']);
        $output = ob_get_clean();

        $this->assertSame(404, http_response_code());
        $this->assertStringContainsString('Unknown job', (string) $output);
    }

    // ----------------------------------------------------------------
    // dry_run=1 → output contient DRY-RUN, HTTP 200
    // ----------------------------------------------------------------

    public function testRunWithDryRunModeOutputsDryRun(): void
    {
        $_GET = ['token' => self::TOKEN, 'dry_run' => '1'];

        ob_start();
        $this->makeController()->run(['job' => 'purge-rate-limits']);
        $output = ob_get_clean();

        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('DRY-RUN', (string) $output);
    }
}
