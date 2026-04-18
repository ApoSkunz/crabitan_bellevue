<?php

/**
 * Script cron — purge des tokens de notation de commande expirés.
 *
 * Supprime les lignes de `order_rating_tokens` dont `expires_at < NOW()`.
 * TTL 90 jours positionné à la création du token (RatingModel::generateOrderToken).
 *
 * À planifier quotidiennement (ex. 03:00).
 * Sur IONOS : Hébergement → Cron jobs → HTTP GET /cron/purge-order-rating-tokens?token=...
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('purge_order_rating_tokens');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $db = \Core\Database::getInstance();

    if ($dryRun) {
        $count = $db->fetchOne(
            'SELECT COUNT(*) AS n FROM order_rating_tokens WHERE expires_at < NOW()'
        )['n'] ?? 0;
        $log("[DRY-RUN] Would delete {$count} token(s) expiré(s) — aucune écriture BDD");
        return;
    }

    $deleted = $db->execute(
        'DELETE FROM order_rating_tokens WHERE expires_at < NOW()'
    );

    $log("order_rating_tokens : {$deleted} token(s) expiré(s) supprimé(s)");
}));
