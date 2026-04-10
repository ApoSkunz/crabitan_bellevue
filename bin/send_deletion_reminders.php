<?php

/**
 * Rappels J-7 / J-1 avant anonymisation définitive du compte.
 * Cron : tous les jours à 09h00
 *   0 9 * * * php /var/www/crabitan_bellevue/bin/send_deletion_reminders.php
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('send_deletion_reminders');
exit($runner->run(function (callable $log): void {
    $model  = new \Model\AccountModel();
    $mailer = new \Service\MailService();
    $sent   = 0;

    foreach ([7, 1] as $days) {
        $accounts = $model->getAccountsPendingDeletionReminder($days);

        foreach ($accounts as $account) {
            $name = trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''))
                ?: $account['email'];

            try {
                $mailer->sendDeletionReminder(
                    $account['email'],
                    $name,
                    $account['lang'] ?? 'fr',
                    $days,
                    $account['scheduled_deletion_at'],
                    $account['reactivation_token'] ?? ''
                );
                $model->markDeletionReminderSent((int) $account['id'], $days);
                $sent++;
                $log("[J-{$days}] Rappel envoyé à {$account['email']} (id={$account['id']})");
            } catch (\Throwable $e) {
                $log("[J-{$days}] ERREUR id={$account['id']} : " . $e->getMessage());
            }
        }
    }

    $log("{$sent} rappel(s) envoyé(s)");
}));
