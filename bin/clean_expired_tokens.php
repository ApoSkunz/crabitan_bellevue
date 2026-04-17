<?php

/**
 * Script RGPD — purge des tokens expirés (Art. 5 §1e — minimisation des données).
 *
 * Nettoie quatre zones de la base de données :
 *   - password_reset        : tokens de réinitialisation mot de passe (grâce 7j)
 *   - device_confirm_tokens : tokens MFA appareil inconnu (grâce 7j)
 *   - connections           : sessions inactives et terminées (grâce 30j)
 *   - accounts              : token de vérification email expiré et non utilisé (grâce 7j)
 *
 * À planifier via cron (ex: tous les dimanches à 04h00) :
 *   0 4 * * 0 php /var/www/crabitan_bellevue/bin/clean_expired_tokens.php >> /var/log/crabitan/tokens.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 */

declare(strict_types=1);

require_once __DIR__ . '/cron-bootstrap.php';

$runner = new \Core\CronRunner('clean_expired_tokens');
exit($runner->run(function (callable $log, bool $dryRun): void {
    $service = new \Service\TokenPurgeService(\Core\Database::getInstance());

    if ($dryRun) {
        $log('[DRY-RUN] Would purge expired tokens (password_reset, device_confirm_tokens, connections, email_verification) — aucune écriture BDD');
        return;
    }

    $counts = $service->purgeAll();

    $log("password_reset        : {$counts['password_reset']} supprimé(s)");
    $log("device_confirm_tokens : {$counts['device_confirm_tokens']} supprimé(s)");
    $log("connections           : {$counts['connections']} supprimé(s)");
    $log("email_verification    : {$counts['email_verification']} compte(s) mis à jour");

    $total = array_sum($counts);
    $log("Total nettoyé : {$total} entrée(s)");
}));
