<?php

/**
 * Cron — Séquence de bienvenue newsletter J+7 et J+30.
 *
 * Traite les abonnés confirmés qui n'ont pas encore reçu leur email J+7 ou J+30.
 * Batch size : cron_newsletter_batch_size (défaut 30).
 *
 * À planifier quotidiennement :
 *   0 8 * * * php /var/www/crabitan_bellevue/bin/cron/send-newsletter-welcome-sequence.php >> /var/log/crabitan/cron.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('send-newsletter-welcome-sequence');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $pdo       = \Core\Database::getInstance();
    $mailer    = new \Service\MailService();
    $batchSize = max(1, \Core\FeatureFlags::getInt('cron_newsletter_batch_size', 30));
    $appUrl    = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');

    // --- J+7 : éducation domaine ---
    $stmtJ7 = $pdo->prepare(
        'SELECT id, email, lang
         FROM newsletter_subscriptions
         WHERE newsletter_confirmed = 1
           AND welcome_j7_sent_at IS NULL
           AND created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
         LIMIT :limit'
    );
    $stmtJ7->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
    $stmtJ7->execute();
    $j7Rows = $stmtJ7->fetchAll(\PDO::FETCH_ASSOC);

    $sentJ7 = 0;
    foreach ($j7Rows as $row) {
        $id    = (int) $row['id'];
        $email = (string) $row['email'];
        $lang  = (string) ($row['lang'] ?? 'fr');

        if ($dryRun) {
            $log("[DRY-RUN] J+7 would send to {$email}");
            continue;
        }
        try {
            $mailer->sendNewsletterWelcomeJ7($email, '', $lang);
            $pdo->prepare('UPDATE newsletter_subscriptions SET welcome_j7_sent_at = NOW() WHERE id = ?')
                ->execute([$id]);
            $sentJ7++;
            $log("[J+7 OK] {$email}");
        } catch (\Throwable $e) {
            $log("[J+7 ERR] {$email} : " . $e->getMessage());
        }
    }

    // --- J+30 : offre découverte ---
    $stmtJ30 = $pdo->prepare(
        'SELECT id, email, lang, newsletter_token_hash
         FROM newsletter_subscriptions
         WHERE newsletter_confirmed = 1
           AND welcome_j7_sent_at IS NOT NULL
           AND welcome_j30_sent_at IS NULL
           AND created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
         LIMIT :limit'
    );
    $stmtJ30->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
    $stmtJ30->execute();
    $j30Rows = $stmtJ30->fetchAll(\PDO::FETCH_ASSOC);

    $sentJ30 = 0;
    foreach ($j30Rows as $row) {
        $id         = (int) $row['id'];
        $email      = (string) $row['email'];
        $lang       = (string) ($row['lang'] ?? 'fr');
        $tokenHash  = (string) ($row['newsletter_token_hash'] ?? '');
        $unsubToken = $tokenHash !== '' ? $tokenHash : bin2hex(random_bytes(16));
        $unsubUrl   = $appUrl . '/' . $lang . '/newsletter/desabonnement?token=' . urlencode($unsubToken);

        if ($dryRun) {
            $log("[DRY-RUN] J+30 would send to {$email}");
            continue;
        }
        try {
            $mailer->sendNewsletterWelcomeJ30($email, '', $lang, $unsubToken);
            $pdo->prepare('UPDATE newsletter_subscriptions SET welcome_j30_sent_at = NOW() WHERE id = ?')
                ->execute([$id]);
            $sentJ30++;
            $log("[J+30 OK] {$email}");
        } catch (\Throwable $e) {
            $log("[J+30 ERR] {$email} : " . $e->getMessage());
        }
    }

    if (!$dryRun) {
        $log("J+7 : {$sentJ7} envoyé(s) — J+30 : {$sentJ30} envoyé(s)");
    }
}));
