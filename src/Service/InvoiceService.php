<?php

declare(strict_types=1);

namespace Service;

/**
 * Generates a TCPDF invoice PDF for a completed order (L441-9 C.com.).
 *
 * The invoice is returned as binary string so the caller decides where to persist it.
 */
class InvoiceService
{
    private const APP_NAME    = 'Château Crabitan Bellevue';
    private const VENDOR_NAME   = 'G.F.A Bernard Solane & Fils';
    private const VENDOR_ADDR   = "1 Crabitan\n33410 Sainte-Croix-du-Mont\nFrance";
    private const VENDOR_FORM   = 'Groupement foncier agricole';
    private const VENDOR_SIRET  = 'SIRET : 398 341 701 00017';
    private const VENDOR_RCS    = 'RCS Bordeaux : 398 341 701';
    private const VENDOR_TVA    = 'N° TVA : FR53398341701';
    private const TVA_RATE    = 0.20;

    /**
     * Generates the invoice PDF and returns its binary content.
     *
     * @param  array<string, mixed> $order         Full order row (with billing address JOIN).
     * @param  string               $invoiceNumber Invoice number (WEB-INVOICE-YYYY-XXXX).
     * @return string Binary PDF content.
     */
    public function generate(array $order, string $invoiceNumber): string
    {
        require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(self::APP_NAME);
        $pdf->SetAuthor(self::APP_NAME);
        $pdf->SetTitle('Facture ' . $invoiceNumber);
        $pdf->SetMargins(15, 18, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $logoPath = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo.png';
        $html = $this->buildHtml($order, $invoiceNumber, is_file($logoPath) ? $logoPath : null);
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    /**
     * Builds the HTML content for the TCPDF invoice.
     *
     * @param  array<string, mixed> $order
     * @param  string               $invoiceNumber
     * @param  string|null          $logoPath      Absolute path to the logo PNG, or null if absent.
     * @return string
     */
    private function buildHtml(array $order, string $invoiceNumber, ?string $logoPath): string
    {
        $items            = json_decode((string) ($order['content'] ?? '[]'), true) ?: [];
        $total            = (float) ($order['price']            ?? 0.0);
        $shippingDiscount = (float) ($order['shipping_discount'] ?? 0.0);
        $orderedAt        = date('d/m/Y', strtotime((string) ($order['ordered_at'] ?? 'now')));
        $paymentLabels    = [
            'card'     => 'Carte bancaire',
            'virement' => 'Virement bancaire',
            'cheque'   => 'Cheque',
        ];
        $paymentLabel = $paymentLabels[$order['payment_method'] ?? ''] ?? htmlspecialchars(
            (string) ($order['payment_method'] ?? ''),
            ENT_QUOTES
        );

        // Billing address
        $civility   = trim((string) ($order['bill_civility']  ?? ''));
        $fname      = htmlspecialchars(trim((string) ($order['bill_firstname'] ?? '')), ENT_QUOTES);
        $lname      = htmlspecialchars(trim((string) ($order['bill_lastname']  ?? '')), ENT_QUOTES);
        $clientName = trim("{$civility} {$fname} {$lname}");
        $street     = htmlspecialchars((string) ($order['bill_street']  ?? ''), ENT_QUOTES);
        $zipCity    = htmlspecialchars(
            trim(($order['bill_zip'] ?? '') . ' ' . ($order['bill_city'] ?? '')),
            ENT_QUOTES
        );
        $country    = htmlspecialchars((string) ($order['bill_country'] ?? ''), ENT_QUOTES);
        $orderRef   = htmlspecialchars((string) ($order['order_reference'] ?? ''), ENT_QUOTES);

        $rows        = $this->buildItemsRows($items);
        $discountRow = $this->buildDiscountRow($shippingDiscount);
        $totalsRows  = $this->buildTotalsRows($total);
        $logoTag     = $this->buildLogoTag($logoPath);

        $sectionLabel = 'font-size:7pt;font-weight:bold;color:#c1a14b;text-transform:uppercase;'
            . 'letter-spacing:1.5px;margin:0 0 6px;';

        return '
<style>
body  { font-family: dejavusans; font-size: 9.5pt; color: #2c1a0e; line-height: 1.5; }
table { width: 100%; border-collapse: collapse; }
</style>

<!-- HEADER : logo gauche | FACTURE centre | N°/date droite -->
<table style="margin-bottom:10px;">
<tr>
<td style="width:30%;vertical-align:bottom;">' . $logoTag . '</td>
<td style="width:40%;text-align:center;vertical-align:bottom;padding-bottom:4px;">
  <span style="font-size:28pt;font-weight:bold;color:#c1a14b;letter-spacing:4px;">FACTURE</span>
</td>
<td style="width:30%;text-align:right;vertical-align:bottom;padding-bottom:4px;">
  <span style="font-size:9pt;color:#555;">N&#176;&nbsp;' . htmlspecialchars($invoiceNumber, ENT_QUOTES) . '</span><br>
  <span style="font-size:9pt;color:#555;">Date&nbsp;:&nbsp;' . $orderedAt . '</span>
</td>
</tr>
</table>

<hr style="border:0;border-top:1px solid #c1a14b;margin-bottom:20px;" />

<!-- VENDEUR (gauche) + CLIENT décalé (droite, commence plus bas) -->
<table style="margin-bottom:20px;">
<tr>
<td style="width:52%;vertical-align:top;padding-right:12px;">
  <p style="' . $sectionLabel . '">Vendeur</p>
  <p style="font-size:9pt;line-height:1.8;margin:0;">
    <strong>' . htmlspecialchars(self::APP_NAME, ENT_QUOTES) . '</strong><br>
    ' . htmlspecialchars(self::VENDOR_NAME, ENT_QUOTES) . '<br>'
        . nl2br(htmlspecialchars(self::VENDOR_ADDR, ENT_QUOTES)) . '
  </p>
  <p style="font-size:8pt;color:#8a7060;line-height:1.8;margin:8px 0 0;">
    ' . htmlspecialchars(self::VENDOR_FORM, ENT_QUOTES) . '<br>
    ' . htmlspecialchars(self::VENDOR_SIRET, ENT_QUOTES) . '<br>
    ' . htmlspecialchars(self::VENDOR_RCS, ENT_QUOTES) . '<br>
    ' . htmlspecialchars(self::VENDOR_TVA, ENT_QUOTES) . '
  </p>
</td>
<td style="width:4%;"></td>
<td style="width:44%;vertical-align:top;padding-top:28px;">
  <p style="' . $sectionLabel . '">Adresse de facturation</p>
  <p style="font-size:9pt;line-height:1.8;margin:0;">
    <strong>' . $clientName . '</strong><br>
    ' . $street . '<br>
    ' . $zipCity . '<br>
    ' . $country . '
  </p>
</td>
</tr>
</table>

<!-- BANDEAU INFOS COMMANDE -->
<table style="margin-bottom:22px;background:#f5f0e8;border:1px solid #ddd3be;">
<tr>
<td style="padding:8px 12px;width:34%;border-right:1px solid #ddd3be;">
  <span style="font-size:7pt;color:#8a7060;text-transform:uppercase;letter-spacing:1px;">R&#233;f. commande</span><br>
  <strong style="font-size:9pt;">' . $orderRef . '</strong>
</td>
<td style="padding:8px 12px;width:33%;border-right:1px solid #ddd3be;">
  <span style="font-size:7pt;color:#8a7060;text-transform:uppercase;letter-spacing:1px;">Mode de paiement</span><br>
  <strong style="font-size:9pt;">' . $paymentLabel . '</strong>
</td>
<td style="padding:8px 12px;width:33%;">
  <span style="font-size:7pt;color:#8a7060;text-transform:uppercase;letter-spacing:1px;">Date de commande</span><br>
  <strong style="font-size:9pt;">' . $orderedAt . '</strong>
</td>
</tr>
</table>

<!-- SECTION TITRE ARTICLES -->
<p style="' . $sectionLabel . 'margin-bottom:6px;">D&#201;TAIL DE LA COMMANDE</p>
<table style="margin-bottom:20px;">
<tr>
<th style="padding:7px 8px;text-align:left;color:#f5f0e8;font-size:8pt;width:48%;background-color:#2c1a0e;">Produit</th>
<th style="padding:7px 8px;text-align:center;color:#f5f0e8;font-size:8pt;width:10%;background-color:#2c1a0e;">Qt&#233;</th>
<th style="padding:7px 8px;text-align:right;color:#f5f0e8;font-size:8pt;width:21%;background-color:#2c1a0e;">Prix unit. TTC</th>
<th style="padding:7px 8px;text-align:right;color:#f5f0e8;font-size:8pt;width:21%;background-color:#2c1a0e;">Sous-total TTC</th>
</tr>
' . $rows . $discountRow . $totalsRows . '
</table>

<!-- PIED DE PAGE LÉGAL -->
<hr style="border:0;border-top:1px solid #e0d8cc;margin-bottom:8px;" />
<table>
<tr>
<td style="width:100%;font-size:7pt;color:#aaa;line-height:1.8;">
  Facture &#233;tablie conform&#233;ment &#224; l\'art. L441-9 du Code de commerce.
  TVA non r&#233;cup&#233;rable sur les vins et spiritueux (art. 298 quater du CGI).
  Conform&#233;ment &#224; l\'art. L221-18 du Code de la consommation, vous disposez de 14 jours
  &#224; compter de la r&#233;ception pour exercer votre droit de r&#233;tractation.
</td>
</tr>
<tr><td style="padding-top:5px;font-size:7pt;color:#8a7060;line-height:1.6;border-top:1px solid #e0d8cc;">
  <strong>Avertissement (Art. L3323-4 CSP &#8212; Loi &#201;vin) :</strong>
  L\'abus d\'alcool est dangereux pour la sant&#233;. &#192; consommer avec mod&#233;ration.
</td></tr>
</table>';
    }

    /**
     * Returns an inline logo img tag, or a fallback text span if the logo is absent.
     *
     * @param  string|null $logoPath Absolute path to the PNG, or null.
     * @return string
     */
    private function buildLogoTag(?string $logoPath): string
    {
        if ($logoPath !== null) {
            return '<img src="' . $logoPath . '" style="width:32mm;" />';
        }
        return '<span style="font-size:10pt;font-weight:bold;color:#c1a14b;">'
            . htmlspecialchars(self::APP_NAME, ENT_QUOTES) . '</span>';
    }

    /**
     * Builds the items rows HTML for the invoice table.
     *
     * @param  array<int, array<string, mixed>> $items
     * @return string
     */
    private function buildItemsRows(array $items): string
    {
        $rows = '';
        foreach ($items as $item) {
            $name     = htmlspecialchars(
                (string) ($item['label_name'] ?? $item['name'] ?? '-'),
                ENT_QUOTES
            );
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
     * Builds the shipping discount row HTML, or empty string if no discount.
     *
     * @param  float $shippingDiscount Discount amount (positive value).
     * @return string
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

    /**
     * Builds the totals section rows (spacer, Total HT, TVA, Total TTC).
     *
     * @param  float $total Grand total TTC.
     * @return string
     */
    private function buildTotalsRows(float $total): string
    {
        $tva      = round($total * self::TVA_RATE / (1 + self::TVA_RATE), 2);
        $totalHt  = round($total - $tva, 2);
        $tvaFmt   = number_format($tva, 2, ',', ' ');
        $htFmt    = number_format($totalHt, 2, ',', ' ');
        $totalFmt = number_format($total, 2, ',', ' ');

        return '<tr><td colspan="4" style="padding:0;height:8px;"></td></tr>'
            . '<tr>'
            . '<td colspan="3" style="padding:6px 8px;text-align:right;font-size:8.5pt;color:#555;'
                . 'border-top:1px solid #e0d5c5;">Total HT</td>'
            . '<td style="padding:6px 8px;text-align:right;font-size:8.5pt;color:#555;'
                . 'border-top:1px solid #e0d5c5;">' . $htFmt . '&nbsp;&euro;</td>'
            . '</tr>'
            . '<tr>'
            . '<td colspan="3" style="padding:4px 8px;text-align:right;font-size:8.5pt;color:#555;">'
                . 'TVA (20%)</td>'
            . '<td style="padding:4px 8px;text-align:right;font-size:8.5pt;color:#555;">'
                . $tvaFmt . '&nbsp;&euro;</td>'
            . '</tr>'
            . '<tr>'
            . '<td colspan="3" style="padding:8px 8px;text-align:right;font-weight:bold;'
                . 'font-size:11pt;color:#2c1a0e;background-color:#f5f0e8;'
                . 'border-top:2px solid #c1a14b;">Total TTC</td>'
            . '<td style="padding:8px 8px;text-align:right;font-weight:bold;font-size:11pt;'
                . 'color:#c1a14b;background-color:#f5f0e8;border-top:2px solid #c1a14b;">'
                . $totalFmt . '&nbsp;&euro;</td>'
            . '</tr>';
    }
}
