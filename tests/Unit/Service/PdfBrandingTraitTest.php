<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Service\OrderSummaryPdfService;

/**
 * Tests unitaires pour PdfBrandingTrait::buildItemsRows().
 *
 * Accès via réflexion sur OrderSummaryPdfService (seule classe concrète du trait facilement instanciable).
 * Couvre : nom basique, millésime, cuvée spéciale, combinaison vintage+cuvée.
 */
class PdfBrandingTraitTest extends TestCase
{
    private function callBuildItemsRows(array $items, bool $showVintage = false): string
    {
        $service = new OrderSummaryPdfService();
        $method  = new ReflectionMethod(OrderSummaryPdfService::class, 'buildItemsRows');
        /** @var string */
        return $method->invoke($service, $items, $showVintage);
    }

    // ----------------------------------------------------------------
    // Nom basique
    // ----------------------------------------------------------------

    public function testBuildItemsRowsRendersProductName(): void
    {
        $html = $this->callBuildItemsRows([
            ['label_name' => 'Château Crabitan', 'qty' => 1, 'price' => 20.0],
        ]);

        $this->assertStringContainsString('Château Crabitan', $html);
    }

    // ----------------------------------------------------------------
    // Millésime
    // ----------------------------------------------------------------

    public function testBuildItemsRowsShowsVintageWhenFlagSet(): void
    {
        $html = $this->callBuildItemsRows(
            [['label_name' => 'Sauternes', 'qty' => 2, 'price' => 35.0, 'vintage' => 2018]],
            true
        );

        $this->assertStringContainsString('2018', $html);
    }

    public function testBuildItemsRowsHidesVintageWhenFlagFalse(): void
    {
        $html = $this->callBuildItemsRows(
            [['label_name' => 'Sauternes', 'qty' => 2, 'price' => 35.0, 'vintage' => 2018]],
            false
        );

        $this->assertStringNotContainsString('2018', $html);
    }

    public function testBuildItemsRowsSkipsZeroVintage(): void
    {
        $html = $this->callBuildItemsRows(
            [['label_name' => 'Vin', 'qty' => 1, 'price' => 10.0, 'vintage' => 0]],
            true
        );

        $this->assertStringNotContainsString('color:#8a7060;', $html);
    }

    // ----------------------------------------------------------------
    // Cuvée spéciale
    // ----------------------------------------------------------------

    public function testBuildItemsRowsShowsCuveeSpecialeMarker(): void
    {
        $html = $this->callBuildItemsRows([
            ['label_name' => 'Grande Réserve', 'qty' => 1, 'price' => 50.0, 'is_cuvee_speciale' => 1],
        ]);

        $this->assertStringContainsString('Cuv', $html);
        $this->assertStringContainsString('color:#c9a84c;', $html);
    }

    public function testBuildItemsRowsHidesCuveeSpecialeMarkerWhenZero(): void
    {
        $html = $this->callBuildItemsRows([
            ['label_name' => 'Standard', 'qty' => 1, 'price' => 20.0, 'is_cuvee_speciale' => 0],
        ]);

        $this->assertStringNotContainsString('color:#c9a84c;', $html);
    }

    // ----------------------------------------------------------------
    // Combinaison millésime + cuvée spéciale
    // ----------------------------------------------------------------

    public function testBuildItemsRowsShowsBothVintageAndCuveeSpeciale(): void
    {
        $html = $this->callBuildItemsRows(
            [['label_name' => 'Prestige', 'qty' => 1, 'price' => 80.0, 'vintage' => 2020, 'is_cuvee_speciale' => 1]],
            true
        );

        $this->assertStringContainsString('2020', $html);
        $this->assertStringContainsString('color:#c9a84c;', $html);
    }

    // ----------------------------------------------------------------
    // Calcul sous-total
    // ----------------------------------------------------------------

    public function testBuildItemsRowsCalculatesSubtotalCorrectly(): void
    {
        $html = $this->callBuildItemsRows([
            ['label_name' => 'Vin', 'qty' => 3, 'price' => 10.0],
        ]);

        // 3 × 10 = 30,00 €
        $this->assertStringContainsString('30,00', $html);
    }
}
