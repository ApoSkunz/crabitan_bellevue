<?php

/**
 * Rapport hebdomadaire stock vins — email au propriétaire.
 *
 * Envoie un tableau récapitulatif de tous les vins du catalogue :
 * millésime · nom · label (AOC/IGP…) · cuvée spéciale · prix TTC · stock.
 *
 * Aucun email si le catalogue est vide.
 *
 * À planifier via cron (ex : tous les lundis à 07h30) :
 *   30 7 * * 1 php /var/www/crabitan_bellevue/bin/cron/send-wine-stock-report.php >> /var/log/crabitan/wine-stock-report.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('send-wine-stock-report');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $model  = new \Model\WineModel();
    $mailer = new \Service\MailService();

    $wines = $model->getForStockReport();
    $count = count($wines);

    if ($count === 0) {
        $log('Catalogue vide — aucun email envoyé');
        return;
    }

    if ($dryRun) {
        $log("[DRY-RUN] Would send stock report ({$count} vin(s)) au propriétaire — aucun email envoyé");
        return;
    }

    $mailer->sendWineStockReport($wines);
    $log("[OK] Rapport stock ({$count} vin(s)) envoyé au propriétaire");
}));
