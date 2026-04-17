<?php

/**
 * Script de purge des abonnements newsletter — conformité RGPD Art. 5-1-e (minimisation).
 *
 * Purge 2 catégories :
 *   1. Abonnements non confirmés dont le token est expiré (double opt-in abandonné).
 *   2. Abonnements confirmés datant de plus de 13 mois (rétention maximale RGPD).
 *
 * À planifier via cron (ex: le 1er de chaque mois à 03h00) :
 *   0 3 1 * * php /var/www/crabitan_bellevue/bin/cron/purge_newsletter_subscriptions.php >> /var/log/crabitan/purge.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_newsletter_subscriptions');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $model = new \Model\NewsletterSubscriptionModel();

    if ($dryRun) {
        $log('[DRY-RUN] Would purge expired pending subscriptions — aucune écriture BDD');
        $log('[DRY-RUN] Would purge subscriptions older than 13 months — aucune écriture BDD');
        return;
    }

    $pending = $model->purgeExpiredPending();
    $log("{$pending} abonnement(s) non confirmé(s) expiré(s) supprimé(s)");

    $old = $model->purgeOlderThan13Months();
    $log("{$old} abonnement(s) confirmé(s) de plus de 13 mois supprimé(s) (rétention RGPD)");
}));
