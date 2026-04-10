<?php

/**
 * Purge du tracking newsletter — données d'ouverture/clic > 13 mois (RGPD Art. 5-1-e).
 * Cron : 1er de chaque mois à 04h00
 *   0 4 1 * * php /var/www/crabitan_bellevue/bin/purge_newsletter_tracking.php
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_newsletter_tracking');
exit($runner->run(function (callable $log): void {
    $model   = new \Model\NewsletterTrackingModel();
    $deleted = $model->purgeOlderThan13Months();
    $log("{$deleted} enregistrement(s) de tracking purgé(s)");
}));
