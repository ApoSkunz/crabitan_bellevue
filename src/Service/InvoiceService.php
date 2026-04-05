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
    private const VENDOR_NAME = 'G.F.A Bernard Solane & Fils';
    private const VENDOR_ADDR = "au Crabitan\n33410 Sainte-Croix-du-Mont\nFrance";
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
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $html = $this->buildHtml($order, $invoiceNumber);
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    /**
     * Builds the HTML content for the TCPDF invoice.
     *
     * @param  array<string, mixed> $order
     * @param  string               $invoiceNumber
     * @return string
     */
    private function buildHtml(array $order, string $invoiceNumber): string
    {
        $items            = json_decode((string) ($order['content'] ?? '[]'), true) ?: [];
        $total            = (float) ($order['price']            ?? 0.0);
        $shippingDiscount = (float) ($order['shipping_discount'] ?? 0.0);
        $orderedAt        = date('d/m/Y', strtotime((string) ($order['ordered_at'] ?? 'now')));
        $paymentLabels    = [
            'card'     => 'Carte bancaire',
            'virement' => 'Virement bancaire',
            'cheque'   => 'Chèque',
        ];
        $paymentLabel = $paymentLabels[$order['payment_method'] ?? ''] ?? htmlspecialchars(
            (string) ($order['payment_method'] ?? ''),
            ENT_QUOTES
        );

        // Billing address
        $civility = trim((string) ($order['bill_civility']  ?? ''));
        $fname    = htmlspecialchars(trim((string) ($order['bill_firstname'] ?? '')), ENT_QUOTES);
        $lname    = htmlspecialchars(trim((string) ($order['bill_lastname']  ?? '')), ENT_QUOTES);
        $clientName = trim("{$civility} {$fname} {$lname}");
        $street     = htmlspecialchars((string) ($order['bill_street']  ?? ''), ENT_QUOTES);
        $zipCity    = htmlspecialchars(
            trim(($order['bill_zip'] ?? '') . ' ' . ($order['bill_city'] ?? '')),
            ENT_QUOTES
        );
        $country = htmlspecialchars((string) ($order['bill_country'] ?? ''), ENT_QUOTES);

        // Items rows
        $subtotalItems = 0.0;
        $rows          = '';
        foreach ($items as $item) {
            $name     = htmlspecialchars(
                (string) ($item['label_name'] ?? $item['name'] ?? '—'),
                ENT_QUOTES
            );
            $qty      = (int) ($item['qty']   ?? 0);
            $price    = (float) ($item['price'] ?? 0.0);
            $subtotal = $qty * $price;
            $subtotalItems += $subtotal;
            $rows .= '<tr>'
                . "<td>{$name}</td>"
                . "<td style=\"text-align:center;\">{$qty}</td>"
                . "<td style=\"text-align:right;\">" . number_format($price, 2, ',', ' ') . '&nbsp;€</td>'
                . "<td style=\"text-align:right;\">" . number_format($subtotal, 2, ',', ' ') . '&nbsp;€</td>'
                . '</tr>';
        }

        // Delivery discount row
        $discountRow = '';
        if ($shippingDiscount > 0.0) {
            $discountRow = '<tr>'
                . '<td colspan="3"><em>Remise livraison</em></td>'
                . '<td style="text-align:right;color:#c9a84c;">'
                . '−' . number_format($shippingDiscount, 2, ',', ' ') . '&nbsp;€'
                . '</td>'
                . '</tr>';
        }

        // TVA breakdown (all prices are TTC, 20% standard rate)
        $tva      = round($total * self::TVA_RATE / (1 + self::TVA_RATE), 2);
        $totalHt  = round($total - $tva, 2);
        $tvaFmt   = number_format($tva, 2, ',', ' ');
        $htFmt    = number_format($totalHt, 2, ',', ' ');
        $totalFmt = number_format($total, 2, ',', ' ');

        return '
<style>
body  { font-family: dejavusans; font-size: 10pt; color: #1a1208; }
h1    { font-size: 16pt; color: #c1a14b; margin: 0 0 4px; }
h2    { font-size: 10pt; color: #c1a14b; margin: 12px 0 4px; border-bottom: 1px solid #c1a14b; }
table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
th    { background: #f5f0e8; font-weight: bold; padding: 4px 6px; text-align: left; font-size: 9pt; }
td    { padding: 4px 6px; border-bottom: 1px solid #e8e0d0; font-size: 9pt; }
.muted  { color: #8a7060; font-size: 9pt; }
.addr   { font-size: 9pt; line-height: 1.6; }
.right  { text-align: right; }
.total  { font-weight: bold; font-size: 10pt; background: #f5f0e8; }
.legal  { font-size: 8pt; color: #888; margin-top: 14px;
          border-top: 1px solid #e0d8cc; padding-top: 8px; }
</style>
<h1>' . htmlspecialchars(self::APP_NAME, ENT_QUOTES) . '</h1>
<p class="muted">FACTURE — ' . htmlspecialchars($invoiceNumber, ENT_QUOTES) . '</p>

<table><tr>
<td style="width:50%;vertical-align:top;">
  <h2>Vendeur</h2>
  <p class="addr"><strong>' . htmlspecialchars(self::VENDOR_NAME, ENT_QUOTES) . '</strong><br>'
        . nl2br(htmlspecialchars(self::VENDOR_ADDR, ENT_QUOTES)) . '</p>
</td>
<td style="width:50%;vertical-align:top;">
  <h2>Client</h2>
  <p class="addr">' . $clientName . '<br>' . $street . '<br>' . $zipCity . '<br>' . $country . '</p>
</td>
</tr></table>

<h2>Informations de facturation</h2>
<table><tr>
<td><strong>N° facture :</strong> ' . htmlspecialchars($invoiceNumber, ENT_QUOTES) . '</td>
<td><strong>Date :</strong> ' . $orderedAt . '</td>
<td><strong>Réf. commande :</strong> '
        . htmlspecialchars((string) ($order['order_reference'] ?? ''), ENT_QUOTES) . '</td>
<td><strong>Paiement :</strong> ' . $paymentLabel . '</td>
</tr></table>

<h2>Détail de la commande</h2>
<table>
<tr>
<th style="width:50%;">Produit</th>
<th style="width:10%;text-align:center;">Qté</th>
<th style="width:20%;text-align:right;">Prix unit. TTC</th>
<th style="width:20%;text-align:right;">Sous-total TTC</th>
</tr>
' . $rows . $discountRow . '
<tr>
<td colspan="3" class="right"><em>Total HT</em></td>
<td class="right"><em>' . $htFmt . '&nbsp;€</em></td>
</tr>
<tr>
<td colspan="3" class="right"><em>TVA (20%)</em></td>
<td class="right"><em>' . $tvaFmt . '&nbsp;€</em></td>
</tr>
<tr class="total">
<td colspan="3" class="right"><strong>Total TTC</strong></td>
<td class="right"><strong>' . $totalFmt . '&nbsp;€</strong></td>
</tr>
</table>

<p class="legal">
Facture établie conformément à l\'art. L441-9 du Code de commerce.
TVA non récupérable sur les vins et spiritueux (art. 298 quater du CGI).
Conformément à l\'art. L221-18 du Code de la consommation, vous disposez
de 14 jours à compter de la réception pour exercer votre droit de rétractation.
</p>';
    }
}
