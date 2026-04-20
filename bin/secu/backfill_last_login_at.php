<?php

/**
 * Backfill one-shot de la colonne accounts.last_login_at.
 *
 * Priorité pour déterminer last_login_at (première valeur non NULL trouvée) :
 *   1. MAX(connections.created_at)  — dernière connexion tracée
 *   2. MAX(orders.ordered_at)       — dernière commande passée
 *   3. accounts.created_at          — date d'inscription (fallback garanti non NULL)
 *
 * Puis réinitialise inactivity_warning_sent_at = NULL si et seulement si :
 *   - last_login_at IS NOT NULL
 *   - last_login_at > NOW() - RGPD_INACTIVITY_MONTHS (compte encore "actif" sur l'échelle RGPD)
 *   → l'avertissement envoyé était un faux positif (données corrompues avant migration 005)
 *
 * Si last_login_at IS NULL après backfill (impossible en pratique — fallback created_at) :
 *   inactivity_warning_sent_at est laissé intact.
 *
 * Exécution :
 *   php bin/secu/backfill_last_login_at.php [--dry-run]
 *
 * ⚠️  À exécuter après la migration db_crabitan_mig_005.sql.
 * ⚠️  Snapshot BDD recommandé avant exécution en production.
 * ⚠️  NE PAS planifier via cron — exécution manuelle unique.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
defined('SRC_PATH')  || define('SRC_PATH',  ROOT_PATH . '/src');
defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$dryRun = in_array('--dry-run', $argv ?? [], true);

// ------------------------------------------------------------
// UI
// ------------------------------------------------------------
$log  = static function (string $msg): void { echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL; };
$ok   = static function (string $msg): void { echo '[OK]  ' . $msg . PHP_EOL; };
$warn = static function (string $msg): void { echo '[WARN] ' . $msg . PHP_EOL; };

$log('backfill_last_login_at.php — démarrage' . ($dryRun ? ' (DRY-RUN)' : ''));

// ------------------------------------------------------------
// Connexion BDD + seuil inactivité
// ------------------------------------------------------------
/** @var \Core\Database $db */
$db = \Core\Database::getInstance();

$inactivityMonths = (int) ($_ENV['RGPD_INACTIVITY_MONTHS'] ?? 36);
$log("Seuil inactivité RGPD : {$inactivityMonths} mois");

// ------------------------------------------------------------
// Étape 1 — Backfill last_login_at
// Priorité : MAX(connections) > MAX(orders.ordered_at) > accounts.created_at
// ------------------------------------------------------------
$log('Étape 1 — Backfill last_login_at (NULL uniquement)...');

$toFill = $db->fetchAll(
    "SELECT
         a.id,
         a.created_at,
         (SELECT MAX(c.created_at)  FROM connections c WHERE c.user_id = a.id) AS last_conn,
         (SELECT MAX(o.ordered_at)  FROM orders o       WHERE o.user_id = a.id) AS last_order
     FROM accounts a
     WHERE a.last_login_at IS NULL
     ORDER BY a.id"
);

$filled = 0;
foreach ($toFill as $row) {
    $value = $row['last_conn'] ?? $row['last_order'] ?? $row['created_at'];

    $source = match(true) {
        $row['last_conn']  !== null => 'connexion',
        $row['last_order'] !== null => 'commande',
        default                     => 'inscription',
    };

    if (!$dryRun) {
        $db->execute(
            "UPDATE accounts SET last_login_at = ? WHERE id = ?",
            [$value, $row['id']]
        );
    }

    $log("  account #{$row['id']} → {$value} (source: {$source})" . ($dryRun ? ' [DRY-RUN]' : ''));
    $filled++;
}

$ok("{$filled} compte(s) backfillé(s)" . ($dryRun ? ' [simulé]' : ''));

// ------------------------------------------------------------
// Étape 2 — Purge inactivity_warning_sent_at
// Condition : last_login_at IS NOT NULL
//             AND last_login_at > NOW() - RGPD_INACTIVITY_MONTHS
// → avertissement envoyé alors que le compte était actif = faux positif
// ------------------------------------------------------------
$log("Étape 2 — Purge inactivity_warning_sent_at (comptes actifs < {$inactivityMonths} mois)...");

$stale = $db->fetchAll(
    "SELECT id, last_login_at, inactivity_warning_sent_at
     FROM accounts
     WHERE inactivity_warning_sent_at IS NOT NULL
       AND last_login_at IS NOT NULL
       AND last_login_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
       AND deleted_at IS NULL",
    [$inactivityMonths]
);

$reset = 0;
foreach ($stale as $row) {
    if (!$dryRun) {
        $db->execute(
            "UPDATE accounts SET inactivity_warning_sent_at = NULL WHERE id = ?",
            [$row['id']]
        );
    }
    $log("  account #{$row['id']} — warning supprimé"
        . " (last_login_at={$row['last_login_at']})" . ($dryRun ? ' [DRY-RUN]' : ''));
    $reset++;
}

$ok("{$reset} avertissement(s) faux-positif(s) réinitialisé(s)" . ($dryRun ? ' [simulé]' : ''));

// ------------------------------------------------------------
// Étape 3 — Bilan : simulation du cron getInactiveAccounts()
// Reproduit exactement la requête AccountModel::getInactiveAccounts()
// après backfill — montre combien de comptes seraient avertis si le cron tournait maintenant
// ------------------------------------------------------------
$log("Étape 3 — Simulation cron inactivité (seuil {$inactivityMonths} mois)...");

$wouldWarn = $db->fetchAll(
    "SELECT a.id, a.email, a.role,
            a.last_login_at,
            a.created_at,
            a.inactivity_warning_sent_at
     FROM accounts a
     WHERE a.role = 'customer'
       AND a.deleted_at IS NULL
       AND a.inactivity_warning_sent_at IS NULL
       AND COALESCE(a.last_login_at, a.created_at) < DATE_SUB(NOW(), INTERVAL ? MONTH)
     ORDER BY a.last_login_at ASC",
    [$inactivityMonths]
);

$warnCount = count($wouldWarn);
$log("  {$warnCount} compte(s) seraient avertis par le cron :");
foreach ($wouldWarn as $row) {
    $lastActivity = $row['last_login_at'] ?? $row['created_at'] . ' (inscription)';
    $log("    account #{$row['id']} — {$row['email']} — dernière activité : {$lastActivity}");
}
$ok("Bilan cron : {$warnCount} compte(s) éligibles à l'avertissement RGPD");

// ------------------------------------------------------------
// Résumé
// ------------------------------------------------------------
$log('--- Résumé ---');
$log("  last_login_at backfillés          : {$filled}");
$log("  inactivity_warning_sent_at purgés : {$reset}");
$log("  comptes qui seraient avertis      : {$warnCount}");

if ($dryRun) {
    $warn('Mode DRY-RUN — aucune écriture en BDD. Relancer sans --dry-run pour appliquer.');
}

$log('Terminé.');
