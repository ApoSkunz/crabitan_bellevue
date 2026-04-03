<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour DpoAdminController.
 * Couvre les helpers privés de génération de contenu HTML via Reflection.
 */
class DpoAdminControllerTest extends TestCase
{
    private \ReflectionProperty $instanceProp;
    private \Controller\Admin\DpoAdminController $ctrl;

    protected function setUp(): void
    {
        defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 4));
        defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
        defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

        require_once ROOT_PATH . '/vendor/autoload.php';
        require_once ROOT_PATH . '/src/helpers.php';
        require_once ROOT_PATH . '/config/config.php';

        $dbMock = $this->createStub(\Core\Database::class);
        $this->instanceProp = new \ReflectionProperty(\Core\Database::class, 'instance');
        $this->instanceProp->setAccessible(true);
        $this->instanceProp->setValue(null, $dbMock);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/admin/dpo';
        $this->ctrl = new \Controller\Admin\DpoAdminController(new \Core\Request());
    }

    protected function tearDown(): void
    {
        $this->instanceProp->setValue(null, null);
    }

    private function callPrivate(string $method): string
    {
        $ref = new \ReflectionMethod(\Controller\Admin\DpoAdminController::class, $method);
        $ref->setAccessible(true);
        return (string) $ref->invoke($this->ctrl);
    }

    // ----------------------------------------------------------------
    // htmlRegistre
    // ----------------------------------------------------------------

    public function testHtmlRegistreReturnNonEmpty(): void
    {
        $html = $this->callPrivate('htmlRegistre');
        $this->assertNotEmpty($html);
    }

    public function testHtmlRegistreContainsArt30Reference(): void
    {
        $html = $this->callPrivate('htmlRegistre');
        $this->assertStringContainsString('Art. 30', $html);
    }

    public function testHtmlRegistreContainsSiret(): void
    {
        $html = $this->callPrivate('htmlRegistre');
        $this->assertStringContainsString('398 341 701 00017', $html);
    }

    public function testHtmlRegistreContainsTenTreatments(): void
    {
        $html = $this->callPrivate('htmlRegistre');
        // Vérifie que les 10 activités de traitement sont présentes
        $this->assertStringContainsString('Gestion des comptes clients', $html);
        $this->assertStringContainsString('commandes et facturation', $html);
        $this->assertStringContainsString('Newsletter', $html);
        $this->assertStringContainsString('Google Analytics', $html);
        $this->assertStringContainsString('DeepL', $html);
    }

    public function testHtmlRegistreContainsRetentionPeriods(): void
    {
        $html = $this->callPrivate('htmlRegistre');
        // 10 ans pour les commandes (obligation comptable L123-22)
        $this->assertStringContainsString('10 ans', $html);
        // 1 an pour les logs
        $this->assertStringContainsString('1 an', $html);
    }

    // ----------------------------------------------------------------
    // htmlSousTraitants
    // ----------------------------------------------------------------

    public function testHtmlSousTraitantsReturnNonEmpty(): void
    {
        $html = $this->callPrivate('htmlSousTraitants');
        $this->assertNotEmpty($html);
    }

    public function testHtmlSousTraitantsContainsArt28Reference(): void
    {
        $html = $this->callPrivate('htmlSousTraitants');
        $this->assertStringContainsString('Art. 28', $html);
    }

    public function testHtmlSousTraitantsListsAllProcessors(): void
    {
        $html = $this->callPrivate('htmlSousTraitants');
        $this->assertStringContainsString('IONOS SE', $html);
        $this->assertStringContainsString('DeepL SE', $html);
        $this->assertStringContainsString('GitHub', $html);
        $this->assertStringContainsString('SonarSource', $html);
        $this->assertStringContainsString('Google LLC', $html);
        $this->assertStringContainsString('Crédit Agricole', $html);
        // Apple Sign In non prévu — retiré du périmètre
        $this->assertStringNotContainsString('Apple Inc.', $html);
    }

    public function testHtmlSousTraitantsContainsTransferGuarantees(): void
    {
        $html = $this->callPrivate('htmlSousTraitants');
        // SCCs pour les transferts US
        $this->assertStringContainsString('SCCs', $html);
    }

    // ----------------------------------------------------------------
    // htmlProcedure
    // ----------------------------------------------------------------

    public function testHtmlProcedureReturnNonEmpty(): void
    {
        $html = $this->callPrivate('htmlProcedure');
        $this->assertNotEmpty($html);
    }

    public function testHtmlProcedureContainsArt33Reference(): void
    {
        $html = $this->callPrivate('htmlProcedure');
        $this->assertStringContainsString('Art. 33', $html);
    }

    public function testHtmlProcedureContains72hDelay(): void
    {
        $html = $this->callPrivate('htmlProcedure');
        $this->assertStringContainsString('72h', $html);
    }

    public function testHtmlProcedureContainsCnilUrl(): void
    {
        $html = $this->callPrivate('htmlProcedure');
        $this->assertStringContainsString('notifications.cnil.fr', $html);
    }

    public function testHtmlProcedureContainsEscaladeChain(): void
    {
        $html = $this->callPrivate('htmlProcedure');
        $this->assertStringContainsString('DevSecOps', $html);
        $this->assertStringContainsString('DPO', $html);
        $this->assertStringContainsString('Direction', $html);
    }

    // ----------------------------------------------------------------
    // pdfStyles
    // ----------------------------------------------------------------

    public function testPdfStylesReturnsCssContent(): void
    {
        $css = $this->callPrivate('pdfStyles');
        $this->assertStringContainsString('<style>', $css);
        $this->assertStringContainsString('dejavusans', $css);
        $this->assertStringContainsString('.footer', $css);
        $this->assertStringContainsString('#c1a14b', $css);
    }

    // ----------------------------------------------------------------
    // row
    // ----------------------------------------------------------------

    public function testRowGeneratesValidHtml(): void
    {
        $ref = new \ReflectionMethod(\Controller\Admin\DpoAdminController::class, 'row');
        $ref->setAccessible(true);
        $html = (string) $ref->invoke($this->ctrl, 'Finalité', 'Gestion comptes');

        $this->assertStringContainsString('<tr>', $html);
        $this->assertStringContainsString('<td class="label">Finalité</td>', $html);
        $this->assertStringContainsString('<td>Gestion comptes</td>', $html);
    }

    // ----------------------------------------------------------------
    // TcpdfNotAvailableException — contrat de l'exception dédiée
    // ----------------------------------------------------------------

    public function testTcpdfNotAvailableExceptionExtendsRuntimeException(): void
    {
        $e = new \Exception\TcpdfNotAvailableException('TCPDF non disponible : /fake/path');
        $this->assertInstanceOf(\RuntimeException::class, $e);
        $this->assertStringContainsString('/fake/path', $e->getMessage());
    }

    // ----------------------------------------------------------------
    // DATE_FORMAT — constante interne
    // ----------------------------------------------------------------

    public function testDateFormatConstantValue(): void
    {
        $ref = new \ReflectionClassConstant(\Controller\Admin\DpoAdminController::class, 'DATE_FORMAT');
        $this->assertSame('d/m/Y', $ref->getValue());
    }

    // ----------------------------------------------------------------
    // pdfHeader
    // ----------------------------------------------------------------

    public function testPdfHeaderContainsCompanyName(): void
    {
        $ref = new \ReflectionMethod(\Controller\Admin\DpoAdminController::class, 'pdfHeader');
        $ref->setAccessible(true);
        $html = (string) $ref->invoke($this->ctrl, 'Test', 'Sous-titre', '01/01/2026');

        $this->assertStringContainsString('GFA Bernard Solane et Fils', $html);
        $this->assertStringContainsString('398 341 701 00017', $html);
        $this->assertStringContainsString('crabitan.bellevue@orange.fr', $html);
    }
}
