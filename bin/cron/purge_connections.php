<?php

/**
 * Script de purge des sessions — suppression des connexions révoquées ou expirées.
 *
 * Supprime les lignes de la table `connections` dont le statut est 'revoked'
 * ou dont la date d'expiration est dépassée — évite la croissance infinie de la table.
 *
 * À planifier via cron (ex: tous les jours à 03h00) :
 *   0 3 * * * php /var/www/crabitan_bellevue/bin/cron/purge_connections.php >> /var/log/crabitan/purge.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_connections');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $model = new \Model\ConnectionModel();

    if ($dryRun) {
        $log('[DRY-RUN] Would purge expired/revoked connections — aucune écriture BDD');
        return;
    }

    $purged = $model->purgeExpired();
    $log("{$purged} session(s) purgée(s)");
}));
