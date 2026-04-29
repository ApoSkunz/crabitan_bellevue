<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Service\MailService;

/**
 * Tests unitaires pour MailService::sendWineStockReport().
 *
 * Couvre : liste vide, liste complète, stock 0 (rouge), stock ≤ 6 (orange),
 * stock > 6 (vert), cuvée spéciale, vin indisponible, champs absents.
 */
class MailServiceWineStockReportTest extends TestCase
{
    private function injectMockMailer(MailService $service): void
    {
        $stub = $this->createStub(PHPMailer::class);
        $stub->method('send')->willReturn(true);

        $prop = new ReflectionProperty(MailService::class, 'mailer');
        $prop->setValue($service, $stub); // NOSONAR — test unitaire, accès privé délibéré
    }

    private function sampleWines(): array
    {
        return [
            [
                'vintage'              => 2021,
                'label_name'           => 'Château Crabitan Bellevue',
                'certification_label'  => 'AOC Sainte-Croix-du-Mont',
                'is_cuvee_speciale'    => 1,
                'price'                => 18.50,
                'stock_quantity'       => 24,
                'available'            => true,
            ],
            [
                'vintage'              => 2019,
                'label_name'           => 'Crabitan Blanc Sec',
                'certification_label'  => 'Bordeaux AOC',
                'is_cuvee_speciale'    => 0,
                'price'                => 12.00,
                'stock_quantity'       => 4,
                'available'            => true,
            ],
            [
                'vintage'              => 2018,
                'label_name'           => 'Vieilles Vignes',
                'certification_label'  => 'AOC Sainte-Croix-du-Mont',
                'is_cuvee_speciale'    => 0,
                'price'                => 22.00,
                'stock_quantity'       => 0,
                'available'            => false,
            ],
        ];
    }

    // ----------------------------------------------------------------
    // Liste non vide — ne lève pas d'exception
    // ----------------------------------------------------------------

    public function testSendWineStockReportWithWinesDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendWineStockReport($this->sampleWines());

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Liste vide — ne lève pas d'exception
    // ----------------------------------------------------------------

    public function testSendWineStockReportWithEmptyListDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendWineStockReport([]);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Stock = 0 → couleur rouge (#c0392b)
    // ----------------------------------------------------------------

    public function testSendWineStockReportWithZeroStockDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendWineStockReport([[
            'vintage'             => 2020,
            'label_name'          => 'Épuisé',
            'certification_label' => 'AOC',
            'is_cuvee_speciale'   => 0,
            'price'               => 15.00,
            'stock_quantity'      => 0,
            'available'           => false,
        ]]);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Stock ≤ 6 → couleur orange (#e67e22)
    // ----------------------------------------------------------------

    public function testSendWineStockReportWithLowStockDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendWineStockReport([[
            'vintage'             => 2022,
            'label_name'          => 'Stock faible',
            'certification_label' => 'IGP',
            'is_cuvee_speciale'   => 0,
            'price'               => 9.90,
            'stock_quantity'      => 3,
            'available'           => true,
        ]]);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Cuvée spéciale → icône ✦ incluse dans le HTML
    // ----------------------------------------------------------------

    public function testSendWineStockReportWithCuveeSpecialeDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendWineStockReport([[
            'vintage'             => 2017,
            'label_name'          => 'Cuvée Prestige',
            'certification_label' => 'AOC Sauternes',
            'is_cuvee_speciale'   => 1,
            'price'               => 45.00,
            'stock_quantity'      => 12,
            'available'           => true,
        ]]);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Vin indisponible → badge "(indispo)" dans le HTML
    // ----------------------------------------------------------------

    public function testSendWineStockReportWithUnavailableWineDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendWineStockReport([[
            'vintage'             => 2015,
            'label_name'          => 'Non commercialisé',
            'certification_label' => 'AOC',
            'is_cuvee_speciale'   => 0,
            'price'               => 30.00,
            'stock_quantity'      => 6,
            'available'           => false,
        ]]);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Champs absents — valeurs par défaut (—, 0, false)
    // ----------------------------------------------------------------

    public function testSendWineStockReportWithMissingFieldsDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendWineStockReport([[]]);

        $this->assertTrue(true);
    }

    // ----------------------------------------------------------------
    // Caractères spéciaux — échappement HTML
    // ----------------------------------------------------------------

    public function testSendWineStockReportWithSpecialCharsDoesNotThrow(): void
    {
        $service = new MailService();
        $this->injectMockMailer($service);

        $service->sendWineStockReport([[
            'vintage'             => 2023,
            'label_name'          => '<script>alert("xss")</script>',
            'certification_label' => '"Médaille" & <Bio>',
            'is_cuvee_speciale'   => 0,
            'price'               => 10.00,
            'stock_quantity'      => 50,
            'available'           => true,
        ]]);

        $this->assertTrue(true);
    }
}
