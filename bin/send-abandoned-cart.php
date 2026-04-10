<?php

/**
 * Cron — Relance panier abandonné J+3.
 *
 * Envoie un email de relance aux utilisateurs dont le panier n'a pas été
 * commandé depuis 72 à 96 heures (fenêtre J+3). Une seule relance par panier.
 *
 * À planifier via cron (ex: tous les jours à 9h) :
 *   0 9 * * * php /var/www/crabitan_bellevue/bin/send-abandoned-cart.php >> /var/log/crabitan/cron.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('send-abandoned-cart');
exit($runner->run(function (callable $log): void {
    $cartModel    = new \Model\CartModel();
    $accountModel = new \Model\AccountModel();
    $wineModel    = new \Model\WineModel();
    $mailer       = new \Service\MailService();
    $appUrl       = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');

    $abandoned = $cartModel->getAbandoned(72, 96);
    $log('Paniers abandonnés éligibles : ' . count($abandoned));

    foreach ($abandoned as $cart) {
        $userId  = (int) ($cart['user_id'] ?? 0);
        $account = $accountModel->findById($userId);
        if (!$account) {
            $log("[SKIP] user_id={$userId} introuvable");
            continue;
        }

        $email    = (string) ($account['email'] ?? '');
        $firstname = (string) ($account['firstname'] ?? $account['company_name'] ?? 'Client');
        $lang     = in_array((string) ($account['lang'] ?? ''), ['fr', 'en'], true)
            ? (string) $account['lang'] : 'fr';

        $rawItems      = $cartModel->getContent($cart);
        $enrichedItems = [];
        foreach ($rawItems as $item) {
            $wineId = (int) ($item['wine_id'] ?? 0);
            $wine   = $wineId > 0 ? $wineModel->findById($wineId) : false;
            if (!$wine) {
                continue;
            }
            $enrichedItems[] = [
                'label_name' => (string) ($wine['label_name'] ?? ''),
                'vintage'    => (int) ($wine['vintage'] ?? 0),
                'image_path' => (string) ($wine['image_path'] ?? ''),
                'price'      => (float) ($wine['price'] ?? 0.0),
                'qty'        => (int) ($item['qty'] ?? 0),
            ];
        }

        if (empty($enrichedItems)) {
            $log("[SKIP] user_id={$userId} — panier vide après enrichissement");
            continue;
        }

        $cartUrl = $lang === 'fr' ? $appUrl . '/fr/panier' : $appUrl . '/en/cart';

        try {
            $mailer->sendAbandonedCart($email, $firstname, $enrichedItems, $lang, $cartUrl);
            $cartModel->markReminderSent((int) $cart['id']);
            $log("[OK] Relance envoyée à {$email} (user_id={$userId})");
        } catch (\Throwable $e) {
            $log("[ERR] {$email} : " . $e->getMessage());
        }
    }
}));
