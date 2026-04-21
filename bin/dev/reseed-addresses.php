<?php

/**
 * Reseed des adresses mock avec la clé DB_ENCRYPT_KEY courante.
 *
 * Utilisation (XAMPP actif) :
 *   php bin/dev/reseed-addresses.php
 *
 * ⚠️  Local uniquement — écrase addresses + order_address_snapshots.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

// ── Env ───────────────────────────────────────────────────────────────────────
$env = is_file(ROOT_PATH . '/.env') ? parse_ini_file(ROOT_PATH . '/.env') : [];
foreach ($env ?: [] as $k => $v) {
    $_ENV[$k] = $v;
}

$rawKey = $_ENV['DB_ENCRYPT_KEY'] ?? '';
if ($rawKey === '') {
    fwrite(STDERR, "[ERROR] DB_ENCRYPT_KEY absente ou vide dans .env\n");
    exit(1);
}

$enc = new \Service\EncryptionService($rawKey);

// ── Données mock en clair ─────────────────────────────────────────────────────
// addresses : [id => [street, phone]]
$mockAddresses = [
    1  => ['14 rue des Vignerons',       '+33 6 12 34 56 78'],
    2  => ['14 rue des Vignerons',       '+33 6 12 34 56 78'],
    3  => ['8 avenue de la Vigne',       '+33 6 87 65 43 21'],
    4  => ['22 chemin du Château',       '+33 5 61 23 45 67'],
    5  => ['22 chemin du Château',       '+33 5 61 23 45 67'],
    6  => ['10 Regent Street',           '+44 20 7946 0958'],
    7  => ['1 place de la République',   '+33 1 42 00 00 00'],
    8  => ['3 quai de Saône',            '+33 4 72 00 11 22'],
    9  => ['5 rue de la Paix',           '+33 1 23 45 67 89'],
];

// order_address_snapshots : [id => [street, phone]]
// snapshot 8 est pseudonymisé (XXXXX) — on ne le touche pas
$mockSnapshots = [
    1  => ['14 rue des Vignerons',       '+33 6 12 34 56 78'],
    2  => ['14 rue des Vignerons',       '+33 6 12 34 56 78'],
    3  => ['22 chemin du Château',       '+33 5 61 23 45 67'],
    4  => ['22 chemin du Château',       '+33 5 61 23 45 67'],
    5  => ['1 place de la République',   '+33 1 42 00 00 00'],
    6  => ['45 rue Bellecour',           '+33 4 78 00 12 34'],
    7  => ['12 allée des Roses',         '+33 2 40 00 55 66'],
    9  => ['3 quai de Saône',            '+33 4 72 00 11 22'],
    10 => ['5 rue de la Paix',           '+33 1 23 45 67 89'],
];

// ── DB ────────────────────────────────────────────────────────────────────────
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'crabitan_bellevue';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASS'] ?? '';
$dbPort = (int) ($_ENV['DB_PORT'] ?? 3306);

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\PDOException $e) {
    fwrite(STDERR, "[ERROR] Connexion BDD : " . $e->getMessage() . "\n");
    exit(1);
}

echo "=== Reseed addresses (clé courante) ===\n\n";

// ── UPDATE addresses ──────────────────────────────────────────────────────────
$stmtAddr = $pdo->prepare("UPDATE addresses SET street = ?, phone = ? WHERE id = ?");
echo "--- addresses ---\n";
$seedAddresses = [];
foreach ($mockAddresses as $id => [$street, $phone]) {
    $encStreet = $enc->encrypt($street);
    $encPhone  = $enc->encrypt($phone);
    $stmtAddr->execute([$encStreet, $encPhone, $id]);
    $seedAddresses[$id] = [$encStreet, $encPhone];
    echo "  id={$id} : {$street} / {$phone} → OK\n";
}

// ── UPDATE order_address_snapshots ────────────────────────────────────────────
$stmtSnap = $pdo->prepare("UPDATE order_address_snapshots SET street = ?, phone = ? WHERE id = ?");
echo "\n--- order_address_snapshots ---\n";
$seedSnapshots = [];
foreach ($mockSnapshots as $id => [$street, $phone]) {
    $encStreet = $enc->encrypt($street);
    $encPhone  = $enc->encrypt($phone);
    $stmtSnap->execute([$encStreet, $encPhone, $id]);
    $seedSnapshots[$id] = [$encStreet, $encPhone];
    echo "  id={$id} : {$street} / {$phone} → OK\n";
}

// ── Génération SQL seed ───────────────────────────────────────────────────────
echo "\n=== SQL à coller dans database/seed_mock_data.sql ===\n\n";
echo "-- addresses (street, phone) — clé actuelle\n";
foreach ($seedAddresses as $id => [$encStreet, $encPhone]) {
    echo "-- id={$id}: UPDATE \`addresses\` SET \`street\`='{$encStreet}', \`phone\`='{$encPhone}' WHERE \`id\`={$id};\n";
}
echo "\n-- order_address_snapshots (street, phone) — clé actuelle\n";
foreach ($seedSnapshots as $id => [$encStreet, $encPhone]) {
    echo "-- id={$id}: UPDATE \`order_address_snapshots\` SET \`street\`='{$encStreet}', \`phone\`='{$encPhone}' WHERE \`id\`={$id};\n";
}

echo "\n✅ DB mise à jour. Copiez les valeurs ENC: ci-dessus dans seed_mock_data.sql.\n";
