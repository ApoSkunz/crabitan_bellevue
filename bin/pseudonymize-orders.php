<?php

/**
 * Pseudonymisation des commandes > 10 ans (Art. L123-22 Code de commerce + RGPD Art. 5).
 * Cron : tous les jours à 02h00
 *   0 2 * * * php /var/www/crabitan_bellevue/bin/pseudonymize-orders.php
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('pseudonymize_orders');
exit($runner->run(function (callable $log): void {
    $model = new \Model\OrderModel();
    $count = $model->pseudonymizeExpired();
    $log("{$count} snapshot(s) pseudonymisé(s)");
}));
