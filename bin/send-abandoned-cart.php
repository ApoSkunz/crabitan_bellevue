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
    $cartModel = new \Model\CartModel();
    $mailer    = new \Service\MailService();
    $appUrl    = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');

    $carts = $cartModel->getAbandonedForEmail();
    $log('Paniers éligibles : ' . count($carts));

    foreach ($carts as $data) {
        $cartUrl = ($data['lang'] === 'fr') ? $appUrl . '/fr/panier' : $appUrl . '/en/cart';
        try {
            $mailer->sendAbandonedCart($data['email'], $data['firstname'], $data['items'], $data['lang'], $cartUrl);
            $cartModel->markReminderSent($data['cart_id']);
            $log('[OK] ' . $data['email']);
        } catch (\Throwable $e) {
            $log('[ERR] ' . $data['email'] . ' : ' . $e->getMessage());
        }
    }
}));
