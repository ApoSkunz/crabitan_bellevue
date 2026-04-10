<?php

/**
 * Purge RGPD — suppression des adresses orphelines.
 * Supprime les adresses preserved_for_order dont toutes les commandes sont terminées.
 * Cron : tous les jours à 03h00
 *   0 3 * * * php /var/www/crabitan_bellevue/bin/purge_addresses.php
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_addresses');
exit($runner->run(function (callable $log): void {
    $model    = new \Model\AddressModel();
    $orphaned = $model->getOrphanedAddresses();
    $log('Adresses orphelines éligibles : ' . count($orphaned));

    if ($orphaned === []) {
        $log('0 adresse purgée');
        return;
    }

    foreach ($orphaned as $addr) {
        $log("Adresse id={$addr['id']} (user_id={$addr['user_id']}) éligible");
    }

    $purged = $model->purgeOrphaned();
    $log("{$purged} adresse(s) supprimée(s)");
}));
