<?php

/**
 * Rotation de clé AES-256-GCM pour toutes les colonnes chiffrées :
 *   - accounts.email_change_new_email
 *   - accounts.majority_declared_ip
 *   - addresses.street + addresses.phone
 *   - order_address_snapshots.street + order_address_snapshots.phone
 *
 * Prérequis .env :
 *   DB_ENCRYPT_KEY     = <clé v1 actuelle, 32 octets hex>
 *   ENCRYPTION_KEY_V2  = <nouvelle clé v2, 32 octets hex>
 *
 * Exécution (fenêtre de maintenance recommandée) :
 *   php bin/secu/rotate_encryption_keys.php --host <host> --user <user> --db <db>
 *
 * ⚠️  NE PAS planifier via cron — exécution manuelle unique par rotation.
 * ⚠️  Snapshot BDD obligatoire avant exécution.
 * ⚠️  Tester impérativement en staging avant production.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$env = is_file(ROOT_PATH . '/.env') ? parse_ini_file(ROOT_PATH . '/.env') : [];
foreach ($env ?: [] as $k => $v) {
    $_ENV[$k] = $v;
}

// ── Args ──────────────────────────────────────────────────────────────────────
$opts  = getopt('', ['host:', 'user:', 'db:', 'pass:', 'batch:']);
$host  = $opts['host']  ?? throw new \InvalidArgumentException('--host requis');
$user  = $opts['user']  ?? throw new \InvalidArgumentException('--user requis');
$db    = $opts['db']    ?? throw new \InvalidArgumentException('--db requis');
$pass  = $opts['pass']  ?? (getenv('MYSQL_PWD') ?: '');
$batch = (int) ($opts['batch'] ?? 100);

// ── Clés ──────────────────────────────────────────────────────────────────────
$keyV1 = $_ENV['DB_ENCRYPT_KEY']    ?? '';
$keyV2 = $_ENV['ENCRYPTION_KEY_V2'] ?? '';

if ($keyV1 === '') {
    fwrite(STDERR, "[ERROR] DB_ENCRYPT_KEY manquante dans .env\n");
    exit(1);
}
if ($keyV2 === '') {
    fwrite(STDERR, "[ERROR] ENCRYPTION_KEY_V2 manquante dans .env\n");
    exit(1);
}
if ($keyV1 === $keyV2) {
    fwrite(STDERR, "[ERROR] DB_ENCRYPT_KEY et ENCRYPTION_KEY_V2 sont identiques — rien à faire\n");
    exit(1);
}

$encV1 = new \Service\EncryptionService($keyV1);
$encV2 = new \Service\EncryptionService($keyV2);

// ── Connexion PDO ──────────────────────────────────────────────────────────────
$pdo = new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]
);

$log = static function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

/**
 * Recodage d'une valeur : déchiffre avec v1, rechiffre avec v2.
 * Retourne null si la valeur est vide ou non chiffrée (pas de préfixe ENC:).
 *
 * @param string $value  Valeur chiffrée (ENC:...) ou vide
 * @return string|null   Nouvelle valeur chiffrée v2, ou null si skip
 */
$recode = static function (string $value) use ($encV1, $encV2): ?string {
    if ($value === '' || !$encV1->isEncrypted($value)) {
        return null;
    }
    $plain = $encV1->decrypt($value);
    if ($plain === null) {
        return null;
    }
    return $encV2->encrypt($plain);
};

$log('START — rotation de clé AES-256-GCM');
$totalAccounts = 0;
$totalAddr     = 0;
$totalOas      = 0;
$skipped       = 0;

// ── Table accounts ─────────────────────────────────────────────────────────────
$log('=== accounts ===');
$offset = 0;
do {
    $selAccounts = $pdo->prepare(
        "SELECT id, email_change_new_email, majority_declared_ip
         FROM accounts
         WHERE encryption_version = 1
         LIMIT :limit OFFSET :offset"
    );
    $selAccounts->bindValue(':limit', $batch, PDO::PARAM_INT);
    $selAccounts->bindValue(':offset', $offset, PDO::PARAM_INT);
    $selAccounts->execute();
    $rows = $selAccounts->fetchAll();
    if ($rows === []) {
        break;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE accounts
             SET email_change_new_email = ?, majority_declared_ip = ?, encryption_version = 2
             WHERE id = ?"
        );
        foreach ($rows as $row) {
            $newEmail = $row['email_change_new_email'] !== null
                ? ($recode((string) $row['email_change_new_email']) ?? $row['email_change_new_email'])
                : null;
            $newIp    = $row['majority_declared_ip'] !== null
                ? ($recode((string) $row['majority_declared_ip']) ?? $row['majority_declared_ip'])
                : null;

            $stmt->execute([$newEmail, $newIp, $row['id']]);
            $totalAccounts++;
            $log("  accounts.id={$row['id']} → recodé v2");
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "[ERROR] accounts batch offset={$offset} : " . $e->getMessage() . "\n");
        exit(1);
    }
    $offset += $batch;
} while (true);

// ── Table addresses ────────────────────────────────────────────────────────────
$log('=== addresses ===');
$offset = 0;
do {
    $selAddr = $pdo->prepare("SELECT id, street, phone FROM addresses LIMIT :limit OFFSET :offset");
    $selAddr->bindValue(':limit', $batch, PDO::PARAM_INT);
    $selAddr->bindValue(':offset', $offset, PDO::PARAM_INT);
    $selAddr->execute();
    $rows = $selAddr->fetchAll();
    if ($rows === []) {
        break;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE addresses SET street = ?, phone = ? WHERE id = ?");
        foreach ($rows as $row) {
            $newStreet = $recode($row['street']);
            $newPhone  = $recode($row['phone']);

            if ($newStreet === null && $newPhone === null) {
                $skipped++;
                continue;
            }

            $stmt->execute([
                $newStreet ?? $row['street'],
                $newPhone  ?? $row['phone'],
                $row['id'],
            ]);
            $totalAddr++;
            $log("  addresses.id={$row['id']} → recodé v2");
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "[ERROR] addresses batch offset={$offset} : " . $e->getMessage() . "\n");
        exit(1);
    }
    $offset += $batch;
} while (true);

// ── Table order_address_snapshots ──────────────────────────────────────────────
$log('=== order_address_snapshots ===');
$offset = 0;
do {
    $selOas = $pdo->prepare(
        "SELECT id, street, phone FROM order_address_snapshots
         WHERE pseudonymized_at IS NULL
         LIMIT :limit OFFSET :offset"
    );
    $selOas->bindValue(':limit', $batch, PDO::PARAM_INT);
    $selOas->bindValue(':offset', $offset, PDO::PARAM_INT);
    $selOas->execute();
    $rows = $selOas->fetchAll();
    if ($rows === []) {
        break;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE order_address_snapshots SET street = ?, phone = ? WHERE id = ?");
        foreach ($rows as $row) {
            $newStreet = $recode($row['street']);
            $newPhone  = $recode($row['phone']);

            if ($newStreet === null && $newPhone === null) {
                $skipped++;
                continue;
            }

            $stmt->execute([
                $newStreet ?? $row['street'],
                $newPhone  ?? $row['phone'],
                $row['id'],
            ]);
            $totalOas++;
            $log("  order_address_snapshots.id={$row['id']} → recodé v2");
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "[ERROR] order_address_snapshots batch offset={$offset} : " . $e->getMessage() . "\n");
        exit(1);
    }
    $offset += $batch;
} while (true);

$log('END');
$log("  accounts recodés           : {$totalAccounts}");
$log("  addresses recodées         : {$totalAddr}");
$log("  order_address_snapshots    : {$totalOas}");
$log("  skippés (vide/non chiffré) : {$skipped}");
