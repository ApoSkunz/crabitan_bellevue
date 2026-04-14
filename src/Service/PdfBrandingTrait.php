<?php

declare(strict_types=1);

namespace Service;

/**
 * Fragments HTML réutilisés par les services PDF (facture + récap commande).
 *
 * Centralise le rendu du logo, des lignes d'articles et de la ligne remise
 * livraison pour éviter la duplication entre InvoiceService et
 * OrderSummaryPdfService (détectée par Sonar PR#141).
 */
trait PdfBrandingTrait
{
    /**
     * Logo du château (image si fichier trouvé, fallback texte sinon).
     */
    private function buildLogoTag(?string $logoPath, string $appName): string
    {
        if ($logoPath !== null) {
            return '<img src="' . $logoPath . '" style="width:32mm;" />';
        }
        return '<span style="font-size:10pt;font-weight:bold;color:#c1a14b;">'
            . htmlspecialchars($appName, ENT_QUOTES) . '</span>';
    }

    /**
     * Lignes du tableau d'articles. Option $showVintage ajoute le millésime
     * à côté du nom (utilisé par OrderSummaryPdfService, pas par la facture).
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function buildItemsRows(array $items, bool $showVintage = false): string
    {
        $rows = '';
        foreach ($items as $item) {
            $name = htmlspecialchars(
                (string) ($item['label_name'] ?? $item['name'] ?? '-'),
                ENT_QUOTES
            );
            if ($showVintage) {
                $vintage = (int) ($item['vintage'] ?? 0);
                if ($vintage > 0) {
                    $name .= ' <span style="color:#8a7060;">' . $vintage . '</span>';
                }
            }
            $qty      = (int)   ($item['qty']   ?? 0);
            $price    = (float) ($item['price'] ?? 0.0);
            $subtotal = $qty * $price;
            $rows .= '<tr>'
                . "<td style=\"padding:5px 6px;border-bottom:1px solid #e8e0d0;\">{$name}</td>"
                . "<td style=\"padding:5px 6px;border-bottom:1px solid #e8e0d0;text-align:center;\">{$qty}</td>"
                . "<td style=\"padding:5px 6px;border-bottom:1px solid #e8e0d0;text-align:right;\">"
                    . number_format($price, 2, ',', ' ') . '&nbsp;&euro;</td>'
                . "<td style=\"padding:5px 6px;border-bottom:1px solid #e8e0d0;text-align:right;\">"
                    . number_format($subtotal, 2, ',', ' ') . '&nbsp;&euro;</td>'
                . '</tr>';
        }
        return $rows;
    }

    /**
     * Ligne remise livraison, vide si pas de remise.
     */
    private function buildDiscountRow(float $shippingDiscount): string
    {
        if ($shippingDiscount <= 0.0) {
            return '';
        }
        return '<tr>'
            . '<td colspan="3" style="padding:4px 6px;border-bottom:1px solid #e8e0d0;">'
                . '<em>Remise livraison</em></td>'
            . '<td style="padding:4px 6px;border-bottom:1px solid #e8e0d0;text-align:right;'
                . 'font-style:italic;">'
                . '-' . number_format($shippingDiscount, 2, ',', ' ') . '&nbsp;&euro;'
            . '</td>'
            . '</tr>';
    }
}
