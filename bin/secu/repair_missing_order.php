<?php

/**
 * Rattrapage manuel — commande CB payée mais jamais créée (IPN non reçue/échouée).
 *
 * Rejoue la logique de IpnController::handle() à partir du snapshot payment_intents,
 * sans vérification de signature (paiement déjà confirmé manuellement côté back-office Up2pay CA).
 *
 * Prérequis avant exécution :
 *   - Transaction confirmée acceptée dans le back-office Up2pay CA (montant + statut)
 *   - NUMAPPEL / NUMTRANS récupérés depuis ce back-office
 *   - payment_intents contient encore la ligne pour la référence (expirée ou non)
 *
 * Usage (dry-run par défaut, aucune écriture) :
 *   php bin/secu/repair_missing_order.php --ref=WEB-CB-XXXXX-YYYY --numappel=XXXX --numtrans=XXXX
 *
 * Exécution réelle (écrit en BDD, décrémente stock, envoie factures/emails) :
 *   php bin/secu/repair_missing_order.php --ref=WEB-CB-XXXXX-YYYY --numappel=XXXX --numtrans=XXXX --confirm
 */

declare(strict_types=1);

require_once __DIR__ . '/../cron/cron-bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$opts     = getopt('', ['ref:', 'numappel::', 'numtrans::', 'confirm']);
$ref      = $opts['ref']      ?? null;
$numappel = $opts['numappel'] ?? '';
$numtrans = $opts['numtrans'] ?? '';
$confirm  = array_key_exists('confirm', $opts);

if (!$ref) {
    fwrite(STDERR, "Usage: php repair_missing_order.php --ref=WEB-CB-XXXXX-YYYY [--numappel=XXXX] [--numtrans=XXXX] [--confirm]\n");
    exit(1);
}

$ref = preg_replace('/[^A-Za-z0-9\-]/', '', $ref);

$log = static function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

$orders = new \Model\OrderModel();
$carts  = new \Model\CartModel();
$wines  = new \Model\WineModel();
$mail   = new \Service\MailService();
$db     = \Core\Database::getInstance();

// Idempotence — refuse si la commande existe déjà
if ($orders->findByReferenceOnly($ref) !== null) {
    fwrite(STDERR, "[ABORT] Une commande existe déjà pour la référence {$ref} — rien à faire.\n");
    exit(1);
}

// Lecture directe du snapshot, SANS filtre d'expiration (contrairement à PaymentIntentModel::findByReference)
$row = $db->fetchOne(
    'SELECT `reference`, `user_id`, `snapshot`, `expires_at` FROM `payment_intents` WHERE `reference` = ?',
    [$ref]
);

if ($row === false) {
    fwrite(STDERR, "[ABORT] Aucun payment_intents trouvé pour {$ref}.\n");
    exit(1);
}

$snapshot = json_decode((string) $row['snapshot'], true);
if (!is_array($snapshot)) {
    fwrite(STDERR, "[ABORT] Snapshot illisible pour {$ref}.\n");
    exit(1);
}

$log("Référence     : {$ref}");
$log("User ID       : " . (int) $snapshot['user_id']);
$log("Total         : " . (float) $snapshot['total']);
$log("Items         : " . count((array) $snapshot['items']));
$log("Expiré depuis : {$row['expires_at']} (maintenant : " . date('Y-m-d H:i:s') . ')');

if ($numappel === '' || $numtrans === '') {
    $log('[ATTENTION] NUMAPPEL/NUMTRANS non fournis — ca_numappel/ca_numtrans resteront vides en BDD.');
}

if (!$confirm) {
    $log('[DRY-RUN] Aucune écriture effectuée. Relancer avec --confirm pour exécuter.');
    exit(0);
}

$finalRef = $orders->createFromIpn(
    (int)    $snapshot['user_id'],
    (array)  $snapshot['items'],
    (float)  $snapshot['total'],
    (float)  $snapshot['delivery_discount'],
    (int)    $snapshot['billing_address_id'],
    (int)    $snapshot['delivery_address_id'],
    (string) $snapshot['cgv_version'],
    $ref,
    (string) $numappel,
    (string) $numtrans,
    (string) ($snapshot['delivery_method'] ?? 'shipping')
);
$log("Commande créée : {$finalRef}");

$db->execute('DELETE FROM `payment_intents` WHERE `reference` = ?', [$ref]);
$carts->clear((int) $snapshot['user_id']);
$log('Intent supprimé, panier vidé.');

foreach ((array) $snapshot['items'] as $item) {
    $wineId = (int) ($item['wine_id'] ?? 0);
    $qty    = (int) ($item['qty']     ?? 0);
    if ($wineId > 0 && $qty > 0) {
        $wines->decrementStock($wineId, $qty);
        $log("Stock décrémenté — wine_id={$wineId} qty={$qty}");
    }
}

$autoInvoice = \Core\FeatureFlags::getBool('invoice_auto_generate', true);
if ($autoInvoice) {
    $order = $orders->findByReferenceForInvoice($finalRef);
    if ($order !== null) {
        $invoiceNumber = sprintf('WEB-INVOICE-%s-%04d', date('Y'), (int) $order['id']);
        $invoiceDir    = ROOT_PATH . '/storage/invoices';
        if (!is_dir($invoiceDir)) {
            mkdir($invoiceDir, 0755, true);
        }
        $invoicePath = 'storage/invoices/' . $invoiceNumber . '.pdf';
        file_put_contents(
            ROOT_PATH . '/' . $invoicePath,
            (new \Service\InvoiceService())->generate($order, $invoiceNumber)
        );
        $orders->updateInvoiceData((int) $order['id'], $invoiceNumber, $invoicePath);
        $log("Facture générée : {$invoiceNumber}");

        $invoiceLang  = (string) ($snapshot['lang']         ?? 'fr');
        $invoiceEmail = (string) ($snapshot['client_email'] ?? '');
        $invoiceName  = (string) ($snapshot['client_name']  ?? '');
        if ($invoiceEmail !== '') {
            $mail->sendInvoiceToClient(
                $invoiceEmail,
                $invoiceName,
                $finalRef,
                $invoiceNumber,
                realpath(ROOT_PATH . '/' . $invoicePath) ?: ROOT_PATH . '/' . $invoicePath,
                $invoiceLang
            );
            $log('Facture envoyée par email au client.');
        }
    }
}

$pdfContext = (new \Service\OrderPdfContextBuilder())->build($snapshot);
$mail->sendOrderConfirmationToClient(
    (string) ($snapshot['client_email'] ?? ''),
    (string) ($snapshot['client_name']  ?? ''),
    $ref,
    'card',
    (array)  $snapshot['items'],
    (float)  $snapshot['total'],
    (string) ($snapshot['lang'] ?? 'fr'),
    (float)  ($snapshot['delivery_discount'] ?? 0.0),
    $pdfContext,
    (string) ($snapshot['delivery_method'] ?? 'shipping')
);
$mail->sendOrderConfirmationToOwner(
    (string) ($snapshot['client_email'] ?? ''),
    (string) ($snapshot['client_name']  ?? ''),
    $ref,
    'card',
    (array)  $snapshot['items'],
    (float)  $snapshot['total'],
    (float)  ($snapshot['delivery_discount'] ?? 0.0),
    $pdfContext,
    (string) ($snapshot['delivery_method'] ?? 'shipping')
);
$log('Emails de confirmation envoyés (client + gérant).');

\Core\ErrorLogger::write([
    'timestamp' => date('Y-m-d H:i:s'),
    'level'     => 'WARNING',
    'message'   => "[IPN-REPAIR] Commande rattrapée manuellement — ref={$finalRef}",
    'file'      => __FILE__,
    'line'      => __LINE__,
    'trace'     => '',
    'url'       => '',
    'method'    => 'CLI',
]);

$log('TERMINÉ.');
