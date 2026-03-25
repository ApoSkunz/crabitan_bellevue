<?php

/**
 * Seed production — règles de tarification livraison & retrait cave.
 *
 * Usage : php database/seed_pricing_rules_prod.php
 *
 * Règle appliquée au panier :
 *   delivery_price  = remise déduite du total TTC selon le nb de caisses de 12
 *   withdrawal_price = remise fixe de 2,20 € / bouteille (retrait au domaine)
 *
 * Vider la table avant d'insérer si nécessaire :
 *   TRUNCATE pricing_rules;
 *
 * NE PAS exécuter plusieurs fois sans vider la table au préalable.
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

/**
 * Chaque règle représente une tranche de quantité en bouteilles (format bottle).
 * delivery_price = remise appliquée au total pour la livraison
 * withdrawal_price = remise appliquée au total pour le retrait cave (2,20 € / bt)
 *
 * Colonnes : format, min_quantity, max_quantity (null = illimité),
 *            delivery_price, withdrawal_price, label (JSON fr/en), active
 */
$rules = [
    // ---- Moins de 2 caisses (0–23 bt) ----
    [
        'format'           => 'bottle',
        'min_quantity'     => 1,
        'max_quantity'     => 23,
        'delivery_price'   => 0.00,   // pas de remise
        'withdrawal_price' => 2.20,   // 2,20 €/bt
        'label'            => json_encode([
            'fr' => 'Moins de 2 caisses — pas de remise livraison',
            'en' => 'Less than 2 cases — no delivery discount',
        ]),
        'active'           => 1,
    ],
    // ---- 2 caisses exactement (24–35 bt) ----
    [
        'format'           => 'bottle',
        'min_quantity'     => 24,
        'max_quantity'     => 35,
        'delivery_price'   => 15.00,  // 15 € fixe
        'withdrawal_price' => 2.20,
        'label'            => json_encode([
            'fr' => '2 caisses — 15 € de remise livraison',
            'en' => '2 cases — €15 delivery discount',
        ]),
        'active'           => 1,
    ],
    // ---- 3 caisses exactement (36–47 bt) ----
    [
        'format'           => 'bottle',
        'min_quantity'     => 36,
        'max_quantity'     => 47,
        'delivery_price'   => 42.00,  // 42 € fixe
        'withdrawal_price' => 2.20,
        'label'            => json_encode([
            'fr' => '3 caisses — 42 € de remise livraison',
            'en' => '3 cases — €42 delivery discount',
        ]),
        'active'           => 1,
    ],
    // ---- 4–5 caisses (48–71 bt) ----
    [
        'format'           => 'bottle',
        'min_quantity'     => 48,
        'max_quantity'     => 71,
        'delivery_price'   => 1.30,   // 1,30 €/bt (appliqué × quantité dans le panier)
        'withdrawal_price' => 2.20,
        'label'            => json_encode([
            'fr' => '4 à 5 caisses — 1,30 € / bouteille',
            'en' => '4 to 5 cases — €1.30 / bottle',
        ]),
        'active'           => 1,
    ],
    // ---- 6–10 caisses (72–131 bt) ----
    [
        'format'           => 'bottle',
        'min_quantity'     => 72,
        'max_quantity'     => 131,
        'delivery_price'   => 1.50,
        'withdrawal_price' => 2.20,
        'label'            => json_encode([
            'fr' => '6 à 10 caisses — 1,50 € / bouteille',
            'en' => '6 to 10 cases — €1.50 / bottle',
        ]),
        'active'           => 1,
    ],
    // ---- 11–25 caisses (132–311 bt) ----
    [
        'format'           => 'bottle',
        'min_quantity'     => 132,
        'max_quantity'     => 311,
        'delivery_price'   => 1.80,
        'withdrawal_price' => 2.20,
        'label'            => json_encode([
            'fr' => '11 à 25 caisses — 1,80 € / bouteille',
            'en' => '11 to 25 cases — €1.80 / bottle',
        ]),
        'active'           => 1,
    ],
    // ---- Plus de 25 caisses (312 bt et +) ----
    [
        'format'           => 'bottle',
        'min_quantity'     => 312,
        'max_quantity'     => null,
        'delivery_price'   => 1.80,
        'withdrawal_price' => 2.20,
        'label'            => json_encode([
            'fr' => 'Plus de 25 caisses — 1,80 € / bouteille',
            'en' => 'More than 25 cases — €1.80 / bottle',
        ]),
        'active'           => 1,
    ],
];

$stmt = $pdo->prepare(
    "INSERT INTO pricing_rules
     (format, min_quantity, max_quantity, delivery_price, withdrawal_price, label, active)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);

foreach ($rules as $rule) {
    $stmt->execute([
        $rule['format'],
        $rule['min_quantity'],
        $rule['max_quantity'],
        $rule['delivery_price'],
        $rule['withdrawal_price'],
        $rule['label'],
        $rule['active'],
    ]);
    $qty = $rule['min_quantity'] . '–' . ($rule['max_quantity'] ?? '∞');
    echo "  OK    [{$rule['format']}] {$qty} bt\n";
}

echo "\nSeed pricing terminé — " . count($rules) . " règles insérées.\n";
