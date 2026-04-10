<?php

/**
 * Purge des sessions révoquées ou expirées (table `connections`).
 * Cron : tous les jours à 03h00
 *   0 3 * * * php /var/www/crabitan_bellevue/bin/purge_connections.php
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_connections');
exit($runner->run(function (callable $log): void {
    $model  = new \Model\ConnectionModel();
    $purged = $model->purgeExpired();
    $log("{$purged} session(s) purgée(s)");
}));
