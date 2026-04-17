<?php

/**
 * Script de purge du tracking newsletter — suppression des données d'ouverture et de clic
 * antérieures à 13 mois (conformité RGPD Art. 5-1-e, intérêt légitime).
 *
 * À planifier via cron (ex: le 1er de chaque mois à 04h00) :
 *   0 4 1 * * php /var/www/crabitan_bellevue/bin/cron/purge_newsletter_tracking.php >> /var/log/crabitan/purge.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_newsletter_tracking');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $model = new \Model\NewsletterTrackingModel();

    if ($dryRun) {
        $log('[DRY-RUN] Would purge newsletter tracking older than 13 months — aucune écriture BDD');
        return;
    }

    $deleted = $model->purgeOlderThan13Months();
    $log("{$deleted} enregistrement(s) de tracking purgé(s)");
}));
