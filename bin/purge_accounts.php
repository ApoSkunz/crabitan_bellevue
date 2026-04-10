<?php

/**
 * Script de purge RGPD — anonymisation des comptes supprimés après 30 jours.
 *
 * Les données de commandes sont conservées (obligations légales comptables — 10 ans).
 * Seules les données personnelles identifiantes sont anonymisées.
 *
 * À planifier via cron (ex: tous les jours à 02h00) :
 *   0 2 * * * php /var/www/crabitan_bellevue/bin/purge_accounts.php >> /var/log/crabitan/purge.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_accounts');
exit($runner->run(function (callable $log): void {
    $model  = new \Model\AccountModel();
    $mailer = new \Service\MailService();

    $due = $model->getAccountsDueForDeletion();
    $log('Comptes éligibles à l\'anonymisation : ' . count($due));

    foreach ($due as $account) {
        $name = trim((string) ($account['name'] ?? '')) ?: $account['email'];
        try {
            $mailer->sendFinalDeletion($account['email'], $name, $account['lang'] ?? 'fr');
            $log("[EMAIL] Notification envoyée à {$account['email']} (id={$account['id']})");
        } catch (\Throwable $e) {
            $log("[EMAIL-ERR] id={$account['id']} : " . $e->getMessage());
        }
    }

    $purged = $model->purgeScheduledDeletions();
    $log("{$purged} compte(s) anonymisé(s)");
}));
