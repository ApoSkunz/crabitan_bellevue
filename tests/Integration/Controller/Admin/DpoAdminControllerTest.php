<?php

declare(strict_types=1);

namespace Tests\Integration\Controller\Admin;

use Controller\Admin\DpoAdminController;

/**
 * Tests d'intégration pour DpoAdminController.
 */
class DpoAdminControllerTest extends AdminIntegrationTestCase
{
    private function makeController(string $method = 'GET', string $uri = '/admin/dpo'): DpoAdminController
    {
        return new DpoAdminController($this->makeRequest($method, $uri));
    }

    // ----------------------------------------------------------------
    // index — rendu vue
    // ----------------------------------------------------------------

    /**
     * GET /admin/dpo avec session admin → rendu de la page DPO.
     */
    public function testIndexRendersDpoPage(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('admin-card', $output);
        $this->assertStringContainsString('registre-traitements', $output);
    }

    /**
     * GET /admin/dpo expose les 3 liens de téléchargement RGPD.
     */
    public function testIndexContainsAllThreeDownloadLinks(): void
    {
        ob_start();
        $this->makeController()->index([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('registre-traitements', $output);
        $this->assertStringContainsString('sous-traitants', $output);
        $this->assertStringContainsString('procedure-violation', $output);
    }

    // ----------------------------------------------------------------
    // index — protection auth
    // ----------------------------------------------------------------

    /**
     * GET /admin/dpo sans cookie JWT → HttpException 302 vers /connexion.
     */
    public function testIndexRedirectsWithoutAuth(): void
    {
        $_COOKIE = [];

        $this->expectException(\Core\Exception\HttpException::class);

        ob_start();
        try {
            $this->makeController()->index([]);
        } finally {
            ob_end_clean();
        }
    }
}
