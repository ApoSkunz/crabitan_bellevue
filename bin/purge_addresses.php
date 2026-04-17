<?php

/**
 * Script RGPD — purge chronique des adresses orphelines.
 *
 * Supprime définitivement les adresses marquées preserved_for_order = 1
 * dont toutes les commandes associées sont en statut terminal (delivered / cancelled).
 *
 * À planifier via cron (ex: tous les jours à 03h00) :
 *   0 3 * * * php /var/www/crabitan_bellevue/bin/purge_addresses.php >> /var/log/crabitan/purge.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_addresses');
exit($runner->run(function (callable $log, bool $dryRun): void {
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

    if ($dryRun) {
        $log('[DRY-RUN] Would delete ' . count($orphaned) . ' adresse(s) — aucune écriture BDD');
        return;
    }

    $purged = $model->purgeOrphaned();
    $log("{$purged} adresse(s) supprimée(s)");
}));
