<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Service\InvoiceService;

/**
 * TU pour InvoiceService::generate().
 *
 * Ces tests nécessitent TCPDF (vendor/tecnickcom/tcpdf) et la constante ROOT_PATH.
 * Ils génèrent un vrai PDF en mémoire pour valider le contenu binaire minimal.
 */
class InvoiceServiceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $order;

    protected function setUp(): void
    {
        if (!defined('ROOT_PATH')) {
            $this->markTestSkipped('ROOT_PATH non défini — test ignoré hors contexte bootstrap.');
        }

        $this->order = [
            'id'               => 42,
            'order_reference'  => 'WEB-CB-ABCD1234-2026',
            'ordered_at'       => '2026-04-05 10:00:00',
            'price'            => 120.00,
            'shipping_discount' => 5.00,
            'payment_method'   => 'card',
            'content'          => json_encode([
                [
                    'label_name'        => 'Château Crabitan Bellevue Blanc 2022',
                    'qty'               => 6,
                    'price'             => 18.50,
                    'is_cuvee_speciale' => false,
                ],
                [
                    'label_name'        => 'Crabitan Bellevue Rouge 2021',
                    'qty'               => 6,
                    'price'             => 16.50,
                    'is_cuvee_speciale' => true,
                ],
            ]),
            'bill_civility'    => 'M',
            'bill_firstname'   => 'Jean',
            'bill_lastname'    => 'Dupont',
            'bill_street'      => '12 rue de la Paix',
            'bill_city'        => 'Paris',
            'bill_zip'         => '75001',
            'bill_country'     => 'France',
            'bill_phone'       => '0600000000',
        ];
    }

    public function testGenerateReturnsBinaryString(): void
    {
        $service = new InvoiceService();
        $result  = $service->generate($this->order, 'WEB-INVOICE-2026-0042');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testGenerateReturnsPdfMagicBytes(): void
    {
        $service = new InvoiceService();
        $result  = $service->generate($this->order, 'WEB-INVOICE-2026-0042');

        // Les PDF commencent toujours par le magic bytes %PDF
        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testGenerateWithZeroDiscountProducesPdf(): void
    {
        $order                     = $this->order;
        $order['shipping_discount'] = 0.0;

        $service = new InvoiceService();
        $result  = $service->generate($order, 'WEB-INVOICE-2026-0001');

        $this->assertStringStartsWith('%PDF', $result);
    }

    public function testGenerateWithEmptyItemsProducesPdf(): void
    {
        $order            = $this->order;
        $order['content'] = '[]';

        $service = new InvoiceService();
        $result  = $service->generate($order, 'WEB-INVOICE-2026-0002');

        $this->assertStringStartsWith('%PDF', $result);
    }
}
