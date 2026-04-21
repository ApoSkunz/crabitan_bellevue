<?php

/**
 * Cron — Email de demande d'avis global J+28.
 *
 * Envoie un email avec un unique CTA vers /mon-avis?token= à J+28 après
 * la livraison confirmée (fenêtre 672h–696h). Un seul envoi par commande.
 * Distinct de send-post-purchase.php (notation étoiles par vin, J+21).
 *
 * À planifier via cron (ex: tous les jours à 11h) :
 *   0 11 * * * php /var/www/crabitan_bellevue/bin/cron/send-review-request.php >> /var/log/crabitan/cron.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → HTTP GET → /cron/send-review-request?token=…
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('send-review-request');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $orderModel   = new \Model\OrderModel();
    $accountModel = new \Model\AccountModel();
    $mailer       = new \Service\MailService();

    $orders = $orderModel->getDeliveredForReviewEmail();
    $log('Commandes éligibles demande avis : ' . count($orders));

    foreach ($orders as $order) {
        $account = $accountModel->findById((int) $order['user_id']);
        if (!$account) {
            $log('[SKIP] user_id=' . $order['user_id']);
            continue;
        }

        if ($dryRun) {
            $log('[DRY-RUN] Would send review-request to ' . $account['email'] . ' commande #' . $order['id']);
            continue;
        }

        $lang = in_array($account['lang'] ?? '', ['fr', 'en'], true) ? $account['lang'] : 'fr';
        $name = trim((string) ($account['firstname'] ?? '')) ?: 'Client';

        try {
            $mailer->sendReviewRequest(
                $account['email'],
                $name,
                (int) $order['user_id'],
                (int) $order['id'],
                $lang
            );
            $orderModel->markReviewEmailSent((int) $order['id']);
            $log('[OK] ' . $account['email'] . ' commande #' . $order['id']);
        } catch (\Throwable $e) {
            $log('[ERR] commande #' . $order['id'] . ' : ' . $e->getMessage());
        }
    }
}));
