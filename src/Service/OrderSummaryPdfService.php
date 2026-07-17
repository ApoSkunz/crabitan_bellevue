<?php

declare(strict_types=1);

namespace Service;

/**
 * Génère un PDF récapitulatif (sans mentions légales SIRET/TVA) joint au mail
 * de notification de nouvelle commande envoyé au gérant.
 *
 * Différent de InvoiceService : pas une facture légale, juste un récap interne.
 */
class OrderSummaryPdfService
{
    use PdfBrandingTrait;

    private const APP_NAME = 'Château Crabitan Bellevue';

    /**
     * Génère le PDF récap et retourne son contenu binaire.
     *
     * @param array{
     *   reference: string,
     *   ordered_at: string,
     *   payment_method: string,
     *   items: array<int, array<string, mixed>>,
     *   total: float,
     *   shipping_discount?: float,
     *   billing: array<string, mixed>,
     *   delivery?: array<string, mixed>|null,
     *   customer_note?: string|null
     * } $data
     */
    public function generate(array $data): string
    {
        require_once ROOT_PATH . '/vendor/tecnickcom/tcpdf/tcpdf.php';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(self::APP_NAME);
        $pdf->SetAuthor(self::APP_NAME);
        $pdf->SetTitle('Récapitulatif commande ' . ($data['reference'] ?? ''));
        $pdf->SetMargins(15, 18, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $logoPath = ROOT_PATH . '/public/assets/images/logo/crabitan-bellevue-logo-restored.png';
        $html     = $this->buildHtml($data, is_file($logoPath) ? $logoPath : null);
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildHtml(array $data, ?string $logoPath): string
    {
        $reference        = htmlspecialchars((string) ($data['reference'] ?? ''), ENT_QUOTES);
        $orderedAtRaw     = (string) ($data['ordered_at'] ?? 'now');
        $orderedAt        = date('d/m/Y à H:i', strtotime($orderedAtRaw) ?: time());
        $paymentMethod    = (string) ($data['payment_method'] ?? '');
        $items            = (array)  ($data['items'] ?? []);
        $total            = (float)  ($data['total'] ?? 0.0);
        $shippingDiscount = (float)  ($data['shipping_discount'] ?? 0.0);
        $deliveryMethod   = (string) ($data['delivery_method'] ?? 'shipping');
        $billing          = (array)  ($data['billing']  ?? []);
        $delivery         = isset($data['delivery']) && is_array($data['delivery']) ? $data['delivery'] : null;
        $customerNote     = trim((string) ($data['customer_note'] ?? ''));

        $paymentLabels = [
            'card'     => 'Carte bancaire',
            'virement' => 'Virement bancaire',
            'cheque'   => 'Chèque',
        ];
        $paymentLabel = $paymentLabels[$paymentMethod]
            ?? htmlspecialchars($paymentMethod, ENT_QUOTES);

        $logoTag      = $this->buildLogoTag($logoPath, self::APP_NAME);
        $rows         = $this->buildItemsRows($items, true);
        $discountRow  = $this->buildDiscountRow($shippingDiscount, $deliveryMethod);
        $totalRow     = $this->buildTotalRow($total);
        $billingHtml  = $this->buildAddressBlock($billing);
        $deliveryHtml = $delivery !== null
            ? $this->buildAddressBlock($delivery)
            : '<p style="font-size:9pt;color:#8a7060;font-style:italic;margin:0;">Identique à la facturation</p>';

        $sectionLabel = 'font-size:7pt;font-weight:bold;color:#c1a14b;text-transform:uppercase;'
            . 'letter-spacing:1.5px;margin:0 0 6px;';

        $noteHtml = $customerNote !== '' ? '
<p style="' . $sectionLabel . '">Commentaire client</p>
<p style="font-size:9pt;margin:0 0 14px;white-space:pre-line;">' . nl2br(htmlspecialchars($customerNote, ENT_QUOTES)) . '</p>' : '';

        return '
<style>
body  { font-family: dejavusans; font-size: 9.5pt; color: #2c1a0e; line-height: 1.5; }
table { width: 100%; border-collapse: collapse; }
</style>

<table style="margin-bottom:10px;">
<tr>
<td style="width:30%;vertical-align:bottom;">' . $logoTag . '</td>
<td style="width:40%;text-align:center;vertical-align:bottom;padding-bottom:6px;">
  <span style="font-family:dejavuserif;font-size:13pt;font-weight:normal;color:#c1a14b;letter-spacing:5px;">RÉCAPITULATIF COMMANDE</span><br>
  <span style="font-family:dejavuserif;font-size:6.5pt;font-weight:normal;color:#8a7060;letter-spacing:3px;">Ch&#226;teau Crabitan Bellevue</span>
</td>
<td style="width:30%;text-align:right;vertical-align:bottom;padding-bottom:4px;">
  <span style="font-size:9pt;color:#555;">R&#233;f.&nbsp;' . $reference . '</span><br>
  <span style="font-size:9pt;color:#555;">' . $orderedAt . '</span>
</td>
</tr>
</table>

<hr style="border:0;border-top:1px solid #c1a14b;margin-bottom:18px;" />

<table style="margin-bottom:18px;background:#f5f0e8;border:1px solid #ddd3be;">
<tr>
<td style="padding:8px 12px;width:50%;border-right:1px solid #ddd3be;">
  <span style="font-size:7pt;color:#8a7060;text-transform:uppercase;letter-spacing:1px;">Mode de paiement</span><br>
  <strong style="font-size:9pt;">' . $paymentLabel . '</strong>
</td>
<td style="padding:8px 12px;width:50%;">
  <span style="font-size:7pt;color:#8a7060;text-transform:uppercase;letter-spacing:1px;">R&#233;f&#233;rence</span><br>
  <strong style="font-size:9pt;">' . $reference . '</strong>
</td>
</tr>
</table>

<table style="margin-bottom:18px;">
<tr>
<td style="width:48%;vertical-align:top;padding-right:8px;">
  <p style="' . $sectionLabel . '">Adresse de facturation</p>
  ' . $billingHtml . '
</td>
<td style="width:4%;"></td>
<td style="width:48%;vertical-align:top;">
  <p style="' . $sectionLabel . '">Adresse de livraison</p>
  ' . $deliveryHtml . '
</td>
</tr>
</table>

<p style="' . $sectionLabel . 'margin-bottom:6px;">D&#201;TAIL DE LA COMMANDE</p>
<table style="margin-bottom:14px;">
<tr>
<th style="padding:7px 8px;text-align:left;color:#f5f0e8;font-size:8pt;width:50%;background-color:#2c1a0e;">Produit</th>
<th style="padding:7px 8px;text-align:center;color:#f5f0e8;font-size:8pt;width:10%;background-color:#2c1a0e;">Qt&#233;</th>
<th style="padding:7px 8px;text-align:right;color:#f5f0e8;font-size:8pt;width:20%;background-color:#2c1a0e;">Prix unit. TTC</th>
<th style="padding:7px 8px;text-align:right;color:#f5f0e8;font-size:8pt;width:20%;background-color:#2c1a0e;">Sous-total TTC</th>
</tr>
' . $rows . $discountRow . $totalRow . '
</table>
' . $noteHtml . '
<p style="font-size:7pt;color:#aaa;margin:18px 0 0;font-style:italic;">
  Document interne &#8212; r&#233;capitulatif de commande. Ne tient pas lieu de facture.
</p>';
    }

    private function buildTotalRow(float $total): string
    {
        $totalFmt = number_format($total, 2, ',', ' ');
        return '<tr><td colspan="4" style="padding:0;height:6px;"></td></tr>'
            . '<tr>'
            . '<td colspan="3" style="padding:8px 8px;text-align:right;font-weight:bold;'
                . 'font-size:11pt;color:#2c1a0e;background-color:#f5f0e8;'
                . 'border-top:2px solid #c1a14b;">Total TTC</td>'
            . '<td style="padding:8px 8px;text-align:right;font-weight:bold;font-size:11pt;'
                . 'color:#c1a14b;background-color:#f5f0e8;border-top:2px solid #c1a14b;">'
                . $totalFmt . '&nbsp;&euro;</td>'
            . '</tr>';
    }

    /**
     * @param array<string, mixed> $address
     */
    private function buildAddressBlock(array $address): string
    {
        $civility  = trim((string) ($address['civility']  ?? ''));
        $firstname = htmlspecialchars(trim((string) ($address['firstname'] ?? '')), ENT_QUOTES);
        $lastname  = htmlspecialchars(trim((string) ($address['lastname']  ?? '')), ENT_QUOTES);
        $name      = trim("{$civility} {$firstname} {$lastname}");
        $street    = htmlspecialchars((string) ($address['street']  ?? ''), ENT_QUOTES);
        $zip       = (string) ($address['zip'] ?? $address['zip_code'] ?? '');
        $city      = (string) ($address['city'] ?? '');
        $zipCity   = htmlspecialchars(trim($zip . ' ' . $city), ENT_QUOTES);
        $country   = htmlspecialchars((string) ($address['country'] ?? ''), ENT_QUOTES);
        $phone     = htmlspecialchars((string) ($address['phone']   ?? ''), ENT_QUOTES);

        if ($name === '' && $street === '' && $zipCity === '') {
            return '<p style="font-size:9pt;color:#8a7060;font-style:italic;margin:0;">Aucune adresse renseignée</p>';
        }

        $phoneLine = $phone !== ''
            ? '<br><span style="color:#8a7060;font-size:8pt;">Tél : ' . $phone . '</span>'
            : '';

        return '<p style="font-size:9pt;line-height:1.7;margin:0;">'
            . '<strong>' . $name . '</strong><br>'
            . $street . '<br>'
            . $zipCity . '<br>'
            . $country
            . $phoneLine
            . '</p>';
    }
}
