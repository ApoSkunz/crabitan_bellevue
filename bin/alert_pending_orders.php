<?php

/**
 * Alerte hebdomadaire — commandes sans mouvement depuis plus de 7 jours.
 *
 * Envoie un email récapitulatif au propriétaire listant toutes les commandes
 * dont le statut n'est pas final (delivered/cancelled/refunded) et qui n'ont
 * pas bougé depuis plus de 7 jours. Aucun email si aucune commande concernée.
 *
 * À planifier via cron (ex: tous les lundis à 07h00) :
 *   0 7 * * 1 php /var/www/crabitan_bellevue/bin/alert_pending_orders.php >> /var/log/crabitan/pending_orders.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('alert_pending_orders');
exit($runner->run(function (callable $log): void {
    $model  = new \Model\OrderModel();
    $mailer = new \Service\MailService();

    $staleOrders = $model->getStaleOrders(7);

    if (empty($staleOrders)) {
        $log('Aucune commande bloquée — aucun email envoyé');
        return;
    }

    $count = count($staleOrders);
    $mailer->sendPendingOrdersAlert($staleOrders, 7);
    $log("{$count} commande(s) bloquée(s) — alerte envoyée au propriétaire");
}));
