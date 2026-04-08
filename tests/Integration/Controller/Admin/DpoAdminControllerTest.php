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

    // ----------------------------------------------------------------
    // downloadRegistre
    // ----------------------------------------------------------------

    /**
     * GET /admin/dpo/registre-traitements sans auth → HttpException.
     */
    public function testDownloadRegistreRequiresAuth(): void
    {
        $_COOKIE = [];
        $this->expectException(\Core\Exception\HttpException::class);

        ob_start();
        try {
            $this->makeController('GET', '/admin/dpo/registre-traitements')->downloadRegistre([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * GET /admin/dpo/registre-traitements avec admin → génère un PDF ou lève TcpdfNotAvailableException.
     */
    public function testDownloadRegistreGeneratesPdf(): void
    {
        ob_start();
        try {
            $this->makeController('GET', '/admin/dpo/registre-traitements')->downloadRegistre([]);
            $output = ob_get_clean();
            // TCPDF installé → sortie PDF binaire
            $this->assertStringContainsString('%PDF', $output);
        } catch (\Exception\TcpdfNotAvailableException $e) {
            ob_end_clean();
            // TCPDF absent en CI → attendu
            $this->assertStringContainsString('TCPDF', $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // downloadSousTraitants
    // ----------------------------------------------------------------

    /**
     * GET /admin/dpo/sous-traitants sans auth → HttpException.
     */
    public function testDownloadSousTraitantsRequiresAuth(): void
    {
        $_COOKIE = [];
        $this->expectException(\Core\Exception\HttpException::class);

        ob_start();
        try {
            $this->makeController('GET', '/admin/dpo/sous-traitants')->downloadSousTraitants([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * GET /admin/dpo/sous-traitants avec admin → génère un PDF ou lève TcpdfNotAvailableException.
     */
    public function testDownloadSousTraitantsGeneratesPdf(): void
    {
        ob_start();
        try {
            $this->makeController('GET', '/admin/dpo/sous-traitants')->downloadSousTraitants([]);
            $output = ob_get_clean();
            $this->assertStringContainsString('%PDF', $output);
        } catch (\Exception\TcpdfNotAvailableException $e) {
            ob_end_clean();
            $this->assertStringContainsString('TCPDF', $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // downloadMatrice
    // ----------------------------------------------------------------

    /**
     * GET /admin/dpo/matrice-habilitations sans auth → HttpException.
     */
    public function testDownloadMatriceRequiresAuth(): void
    {
        $_COOKIE = [];
        $this->expectException(\Core\Exception\HttpException::class);

        ob_start();
        try {
            $this->makeController('GET', '/admin/dpo/matrice-habilitations')->downloadMatrice([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * GET /admin/dpo/matrice-habilitations avec admin → génère un PDF ou lève TcpdfNotAvailableException.
     */
    public function testDownloadMatriceGeneratesPdf(): void
    {
        ob_start();
        try {
            $this->makeController('GET', '/admin/dpo/matrice-habilitations')->downloadMatrice([]);
            $output = ob_get_clean();
            $this->assertStringContainsString('%PDF', $output);
        } catch (\Exception\TcpdfNotAvailableException $e) {
            ob_end_clean();
            $this->assertStringContainsString('TCPDF', $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // downloadProcedure
    // ----------------------------------------------------------------

    /**
     * GET /admin/dpo/procedure-violation sans auth → HttpException.
     */
    public function testDownloadProcedureRequiresAuth(): void
    {
        $_COOKIE = [];
        $this->expectException(\Core\Exception\HttpException::class);

        ob_start();
        try {
            $this->makeController('GET', '/admin/dpo/procedure-violation')->downloadProcedure([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * GET /admin/dpo/procedure-violation avec admin → génère un PDF ou lève TcpdfNotAvailableException.
     */
    public function testDownloadProcedureGeneratesPdf(): void
    {
        ob_start();
        try {
            $this->makeController('GET', '/admin/dpo/procedure-violation')->downloadProcedure([]);
            $output = ob_get_clean();
            $this->assertStringContainsString('%PDF', $output);
        } catch (\Exception\TcpdfNotAvailableException $e) {
            ob_end_clean();
            $this->assertStringContainsString('TCPDF', $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // downloadDpia
    // ----------------------------------------------------------------

    /**
     * GET /admin/dpo/dpia sans auth → HttpException.
     */
    public function testDownloadDpiaRequiresAuth(): void
    {
        $_COOKIE = [];
        $this->expectException(\Core\Exception\HttpException::class);

        ob_start();
        try {
            $this->makeController('GET', '/admin/dpo/dpia')->downloadDpia([]);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * GET /admin/dpo/dpia avec admin → génère un PDF ou lève TcpdfNotAvailableException.
     */
    public function testDownloadDpiaGeneratesPdf(): void
    {
        ob_start();
        try {
            $this->makeController('GET', '/admin/dpo/dpia')->downloadDpia([]);
            $output = ob_get_clean();
            $this->assertStringContainsString('%PDF', $output);
        } catch (\Exception\TcpdfNotAvailableException $e) {
            ob_end_clean();
            $this->assertStringContainsString('TCPDF', $e->getMessage());
        }
    }

    // ----------------------------------------------------------------
    // export (AuditLogAdminController — couverture de la branche auth)
    // ----------------------------------------------------------------

    /**
     * GET /admin/logs/export sans auth → HttpException.
     */
    public function testExportAuditLogRequiresSuperAdmin(): void
    {
        $_COOKIE = [];
        $this->expectException(\Core\Exception\HttpException::class);

        $request    = $this->makeRequest('GET', '/admin/logs/export');
        $controller = new \Controller\Admin\AuditLogAdminController($request);

        ob_start();
        try {
            $controller->export([]);
        } finally {
            ob_end_clean();
        }
    }
}
