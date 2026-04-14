<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\OrderSummaryPdfService;

/**
 * TU pour OrderSummaryPdfService — PDF récap commande joint au mail gérant.
 */
class OrderSummaryPdfServiceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $data;

    protected function setUp(): void
    {
        if (!defined('ROOT_PATH')) {
            $this->markTestSkipped('ROOT_PATH non défini — test ignoré hors contexte bootstrap.');
        }

        $this->data = [
            'reference'         => 'CB-2026-0042',
            'ordered_at'        => '2026-04-14 09:00:00',
            'payment_method'    => 'card',
            'items'             => [
                ['label_name' => 'Crabitan Blanc', 'qty' => 6, 'price' => 18.50, 'vintage' => 2022],
                ['label_name' => 'Crabitan Rouge', 'qty' => 6, 'price' => 16.50, 'vintage' => 2021],
            ],
            'total'             => 210.00,
            'shipping_discount' => 0.0,
            'billing'           => [
                'civility'  => 'M',
                'firstname' => 'Jean',
                'lastname'  => 'Dupont',
                'street'    => '12 rue de la Paix',
                'zip_code'  => '75001',
                'city'      => 'Paris',
                'country'   => 'France',
                'phone'     => '0600000000',
            ],
            'delivery'          => [
                'civility'  => 'M',
                'firstname' => 'Jean',
                'lastname'  => 'Dupont',
                'street'    => '5 avenue du Parc',
                'zip_code'  => '69002',
                'city'      => 'Lyon',
                'country'   => 'France',
                'phone'     => '0600000000',
            ],
        ];
    }

    public function testGenerateReturnsPdfMagicBytes(): void
    {
        $service = new OrderSummaryPdfService();
        $result  = $service->generate($this->data);

        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testGenerateWithDeliveryNullProducesPdf(): void
    {
        $data             = $this->data;
        $data['delivery'] = null;

        $service = new OrderSummaryPdfService();
        $result  = $service->generate($data);

        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testGenerateWithDiscountProducesPdf(): void
    {
        $data                      = $this->data;
        $data['shipping_discount'] = 12.50;

        $service = new OrderSummaryPdfService();
        $result  = $service->generate($data);

        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testBuildHtmlContainsRecapTitleAndNoSiret(): void
    {
        $service = new OrderSummaryPdfService();
        $method  = new \ReflectionMethod(OrderSummaryPdfService::class, 'buildHtml');

        $html = $method->invoke($service, $this->data, null);

        $this->assertStringContainsString('RÉCAPITULATIF', $html);
        $this->assertStringNotContainsString('SIRET', $html);
        $this->assertStringNotContainsString('FACTURE', $html);
    }

    public function testBuildAddressBlockReturnsPlaceholderWhenAllEmpty(): void
    {
        $service = new OrderSummaryPdfService();
        $method  = new \ReflectionMethod(OrderSummaryPdfService::class, 'buildAddressBlock');

        $html = $method->invoke($service, 'Adresse', []);

        $this->assertStringContainsString('Aucune adresse', $html);
    }

    public function testBuildLogoTagFallbackWhenNull(): void
    {
        $service = new OrderSummaryPdfService();
        $method  = new \ReflectionMethod(OrderSummaryPdfService::class, 'buildLogoTag');

        $html = $method->invoke($service, null);

        $this->assertStringContainsString('<span', $html);
    }
}
