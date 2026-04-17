<?php

/**
 * Cron — Email de notation post-achat J+21.
 *
 * Envoie un email invitant le client à noter sa commande 3 semaines après
 * la livraison confirmée (fenêtre 504h–528h). Un seul envoi par commande.
 *
 * À planifier via cron (ex: tous les jours à 10h) :
 *   0 10 * * * php /var/www/crabitan_bellevue/bin/cron/send-post-purchase.php >> /var/log/crabitan/cron.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('send-post-purchase');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $orderModel   = new \Model\OrderModel();
    $accountModel = new \Model\AccountModel();
    $mailer       = new \Service\MailService();

    $orders = $orderModel->getDeliveredForRatingEmail();
    $log('Commandes éligibles notation : ' . count($orders));

    foreach ($orders as $order) {
        $account = $accountModel->findById((int) $order['user_id']);
        if (!$account) {
            $log('[SKIP] user_id=' . $order['user_id']);
            continue;
        }

        if ($dryRun) {
            $log('[DRY-RUN] Would send post-purchase to ' . $account['email'] . ' commande #' . $order['id']);
            continue;
        }

        // items depuis order['content'] (JSON)
        $items = json_decode((string) $order['content'], true) ?? [];
        $lang  = in_array($account['lang'] ?? '', ['fr','en'], true) ? $account['lang'] : 'fr';

        try {
            $mailer->sendPostPurchase(
                $account['email'],
                $account['firstname'] ?? 'Client',
                (int) $order['user_id'],
                (int) $order['id'],
                $items,
                $lang
            );
            $orderModel->markRatingEmailSent((int) $order['id']);
            $log('[OK] ' . $account['email'] . ' commande #' . $order['id']);
        } catch (\Throwable $e) {
            $log('[ERR] commande #' . $order['id'] . ' : ' . $e->getMessage());
        }
    }
}));
