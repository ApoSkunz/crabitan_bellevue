<?php

/**
 * Script de rappels avant suppression définitive du compte.
 *
 * Envoie un email J-7 puis J-1 aux clients dont le compte est en attente
 * d'anonymisation RGPD. Prévient les doublons via les colonnes
 * deletion_reminder_7d_sent_at / deletion_reminder_1d_sent_at.
 *
 * À planifier via cron (ex: tous les jours à 09h00) :
 *   0 9 * * * php /var/www/crabitan_bellevue/bin/cron/send_deletion_reminders.php >> /var/log/crabitan/reminders.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('send_deletion_reminders');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $model  = new \Model\AccountModel();
    $mailer = new \Service\MailService();
    $sent   = 0;

    foreach ([7, 1] as $days) {
        $accounts = $model->getAccountsPendingDeletionReminder($days);

        foreach ($accounts as $account) {
            $name = trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''))
                ?: $account['email'];

            if ($dryRun) {
                $log("[DRY-RUN] Would send J-{$days} reminder to {$account['email']} (id={$account['id']})");
                continue;
            }

            try {
                $mailer->sendDeletionReminder(
                    $account['email'],
                    $name,
                    $account['lang'] ?? 'fr',
                    $days,
                    $account['scheduled_deletion_at'],
                    $account['reactivation_token'] ?? ''
                );
                if ($days === 7) {
                    $model->markDeletionReminder7dSent((int) $account['id']);
                } else {
                    $model->markDeletionReminder1dSent((int) $account['id']);
                }
                $sent++;
                $log("[J-{$days}] Rappel envoyé à {$account['email']} (id={$account['id']})");
            } catch (\Throwable $e) {
                $log("[J-{$days}] ERREUR id={$account['id']} : " . $e->getMessage());
            }
        }
    }

    if (!$dryRun) {
        $log("{$sent} rappel(s) envoyé(s)");
    }
}));
