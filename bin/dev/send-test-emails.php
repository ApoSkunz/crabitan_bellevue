<?php

/**
 * Script dev — envoie les 3 nouveaux emails de test vers une adresse cible.
 * Usage : php bin/dev/send-test-emails.php [email]
 *
 * Emails envoyés :
 *   1. Newsletter J+7
 *   2. Newsletter J+30
 *   3. Post-achat J+21 (avec CTA notation + CTA avis)
 */

declare(strict_types=1);

require_once __DIR__ . '/../cron/cron-bootstrap.php';

$to   = $argv[1] ?? 'alexandresolane.web@gmail.com';
$lang = $argv[2] ?? 'fr';

$mailer = new \Service\MailService();

echo "=== Envoi emails de test → {$to} (lang={$lang}) ===\n\n";

// 1. Newsletter J+7
echo "[1/3] Newsletter J+7... ";
try {
    $mailer->sendNewsletterWelcomeJ7($to, 'Alexandre', $lang);
    echo "OK\n";
} catch (\Throwable $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}

// 2. Newsletter J+30
echo "[2/3] Newsletter J+30... ";
try {
    $fakeUnsubToken = bin2hex(random_bytes(16));
    $mailer->sendNewsletterWelcomeJ30($to, 'Alexandre', $lang, $fakeUnsubToken);
    echo "OK\n";
} catch (\Throwable $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}

// 3. Post-achat J+21 (avec CTA notation + CTA avis)
// Images : base URL prod pour que Gmail puisse les charger
// Lien avis : http://crabitan.local (token réel account_id=3, order_id=11)
// customer_reviews pour account_id=3 supprimé → soumission possible
echo "[3/3] Post-achat J+21 (notation + avis)... ";
try {
    // Images réelles depuis la BDD locale (Wine_*.png)
    // APP_URL override temporaire pour que les src d'image pointent vers la prod
    $_ENV['APP_URL'] = 'https://www.crabitanbellevue.fr';

    $items = [
        [
            'wine_id'    => 1,
            'label_name' => 'Sainte-Croix-du-Mont',
            'vintage'    => 2010,
            'wine_color' => 'sweet',
            'format'     => 'bottle',
            'qty'        => 6,
            'price'      => 10.00,
            'subtotal'   => 60.00,
            'image_path' => 'Wine_Sainte_Croix_du_Mont_2010_680fd90de3238c4e.png',
        ],
        [
            'wine_id'    => 6,
            'label_name' => 'Côtes de Bordeaux Rouge',
            'vintage'    => 2014,
            'wine_color' => 'red',
            'format'     => 'bottle',
            'qty'        => 3,
            'price'      => 7.00,
            'subtotal'   => 21.00,
            'image_path' => 'Wine_Cotes_de_Bordeaux_Rouge_2014_67f324684dcc21db.png',
        ],
    ];

    // Lien avis pointe vers local pour que tu puisses cliquer et soumettre
    // Le token order_rating_tokens (account_id=3, order_id=11) expire 2026-07-20
    $mailer->sendPostPurchase($to, 'Sophie', 3, 11, $items, $lang);

    // Restaurer APP_URL local
    $_ENV['APP_URL'] = 'http://crabitan.local';
    echo "OK\n";

    // Token réel pour soumettre un vrai avis en local
    $pdo = \Core\Database::getInstance();
    $row = $pdo->fetchOne(
        "SELECT token FROM order_rating_tokens WHERE account_id = 3 AND order_id = 11"
    );
    if ($row) {
        echo "\n>>> LIEN AVIS LOCAL (cliquer depuis XAMPP actif) :\n";
        echo "    http://crabitan.local/fr/mon-avis?token=" . $row['token'] . "\n\n";
        echo "    (images dans l'email chargées depuis la prod)\n";
    }
} catch (\Throwable $e) {
    $_ENV['APP_URL'] = 'http://crabitan.local';
    echo "ERR: " . $e->getMessage() . "\n";
}

echo "\nTerminé.\n";
