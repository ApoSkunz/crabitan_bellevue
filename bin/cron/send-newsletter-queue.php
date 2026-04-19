<?php

/**
 * Cron — Envoi de la file d'attente newsletter (nouveau vin).
 *
 * Traite un batch de destinataires en attente dans newsletter_send_queue.
 * Batch size : CRON_NEWSLETTER_BATCH_SIZE (défaut 30).
 * Délai inter-envoi : CRON_MAIL_DELAY_MS (défaut 0, recommandé 1500 sur IONOS < 30 jours).
 *
 * À planifier toutes les heures (ou plus souvent si volume élevé) :
 *   0 * * * * php /var/www/crabitan_bellevue/bin/cron/send-newsletter-queue.php >> /var/log/crabitan/cron.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('send-newsletter-queue');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $queueModel = new \Model\NewsletterSendQueueModel();
    $wineModel  = new \Model\WineModel();
    $mailer     = new \Service\MailService();
    $appUrl     = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');
    $batchSize  = max(1, (int) ($_ENV['CRON_NEWSLETTER_BATCH_SIZE'] ?? 30));
    $delayMs    = max(0, (int) ($_ENV['CRON_MAIL_DELAY_MS'] ?? 0));

    $pending = $queueModel->getPending($batchSize);
    $total   = count($pending);

    if ($total === 0) {
        $log('File newsletter vide — aucun envoi');
        return;
    }

    $log("Batch newsletter : {$total} destinataire(s) à traiter");

    $sent   = 0;
    $failed = 0;

    foreach ($pending as $item) {
        $id     = (int) $item['id'];
        $wineId = (int) $item['wine_id'];
        $nlId   = (int) $item['newsletter_id'];
        $email  = (string) $item['recipient_email'];
        $name   = (string) $item['recipient_name'];
        $unsub  = (string) $item['unsub_token'];
        $lang   = (string) $item['lang'];

        if ($dryRun) {
            $log("[DRY-RUN] Would send newsletter #{$nlId} to {$email} (queue_id={$id})");
            continue;
        }

        $wine = $wineModel->getById($wineId);
        if ($wine === null) {
            $log("[SKIP] wine_id={$wineId} introuvable — queue_id={$id}");
            $queueModel->markFailed($id);
            $failed++;
            continue;
        }

        try {
            $mailer->sendNewWineNewsletter($email, $name, $unsub, $wine, $appUrl, $lang);
            $queueModel->markSent($id);
            $sent++;
            $log("[OK] {$email} (queue_id={$id})");
        } catch (\Throwable $e) {
            $queueModel->markFailed($id);
            $failed++;
            $log("[ERR] {$email} (queue_id={$id}) : " . $e->getMessage());
        }

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    if (!$dryRun) {
        $log("Résultat : {$sent} envoyé(s), {$failed} échec(s)");
        $nlIds = array_unique(array_column($pending, 'newsletter_id'));
        foreach ($nlIds as $nlId) {
            $queueModel->refreshCampaignStats((int) $nlId);
        }
        $remaining = $queueModel->countPending();
        if ($remaining > 0) {
            $log("Restants en file : {$remaining}");
        }
    }
}));
