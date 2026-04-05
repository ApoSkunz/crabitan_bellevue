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

    // ----------------------------------------------------------------
    // buildLogoTag — branche fallback (logo absent)
    // ----------------------------------------------------------------

    /**
     * Vérifie que buildLogoTag retourne un span texte quand le logo est absent.
     */
    public function testBuildLogoTagReturnsFallbackSpanWhenLogoAbsent(): void
    {
        $service = new InvoiceService();
        $method  = new \ReflectionMethod(InvoiceService::class, 'buildLogoTag');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $result = $method->invoke($service, null);

        $this->assertStringContainsString('<span', $result);
        $this->assertStringContainsString('Crabitan', $result);
    }

    /**
     * Vérifie que buildLogoTag retourne un img quand un chemin logo est fourni.
     */
    public function testBuildLogoTagReturnsImgWhenLogoPresent(): void
    {
        $service = new InvoiceService();
        $method  = new \ReflectionMethod(InvoiceService::class, 'buildLogoTag');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $result = $method->invoke($service, '/path/to/logo.png');

        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('/path/to/logo.png', $result);
    }

    // ----------------------------------------------------------------
    // Loi Évin — mention présente si articles, absente si panier vide
    // ----------------------------------------------------------------

    /**
     * Le HTML de la facture avec des vins doit contenir la mention Loi Évin (Art. L3323-4 CSP).
     * On teste buildHtml() directement car le binaire TCPDF encode les glyphes.
     */
    public function testBuildHtmlWithWinesContainsLoiEvinMention(): void
    {
        $service = new InvoiceService();
        $method  = new \ReflectionMethod(InvoiceService::class, 'buildHtml');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $items = json_decode((string) ($this->order['content'] ?? '[]'), true) ?: [];
        $html  = $method->invoke($service, $this->order, 'WEB-INVOICE-2026-0042', null);

        $this->assertNotEmpty($items); // pré-condition : articles présents
        $this->assertStringContainsString('L3323-4', $html);
        $this->assertStringContainsString('abus', $html);
    }

    /**
     * Le HTML d'une facture sans article ne doit PAS contenir la mention Loi Évin.
     */
    public function testBuildHtmlWithEmptyItemsDoesNotContainLoiEvinMention(): void
    {
        $service = new InvoiceService();
        $method  = new \ReflectionMethod(InvoiceService::class, 'buildHtml');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $order            = $this->order;
        $order['content'] = '[]';

        $html = $method->invoke($service, $order, 'WEB-INVOICE-2026-0002', null);

        $this->assertStringNotContainsString('L3323-4', $html);
    }

    /**
     * hasAlcoholicItems() retourne true si le tableau d'articles est non vide.
     */
    public function testHasAlcoholicItemsReturnsTrueWhenItemsPresent(): void
    {
        $service = new InvoiceService();
        $method  = new \ReflectionMethod(InvoiceService::class, 'hasAlcoholicItems');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $this->assertTrue($method->invoke($service, [['label_name' => 'Vin', 'qty' => 1, 'price' => 10.0]]));
    }

    /**
     * hasAlcoholicItems() retourne false si le tableau d'articles est vide.
     */
    public function testHasAlcoholicItemsReturnsFalseWhenItemsEmpty(): void
    {
        $service = new InvoiceService();
        $method  = new \ReflectionMethod(InvoiceService::class, 'hasAlcoholicItems');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $this->assertFalse($method->invoke($service, []));
    }

    /**
     * buildLoiEvinBlock() retourne un bloc HTML contenant la mention réglementaire.
     */
    public function testBuildLoiEvinBlockContainsRequiredText(): void
    {
        $service = new InvoiceService();
        $method  = new \ReflectionMethod(InvoiceService::class, 'buildLoiEvinBlock');
        $method->setAccessible(true); // NOSONAR — test unitaire, accès privé délibéré

        $result = $method->invoke($service);

        $this->assertStringContainsString('L3323-4', $result);
        $this->assertStringContainsString('abus', $result);
        $this->assertStringContainsString('mod', $result);
    }
}
