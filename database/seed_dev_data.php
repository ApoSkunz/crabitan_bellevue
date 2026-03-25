<?php

/**
 * Seed de développement enrichi — données fictives pour tests locaux.
 *
 * Crée :
 *   - 18 comptes clients (particuliers + sociétés, vérifiés / non-vérifiés)
 *   - Adresses de livraison & facturation (France métropolitaine hors Corse)
 *   - Paniers actifs (quelques clients)
 *   - Connexions (historique sessions)
 *   - Favoris (quelques vins)
 *   - Commandes (statuts variés — livraison uniquement, pas de retrait cave)
 *
 * Usage : php database/seed_dev_data.php
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

$password = password_hash('Dev123456789!', PASSWORD_BCRYPT, ['cost' => 12]);
$now      = date('Y-m-d H:i:s');

// ============================================================
// Helpers
// ============================================================

function insertOrSkip(PDO $pdo, string $email): ?int
{
    $check = $pdo->prepare("SELECT id FROM accounts WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetchColumn()) {
        echo "  SKIP  {$email} (déjà existant)\n";
        return null;
    }
    return 0; // signal to insert
}

// ============================================================
// 1. Comptes clients
// ============================================================

/**
 * Adresses disponibles par ville — France métropolitaine hors Corse (pas de 20xxx, 97xxx+)
 *
 * @var array<int, array{city: string, zip: string, street: string, phone: string}>
 */
$cities = [
    ['city' => 'Paris',        'zip' => '75008', 'street' => '14 rue du Faubourg Saint-Honoré', 'phone' => '0612345678'],
    ['city' => 'Lyon',         'zip' => '69002', 'street' => '8 place Bellecour',                'phone' => '0623456789'],
    ['city' => 'Marseille',    'zip' => '13006', 'street' => '22 boulevard Michelet',            'phone' => '0634567890'],
    ['city' => 'Bordeaux',     'zip' => '33000', 'street' => '5 cours de l\'Intendance',         'phone' => '0645678901'],
    ['city' => 'Toulouse',     'zip' => '31000', 'street' => '17 rue de la République',          'phone' => '0656789012'],
    ['city' => 'Nice',         'zip' => '06000', 'street' => '3 promenade des Anglais',          'phone' => '0667890123'],
    ['city' => 'Nantes',       'zip' => '44000', 'street' => '12 place Royale',                  'phone' => '0678901234'],
    ['city' => 'Strasbourg',   'zip' => '67000', 'street' => '6 place de la Cathédrale',         'phone' => '0689012345'],
    ['city' => 'Lille',        'zip' => '59000', 'street' => '30 rue de Paris',                  'phone' => '0690123456'],
    ['city' => 'Rennes',       'zip' => '35000', 'street' => '4 place de la Mairie',             'phone' => '0601234567'],
    ['city' => 'Montpellier',  'zip' => '34000', 'street' => '9 place de la Comédie',            'phone' => '0611234567'],
    ['city' => 'Grenoble',     'zip' => '38000', 'street' => '2 rue Félix Viallet',              'phone' => '0622345678'],
    ['city' => 'Bayonne',      'zip' => '64100', 'street' => '15 rue Port Neuf',                 'phone' => '0633456789'],
    ['city' => 'Agen',         'zip' => '47000', 'street' => '11 boulevard de la République',    'phone' => '0644567890'],
    ['city' => 'Périgueux',    'zip' => '24000', 'street' => '7 place Francheville',             'phone' => '0655678901'],
    ['city' => 'Mont-de-Marsan', 'zip' => '40000', 'street' => '19 rue Victor Hugo',            'phone' => '0666789012'],
    ['city' => 'Poitiers',     'zip' => '86000', 'street' => '5 place du Maréchal Leclerc',      'phone' => '0677890123'],
    ['city' => 'Tours',        'zip' => '37000', 'street' => '23 place Jean Jaurès',             'phone' => '0688901234'],
];

$clients = [
    // ---- Particuliers vérifiés ----
    ['email' => 'jean.dupont@dev.local',       'civility' => 'M', 'firstname' => 'Jean',      'lastname' => 'Dupont',       'newsletter' => 1, 'verified' => true],
    ['email' => 'marie.martin@dev.local',      'civility' => 'F', 'firstname' => 'Marie',     'lastname' => 'Martin',       'newsletter' => 1, 'verified' => true],
    ['email' => 'pierre.bernard@dev.local',    'civility' => 'M', 'firstname' => 'Pierre',    'lastname' => 'Bernard',      'newsletter' => 0, 'verified' => true],
    ['email' => 'claire.leroy@dev.local',      'civility' => 'F', 'firstname' => 'Claire',    'lastname' => 'Leroy',        'newsletter' => 1, 'verified' => true],
    ['email' => 'thomas.moreau@dev.local',     'civility' => 'M', 'firstname' => 'Thomas',    'lastname' => 'Moreau',       'newsletter' => 0, 'verified' => true],
    ['email' => 'isabelle.simon@dev.local',    'civility' => 'F', 'firstname' => 'Isabelle',  'lastname' => 'Simon',        'newsletter' => 1, 'verified' => true],
    ['email' => 'nicolas.laurent@dev.local',   'civility' => 'M', 'firstname' => 'Nicolas',   'lastname' => 'Laurent',      'newsletter' => 1, 'verified' => true],
    ['email' => 'aurelie.michel@dev.local',    'civility' => 'F', 'firstname' => 'Aurélie',   'lastname' => 'Michel',       'newsletter' => 0, 'verified' => true],
    ['email' => 'julien.garcia@dev.local',     'civility' => 'M', 'firstname' => 'Julien',    'lastname' => 'Garcia',       'newsletter' => 1, 'verified' => true],
    ['email' => 'camille.david@dev.local',     'civility' => 'F', 'firstname' => 'Camille',   'lastname' => 'David',        'newsletter' => 1, 'verified' => true],
    ['email' => 'luc.robert@dev.local',        'civility' => 'M', 'firstname' => 'Luc',       'lastname' => 'Robert',       'newsletter' => 0, 'verified' => true],
    ['email' => 'sandrine.petit@dev.local',    'civility' => 'F', 'firstname' => 'Sandrine',  'lastname' => 'Petit',        'newsletter' => 1, 'verified' => true],
    // ---- Particuliers non vérifiés ----
    ['email' => 'etienne.blanc@dev.local',     'civility' => 'M', 'firstname' => 'Étienne',   'lastname' => 'Blanc',        'newsletter' => 0, 'verified' => false],
    ['email' => 'valerie.guerin@dev.local',    'civility' => 'F', 'firstname' => 'Valérie',   'lastname' => 'Guérin',       'newsletter' => 0, 'verified' => false],
    // ---- Sociétés vérifiées ----
    ['email' => 'cavesdubordelais@dev.local',  'type' => 'company', 'company_name' => 'Les Caves du Bordelais SARL',   'siret' => '45678901200034', 'newsletter' => 1, 'verified' => true],
    ['email' => 'restaurantlacave@dev.local',  'type' => 'company', 'company_name' => 'Restaurant La Cave SAS',        'siret' => '56789012300045', 'newsletter' => 1, 'verified' => true],
    ['email' => 'vinsetpassions@dev.local',    'type' => 'company', 'company_name' => 'Vins et Passions',              'siret' => '67890123400056', 'newsletter' => 0, 'verified' => true],
    // ---- Société non vérifiée ----
    ['email' => 'chaidumanoir@dev.local',      'type' => 'company', 'company_name' => 'Chai du Manoir EURL',           'siret' => null,             'newsletter' => 0, 'verified' => false],
];

$insertedAccounts = []; // email => account_id

foreach ($clients as $i => $client) {
    if (insertOrSkip($pdo, $client['email']) === null) {
        continue;
    }

    $pdo->beginTransaction();
    try {
        $type     = $client['type'] ?? 'individual';
        $verified = ($client['verified'] ?? false) ? $now : null;

        $stmt = $pdo->prepare(
            "INSERT INTO accounts (email, password, lang, role, account_type, newsletter,
                                   email_verified_at, created_at, updated_at)
             VALUES (?, ?, 'fr', 'customer', ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $client['email'],
            $password,
            $type,
            $client['newsletter'],
            $verified,
        ]);
        $accountId = (int) $pdo->lastInsertId();

        if ($type === 'individual') {
            $pdo->prepare(
                "INSERT INTO account_individuals (account_id, firstname, lastname, civility)
                 VALUES (?, ?, ?, ?)"
            )->execute([$accountId, $client['firstname'], $client['lastname'], $client['civility'] ?? 'other']);
        } else {
            $pdo->prepare(
                "INSERT INTO account_companies (account_id, company_name, siret) VALUES (?, ?, ?)"
            )->execute([$accountId, $client['company_name'], $client['siret'] ?? null]);
        }

        $pdo->commit();
        $insertedAccounts[$client['email']] = $accountId;
        echo "  OK    {$client['email']} (#{$accountId})\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo "  ERR   {$client['email']} — {$e->getMessage()}\n";
    }
}

// ============================================================
// 2. Adresses (livraison uniquement — France métropolitaine hors Corse)
// ============================================================

echo "\n--- Adresses ---\n";

$accountIds  = array_values($insertedAccounts);
$addressIds  = []; // account_id => [billing_id, delivery_id]

foreach ($accountIds as $idx => $accountId) {
    $city = $cities[$idx % count($cities)];

    // Récupère prénom/nom depuis account_individuals ou company_name
    $info = $pdo->prepare(
        "SELECT a.account_type,
                ai.firstname, ai.lastname, ai.civility,
                ac.company_name
         FROM accounts a
         LEFT JOIN account_individuals ai ON ai.account_id = a.id
         LEFT JOIN account_companies   ac ON ac.account_id = a.id
         WHERE a.id = ?"
    );
    $info->execute([$accountId]);
    $row = $info->fetch();

    if (!$row) {
        continue;
    }

    if ($row['account_type'] === 'company') {
        $firstname = 'Contact';
        $lastname  = $row['company_name'] ?? 'Société';
        $civility  = 'other';
    } else {
        $firstname = $row['firstname'] ?? 'Prénom';
        $lastname  = $row['lastname']  ?? 'Nom';
        $civility  = $row['civility']  ?? 'other';
    }

    $addrStmt = $pdo->prepare(
        "INSERT INTO addresses (user_id, type, firstname, lastname, civility,
                                street, city, zip_code, country, phone, saved, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'France', ?, 1, NOW())"
    );

    // Adresse de facturation
    $addrStmt->execute([$accountId, 'billing', $firstname, $lastname, $civility,
        $city['street'], $city['city'], $city['zip'], $city['phone']]);
    $billingId = (int) $pdo->lastInsertId();

    // Adresse de livraison (même adresse pour simplifier)
    $addrStmt->execute([$accountId, 'delivery', $firstname, $lastname, $civility,
        $city['street'], $city['city'], $city['zip'], $city['phone']]);
    $deliveryId = (int) $pdo->lastInsertId();

    $addressIds[$accountId] = ['billing' => $billingId, 'delivery' => $deliveryId];
    echo "  OK    addresses pour account #{$accountId} ({$city['city']})\n";
}

// ============================================================
// 3. Vins disponibles (pour paniers, favoris, commandes)
// ============================================================

$wines = $pdo->query(
    "SELECT id, label_name, vintage, price, format FROM wines WHERE available = 1 LIMIT 20"
)->fetchAll();

if (empty($wines)) {
    echo "\n  WARN  Aucun vin disponible — paniers / commandes / favoris ignorés.\n";
    echo "\nSeed terminé. Mot de passe : Dev123456789!\n";
    exit(0);
}

// ============================================================
// 4. Favoris
// ============================================================

echo "\n--- Favoris ---\n";

$favAccounts = array_slice($accountIds, 0, 10);
foreach ($favAccounts as $accountId) {
    $shuffle = $wines;
    shuffle($shuffle);
    $picked = array_slice($shuffle, 0, rand(1, min(3, count($wines))));
    foreach ($picked as $wine) {
        try {
            $pdo->prepare(
                "INSERT IGNORE INTO favorites (user_id, wine_id, created_at) VALUES (?, ?, NOW())"
            )->execute([$accountId, $wine['id']]);
        } catch (\Throwable) {
        }
    }
    echo "  OK    favoris pour account #{$accountId}\n";
}

// ============================================================
// 5. Connexions (historique sessions)
// ============================================================

echo "\n--- Connexions ---\n";

$agents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_2) AppleWebKit/605.1.15 Safari/605.1.15',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/119 Mobile Safari/537.36',
];
$devices = ['Chrome · Windows', 'Safari · macOS', 'Safari · iPhone', 'Chrome · Android'];
$ips     = ['82.65.12.34', '90.45.23.56', '176.31.45.67', '188.73.34.90', '78.120.45.23'];

foreach (array_slice($accountIds, 0, 12) as $accountId) {
    $nbSessions = rand(1, 3);
    for ($s = 0; $s < $nbSessions; $s++) {
        $agentIdx = rand(0, count($agents) - 1);
        $token    = bin2hex(random_bytes(32));
        $device   = bin2hex(random_bytes(16));
        $expiry   = date('Y-m-d H:i:s', strtotime('+1 hour'));
        try {
            $pdo->prepare(
                "INSERT INTO connections
                 (user_id, token, device_token, ip_address, user_agent, device_name,
                  auth_method, status, last_used_at, created_at, expired_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'password', 'active', NOW(), NOW(), ?)"
            )->execute([
                $accountId, $token, $device,
                $ips[array_rand($ips)],
                $agents[$agentIdx], $devices[$agentIdx],
                $expiry,
            ]);
        } catch (\Throwable) {
        }
    }
    echo "  OK    connexions pour account #{$accountId}\n";
}

// ============================================================
// 6. Paniers actifs
// ============================================================

echo "\n--- Paniers ---\n";

$cartAccounts = array_slice($accountIds, 0, 6);
foreach ($cartAccounts as $accountId) {
    $wine    = $wines[array_rand($wines)];
    $qty     = rand(1, 6);
    $price   = (float) $wine['price'];
    $total   = round($price * $qty, 2);
    $content = json_encode([[
        'wine_id'    => (int) $wine['id'],
        'qty'        => $qty,
        'price'      => number_format($price, 2, '.', ''),
        'label_name' => $wine['label_name'],
        'vintage'    => (int) $wine['vintage'],
        'format'     => $wine['format'],
    ]]);
    try {
        $pdo->prepare(
            "INSERT INTO carts (user_id, content, price, withdrawal_price, delivery_price, total_quantity, created_at)
             VALUES (?, ?, ?, 0.00, 0.00, ?, NOW())
             ON DUPLICATE KEY UPDATE content = VALUES(content), price = VALUES(price),
                                     total_quantity = VALUES(total_quantity), updated_at = NOW()"
        )->execute([$accountId, $content, $total, $qty]);
        echo "  OK    panier pour account #{$accountId} ({$qty} x {$wine['label_name']})\n";
    } catch (\Throwable $e) {
        echo "  ERR   panier #{$accountId} — {$e->getMessage()}\n";
    }
}

// ============================================================
// 7. Commandes (livraison uniquement — pas de retrait cave)
// ============================================================

echo "\n--- Commandes ---\n";

$statuses = ['paid', 'processing', 'shipped', 'delivered', 'delivered', 'delivered', 'cancelled'];
$orderAccounts = array_slice($accountIds, 0, min(14, count($accountIds)));
$refCounter    = 1000;

foreach ($orderAccounts as $idx => $accountId) {
    if (empty($addressIds[$accountId])) {
        continue;
    }

    $nbOrders = rand(1, 3);
    for ($o = 0; $o < $nbOrders; $o++) {
        $wine      = $wines[($idx + $o) % count($wines)];
        $qty       = rand(1, 24);
        $price     = (float) $wine['price'];
        $total     = round($price * $qty, 2);
        $reference = 'CBV-' . date('Y') . '-' . str_pad((string) ++$refCounter, 6, '0', STR_PAD_LEFT);
        $status    = $statuses[array_rand($statuses)];
        $content   = json_encode([[
            'wine_id'    => (int) $wine['id'],
            'qty'        => $qty,
            'price'      => number_format($price, 2, '.', ''),
            'label_name' => $wine['label_name'],
            'vintage'    => (int) $wine['vintage'],
            'format'     => $wine['format'],
        ]]);

        // Vérifie unicité reference
        $refCheck = $pdo->prepare("SELECT id FROM orders WHERE order_reference = ?");
        $refCheck->execute([$reference]);
        if ($refCheck->fetchColumn()) {
            $reference .= '-' . rand(10, 99);
        }

        try {
            $pdo->prepare(
                "INSERT INTO orders
                 (user_id, order_reference, content, price, payment_method,
                  id_billing_address, id_delivery_address, status, ordered_at)
                 VALUES (?, ?, ?, ?, 'card', ?, ?, ?, NOW())"
            )->execute([
                $accountId,
                $reference,
                $content,
                $total,
                $addressIds[$accountId]['billing'],
                $addressIds[$accountId]['delivery'],
                $status,
            ]);
            echo "  OK    commande {$reference} (#{$accountId}) — {$status} — {$total} €\n";
        } catch (\Throwable $e) {
            echo "  ERR   commande #{$accountId} — {$e->getMessage()}\n";
        }
    }
}

echo "\nSeed enrichi terminé. Mot de passe : Dev123456789!\n";
echo "Comptes insérés : " . count($insertedAccounts) . "\n";
