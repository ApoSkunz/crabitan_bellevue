<?php

/**
 * Seed de développement — 100 commandes pour camille.david@dev.local.
 *
 * Prérequis : seed_dev_data.php exécuté au préalable (compte + adresses déjà présents).
 *
 * Usage : php database/seed_orders_camille.php
 * NE PAS exécuter en production.
 */

declare(strict_types=1);

$env = parse_ini_file(dirname(__DIR__) . '/.env');
if ($env === false) {
    fwrite(STDERR, "Impossible de lire .env\n");
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $env['DB_HOST'] ?? '127.0.0.1',
    $env['DB_NAME'] ?? ''
);
$pdo = new PDO($dsn, $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ----------------------------------------------------------------
// Récupère le compte camille.david@dev.local
// ----------------------------------------------------------------

$row = $pdo->prepare("SELECT id FROM accounts WHERE email = ?");
$row->execute(['camille.david@dev.local']);
$account = $row->fetch();

if (!$account) {
    fwrite(STDERR, "Compte camille.david@dev.local introuvable. Exécutez d'abord seed_dev_data.php.\n");
    exit(1);
}

$accountId = (int) $account['id'];

// ----------------------------------------------------------------
// Récupère ses adresses
// ----------------------------------------------------------------

$addrStmt = $pdo->prepare(
    "SELECT id, type FROM addresses WHERE user_id = ? AND saved = 1 ORDER BY type ASC, id ASC"
);
$addrStmt->execute([$accountId]);
$addrs     = $addrStmt->fetchAll();
$billingId  = null;
$deliveryId = null;

foreach ($addrs as $addr) {
    if ($addr['type'] === 'billing'  && $billingId  === null) {
        $billingId  = (int) $addr['id'];
    }
    if ($addr['type'] === 'delivery' && $deliveryId === null) {
        $deliveryId = (int) $addr['id'];
    }
}

// Si pas d'adresse de livraison, on utilise la facturation
if ($deliveryId === null) {
    $deliveryId = $billingId;
}

if ($billingId === null) {
    fwrite(STDERR, "Aucune adresse trouvée pour camille.david@dev.local.\n");
    exit(1);
}

// ----------------------------------------------------------------
// Vins disponibles
// ----------------------------------------------------------------

$wines = $pdo->query(
    "SELECT id, label_name, vintage, price, format FROM wines WHERE available = 1 LIMIT 30"
)->fetchAll();

if (empty($wines)) {
    fwrite(STDERR, "Aucun vin disponible en base.\n");
    exit(1);
}

// ----------------------------------------------------------------
// Référence unique — récupère le dernier numéro
// ----------------------------------------------------------------

$lastRef = $pdo->query(
    "SELECT MAX(CAST(SUBSTRING_INDEX(order_reference, '-', -1) AS UNSIGNED)) AS mx FROM orders"
)->fetchColumn();
$refCounter = max((int) $lastRef, 2000);

// ----------------------------------------------------------------
// Génération de 100 commandes réparties sur 2 ans
// ----------------------------------------------------------------

$statuses       = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'delivered', 'delivered', 'cancelled'];
$paymentMethods = ['card', 'card', 'card', 'virement', 'cheque'];
$now            = time();
$twoYearsAgo    = $now - (2 * 365 * 24 * 3600);

$inserted = 0;
$errors   = 0;

for ($i = 0; $i < 100; $i++) {
    // Sélection aléatoire de 1 à 3 articles
    $nbItems = rand(1, 3);
    $shuffle = $wines;
    shuffle($shuffle);
    $items     = array_slice($shuffle, 0, $nbItems);
    $total     = 0.0;
    $content   = [];

    foreach ($items as $wine) {
        $qty     = rand(1, 12);
        $price   = (float) $wine['price'];
        $total  += round($price * $qty, 2);
        $content[] = [
            'wine_id'    => (int) $wine['id'],
            'qty'        => $qty,
            'price'      => number_format($price, 2, '.', ''),
            'label_name' => $wine['label_name'],
            'vintage'    => (int) $wine['vintage'],
            'format'     => $wine['format'],
        ];
    }

    $total     = round($total, 2);
    $reference = 'CBV-' . date('Y') . '-' . str_pad((string) ++$refCounter, 6, '0', STR_PAD_LEFT);
    $status    = $statuses[array_rand($statuses)];
    $payment   = $paymentMethods[array_rand($paymentMethods)];

    // Date aléatoire sur les 2 dernières années
    $timestamp  = rand($twoYearsAgo, $now);
    $orderedAt  = date('Y-m-d H:i:s', $timestamp);

    // Unicité de la référence
    $check = $pdo->prepare("SELECT id FROM orders WHERE order_reference = ?");
    $check->execute([$reference]);
    if ($check->fetchColumn()) {
        $reference .= '-' . rand(100, 999);
    }

    try {
        $pdo->prepare(
            "INSERT INTO orders
             (user_id, order_reference, content, price, payment_method,
              id_billing_address, id_delivery_address, status, ordered_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $accountId,
            $reference,
            json_encode($content),
            $total,
            $payment,
            $billingId,
            $deliveryId,
            $status,
            $orderedAt,
        ]);
        ++$inserted;
        echo "  OK    {$reference} — {$status} — {$total} € ({$orderedAt})\n";
    } catch (\Throwable $e) {
        ++$errors;
        echo "  ERR   {$reference} — {$e->getMessage()}\n";
    }
}

echo "\nSeed terminé : {$inserted} commandes insérées, {$errors} erreurs.\n";
echo "Compte : camille.david@dev.local (#{$accountId})\n";
