<?php

/**
 * Seed de développement — comptes fictifs pour tests locaux.
 *
 * Usage : php database/seed_users_dev.php
 *
 * Comptes créés :
 *   client.verifie@dev.local  / Dev123456789!  (particulier, vérifié)
 *   client.nouveau@dev.local  / Dev123456789!  (particulier, non vérifié)
 *   societe@dev.local         / Dev123456789!  (société, vérifié)
 *   admin@dev.local           / Dev123456789!  (admin, vérifié)
 *   superadmin@dev.local      / Dev123456789!  (super_admin, vérifié)
 *
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

$users = [
    [
        'email'               => 'client.verifie@dev.local',
        'password'            => $password,
        'lang'                => 'fr',
        'role'                => 'customer',
        'account_type'        => 'individual',
        'email_verified_at'   => $now,
        'firstname'           => 'Sophie',
        'lastname'            => 'Durand',
        'civility'            => 'F',
    ],
    [
        'email'               => 'client.nouveau@dev.local',
        'password'            => $password,
        'lang'                => 'fr',
        'role'                => 'customer',
        'account_type'        => 'individual',
        'email_verified_at'   => null,
        'firstname'           => 'Marc',
        'lastname'            => 'Lefebvre',
        'civility'            => 'M',
    ],
    [
        'email'               => 'societe@dev.local',
        'password'            => $password,
        'lang'                => 'fr',
        'role'                => 'customer',
        'account_type'        => 'company',
        'email_verified_at'   => $now,
        'company_name'        => 'Les Caves du Sud SARL',
        'siret'               => '12345678900012',
    ],
    [
        'email'               => 'admin@dev.local',
        'password'            => $password,
        'lang'                => 'fr',
        'role'                => 'admin',
        'account_type'        => 'individual',
        'email_verified_at'   => $now,
        'firstname'           => 'Admin',
        'lastname'            => 'Test',
        'civility'            => 'M',
    ],
    [
        'email'               => 'superadmin@dev.local',
        'password'            => $password,
        'lang'                => 'fr',
        'role'                => 'super_admin',
        'account_type'        => 'individual',
        'email_verified_at'   => $now,
        'firstname'           => 'Super',
        'lastname'            => 'Admin',
        'civility'            => 'M',
    ],
];

foreach ($users as $user) {
    // Vérifie que l'email n'existe pas déjà
    $check = $pdo->prepare("SELECT id FROM accounts WHERE email = ?");
    $check->execute([$user['email']]);
    if ($check->fetchColumn()) {
        echo "  SKIP  {$user['email']} (déjà existant)\n";
        continue;
    }

    $pdo->beginTransaction();
    try {
        // Compte principal
        $stmt = $pdo->prepare(
            "INSERT INTO accounts (email, password, lang, role, account_type, email_verified_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            $user['email'],
            $user['password'],
            $user['lang'],
            $user['role'],
            $user['account_type'],
            $user['email_verified_at'],
        ]);
        $accountId = (int) $pdo->lastInsertId();

        // Profil particulier ou société
        if ($user['account_type'] === 'individual') {
            $pdo->prepare(
                "INSERT INTO account_individuals (account_id, firstname, lastname, civility)
                 VALUES (?, ?, ?, ?)"
            )->execute([
                $accountId,
                $user['firstname'],
                $user['lastname'],
                $user['civility'],
            ]);
        } else {
            $pdo->prepare(
                "INSERT INTO account_companies (account_id, company_name, siret)
                 VALUES (?, ?, ?)"
            )->execute([
                $accountId,
                $user['company_name'],
                $user['siret'] ?? null,
            ]);
        }

        $pdo->commit();
        echo "  OK    {$user['email']} (#{$accountId})\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo "  ERR   {$user['email']} — {$e->getMessage()}\n";
    }
}

echo "\nSeed terminé. Mot de passe : Dev123456789!\n";
