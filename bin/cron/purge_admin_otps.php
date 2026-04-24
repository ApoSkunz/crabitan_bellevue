<?php

/**
 * Script de purge des OTP admin expirés.
 *
 * Supprime les lignes de la table `admin_otps` dont la date d'expiration
 * est dépassée — évite la croissance infinie de la table.
 *
 * À planifier via cron (ex: toutes les heures) :
 *   0 * * * * php /var/www/crabitan_bellevue/bin/cron/purge_admin_otps.php >> /var/log/crabitan/purge.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_admin_otps');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $model = new \Model\AdminOtpModel();

    if ($dryRun) {
        $log('[DRY-RUN] Would purge expired admin OTPs — aucune écriture BDD');
        return;
    }

    $purged = $model->purgeExpired();
    $log("{$purged} OTP(s) admin expiré(s) purgé(s)");
}));
