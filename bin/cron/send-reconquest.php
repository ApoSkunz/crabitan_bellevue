<?php

/**
 * Cron — Email de reconquête pour clients inactifs (dernier achat > 12 mois, jamais reconquis).
 *
 * Cible : comptes avec newsletter=1, dernier achat > 12 mois, reconquest_sent_at NULL (ou > 1 an).
 * Batch size : cron_newsletter_batch_size (défaut 30).
 *
 * À planifier hebdomadairement (faible fréquence pour éviter la fatigue) :
 *   0 10 * * 1 php /var/www/crabitan_bellevue/bin/cron/send-reconquest.php >> /var/log/crabitan/cron.log 2>&1
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('send-reconquest');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $pdo       = \Core\Database::getInstance();
    $mailer    = new \Service\MailService();
    $batchSize = max(1, \Core\FeatureFlags::getInt('cron_newsletter_batch_size', 30));
    $appUrl    = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');

    // Clients inactifs : dernier achat > 12 mois, newsletter active, non reconquis dans l'année
    $stmt = $pdo->prepare(
        'SELECT a.id, a.email, a.lang, a.newsletter_unsubscribe_token
         FROM accounts a
         WHERE a.newsletter = 1
           AND a.deleted_at IS NULL
           AND a.is_blocked = 0
           AND (a.reconquest_sent_at IS NULL OR a.reconquest_sent_at < DATE_SUB(NOW(), INTERVAL 1 YEAR))
           AND EXISTS (
               SELECT 1 FROM orders o
               WHERE o.user_id = a.id
                 AND o.status NOT IN (\'cancelled\', \'refunded\')
           )
           AND NOT EXISTS (
               SELECT 1 FROM orders o
               WHERE o.user_id = a.id
                 AND o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 AND o.status NOT IN (\'cancelled\', \'refunded\')
           )
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $total = count($rows);
    if ($total === 0) {
        $log('Aucun client à reconquérir');
        return;
    }

    $log("Reconquête : {$total} client(s) ciblé(s)");
    $sent   = 0;
    $failed = 0;

    foreach ($rows as $row) {
        $id         = (int) $row['id'];
        $email      = (string) $row['email'];
        $lang       = (string) ($row['lang'] ?? 'fr');
        $unsubToken = (string) ($row['newsletter_unsubscribe_token'] ?? '');
        $unsubUrl   = $appUrl . '/' . $lang . '/compte/desabonnement?token=' . urlencode($unsubToken);

        if ($dryRun) {
            $log("[DRY-RUN] Would reconquer {$email}");
            continue;
        }
        try {
            $mailer->sendReconquest($email, '', $lang, $unsubUrl);
            $pdo->prepare('UPDATE accounts SET reconquest_sent_at = NOW() WHERE id = ?')
                ->execute([$id]);
            $sent++;
            $log("[OK] {$email}");
        } catch (\Throwable $e) {
            $failed++;
            $log("[ERR] {$email} : " . $e->getMessage());
        }
    }

    if (!$dryRun) {
        $log("Résultat : {$sent} envoyé(s), {$failed} échec(s)");
    }
}));
