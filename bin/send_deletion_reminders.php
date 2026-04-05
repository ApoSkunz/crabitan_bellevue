<?php

/**
 * Script de rappels avant suppression définitive du compte.
 *
 * Envoie un email J-7 puis J-1 aux clients dont le compte est en attente
 * d'anonymisation RGPD. Prévient les doublons via les colonnes
 * deletion_reminder_7d_sent_at / deletion_reminder_1d_sent_at.
 *
 * À planifier via cron (ex: tous les jours à 09h00) :
 *   0 9 * * * php /var/www/crabitan_bellevue/bin/send_deletion_reminders.php >> /var/log/crabitan/reminders.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');
define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';

$accountModel = new \Model\AccountModel();
$mailer       = new \Service\MailService();
$now          = date('Y-m-d H:i:s');
$sent         = 0;
$errors       = 0;

foreach ([7, 1] as $days) {
    $accounts = $accountModel->getAccountsPendingDeletionReminder($days);

    foreach ($accounts as $account) {
        try {
            $name = trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''));
            if ($name === '') {
                $name = $account['email'];
            }

            $mailer->sendDeletionReminder(
                $account['email'],
                $name,
                $account['lang'] ?? 'fr',
                $days,
                $account['scheduled_deletion_at'],
                $account['reactivation_token'] ?? ''
            );

            $accountModel->markDeletionReminderSent((int) $account['id'], $days);
            $sent++;

            echo $now . " — [J-{$days}] Rappel envoyé à {$account['email']} (id={$account['id']})" . PHP_EOL;
        } catch (\Throwable $e) {
            $errors++;
            echo $now . " — [J-{$days}] ERREUR compte id={$account['id']} : " . $e->getMessage() . PHP_EOL;
        }
    }
}

echo $now . " — Terminé : {$sent} rappel(s) envoyé(s), {$errors} erreur(s)" . PHP_EOL;
