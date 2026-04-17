<?php

/**
 * Cron de pseudonymisation des commandes de plus de 10 ans.
 *
 * Respecte l'Art. L123-22 Code de commerce (archivage 10 ans) et le RGPD Art. 5
 * (minimisation des données personnelles au-delà de la durée de conservation).
 *
 * À planifier via cron (ex: tous les jours à 02h00) :
 *   0 2 * * * php /var/www/crabitan_bellevue/bin/cron/pseudonymize-orders.php >> /var/log/crabitan/pseudonymize.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('pseudonymize_orders');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $model = new \Model\OrderModel();

    if ($dryRun) {
        $log('[DRY-RUN] Would pseudonymize expired order snapshots — aucune écriture BDD');
        return;
    }

    $count = $model->pseudonymizeExpired();
    $log("{$count} snapshot(s) pseudonymisé(s)");
}));
