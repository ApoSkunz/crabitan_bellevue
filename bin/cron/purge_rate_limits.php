<?php

/**
 * Script cron — purge des entrées expirées de la table rate_limits.
 *
 * Supprime toutes les lignes dont expires_at < UNIX_TIMESTAMP(),
 * libérant l'espace occupé par les compteurs et lockouts périmés.
 *
 * À planifier via cron (ex: toutes les heures) :
 *   0 * * * * php /var/www/crabitan_bellevue/bin/cron/purge_rate_limits.php >> /var/log/crabitan/rate_limits.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_rate_limits');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $db = \Core\Database::getInstance();

    if ($dryRun) {
        $count = $db->fetchOne(
            'SELECT COUNT(*) AS n FROM rate_limits WHERE expires_at < UNIX_TIMESTAMP()'
        )['n'] ?? 0;
        $log("[DRY-RUN] Would delete {$count} entrée(s) expirée(s) — aucune écriture BDD");
        return;
    }

    $deleted = $db->execute(
        'DELETE FROM rate_limits WHERE expires_at < UNIX_TIMESTAMP()'
    );

    $log("rate_limits : {$deleted} entrée(s) expirée(s) supprimée(s)");
}));
