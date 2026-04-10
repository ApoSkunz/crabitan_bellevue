<?php

/**
 * Script RGPD — purge des comptes inactifs (Art. 5 §1e — minimisation des données).
 *
 * Deux phases :
 *   Phase 1 — Avertissement : envoie un email aux comptes inactifs depuis RGPD_INACTIVITY_MONTHS
 *             (défaut 36 mois) et non encore avertis.
 *   Phase 2 — Soft-delete  : soft-delete les comptes avertis il y a plus de 30 jours
 *             et n'ayant pas reconnecté.
 *
 * À planifier via cron (ex: tous les jours à 02h00) :
 *   0 2 * * * php /var/www/crabitan_bellevue/bin/purge_inactive_accounts.php >> /var/log/crabitan/purge_inactive.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_inactive_accounts');
exit($runner->run(function (callable $log): void {
    $model          = new \Model\AccountModel();
    $mailer         = new \Service\MailService();
    $inactiveMonths = (int) ($_ENV['RGPD_INACTIVITY_MONTHS'] ?? 36);

    $log("Seuil d'inactivité : {$inactiveMonths} mois");

    // Phase 1 — Avertissement
    $inactive = $model->getInactiveAccounts($inactiveMonths);
    $log('Phase 1 — comptes à avertir : ' . count($inactive));

    $warned = 0;
    foreach ($inactive as $account) {
        $id           = (int) $account['id'];
        $deletionDate = date('d/m/Y', strtotime('+30 days'));
        try {
            $mailer->sendInactivityWarning(
                (string) $account['email'],
                (string) $account['name'],
                (string) ($account['lang'] ?? 'fr'),
                $deletionDate
            );
            $model->markInactivityWarningSent($id);
            $warned++;
            $log("[WARN] Email envoyé à {$account['email']} (id={$id}), suppression prévue {$deletionDate}");
        } catch (\Throwable $e) {
            $log("[WARN-ERR] id={$id} : " . $e->getMessage());
        }
    }

    // Phase 2 — Soft-delete
    $toDelete    = $model->getAccountsToSoftDelete();
    $softDeleted = 0;
    $log('Phase 2 — comptes à soft-delete : ' . count($toDelete));

    foreach ($toDelete as $account) {
        $id = (int) $account['id'];
        try {
            $token        = $model->softDeleteForInactivity($id);
            $deletionDate = date('d/m/Y', strtotime('+30 days'));
            $name         = trim((string) ($account['name'] ?? '')) ?: $account['email'];
            $mailer->sendInactivityDeletion(
                $account['email'],
                $name,
                $account['lang'] ?? 'fr',
                $deletionDate,
                $token
            );
            $softDeleted++;
            $log("[DELETE] Compte id={$id} ({$account['email']}) soft-deleted");
        } catch (\Throwable $e) {
            $log("[DELETE-ERR] id={$id} : " . $e->getMessage());
        }
    }

    $log("Terminé : {$warned} avertissement(s), {$softDeleted} soft-delete(s)");
}));
