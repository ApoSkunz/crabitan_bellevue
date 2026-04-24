<?php

/**
 * Script cron — purge des fichiers de log de plus de 90 jours.
 *
 * Supprime tous les fichiers *.log dans storage/logs/ dont la date
 * de modification dépasse 90 jours — couvre logs applicatifs (app-*.log)
 * et logs cron ({job}-*.log).
 *
 * À planifier via cron (ex: quotidiennement à 3h) :
 *   0 3 * * * php /var/www/crabitan_bellevue/bin/cron/purge_logs.php >> /var/log/crabitan/purge_logs.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_logs');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $logDir = defined('ROOT_PATH') ? ROOT_PATH . '/storage/logs' : '';
    if ($logDir === '' || !is_dir($logDir)) {
        $log('storage/logs introuvable — rien à purger');
        return;
    }

    $cutoff  = time() - 30 * 86400;
    $files   = glob($logDir . '/*.log') ?: [];
    $deleted = 0;

    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            if ($dryRun) {
                $log('[DRY-RUN] Would delete ' . basename($file));
            } else {
                unlink($file);
                $deleted++;
            }
        }
    }

    if ($dryRun) {
        $count = count(array_filter($files, fn($f) => filemtime($f) < $cutoff));
        $log("[DRY-RUN] {$count} fichier(s) à purger (> 30 jours) — aucune écriture");
    } else {
        $log("{$deleted} fichier(s) de log purgé(s) (> 30 jours)");
    }
}));
