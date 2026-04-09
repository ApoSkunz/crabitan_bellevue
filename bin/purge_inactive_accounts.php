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
 * Logs écrits hors webroot dans var/logs/purge_inactive.log.
 *
 * À planifier via cron (ex: tous les jours à 02h00) :
 *   0 2 * * * php /var/www/crabitan_bellevue/bin/purge_inactive_accounts.php >> /var/www/crabitan_bellevue/var/logs/purge_inactive.log 2>&1
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

$logDir = ROOT_PATH . '/var/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$now            = date('Y-m-d H:i:s');
$accountModel   = new \Model\AccountModel();
$mailer         = new \Service\MailService();
$inactiveMonths = (int) ($_ENV['RGPD_INACTIVITY_MONTHS'] ?? 36);
$warned         = 0;
$warnErrors     = 0;
$softDeleted    = 0;
$deleteErrors   = 0;

echo $now . ' — === Purge inactivité RGPD ===' . PHP_EOL;
echo $now . " — Seuil d'inactivité : {$inactiveMonths} mois" . PHP_EOL;

// ── Phase 1 : Avertissement ──────────────────────────────────────────────────
$inactive = $accountModel->getInactiveAccounts($inactiveMonths);
echo $now . ' — Phase 1 — comptes inactifs à avertir : ' . count($inactive) . PHP_EOL;

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

        $accountModel->markInactivityWarningSent($id);
        $warned++;

        echo $now . " — [WARN] Email envoyé à {$account['email']} (id={$id}), suppression prévue {$deletionDate}" . PHP_EOL;
    } catch (\Throwable $e) {
        $warnErrors++;
        echo $now . " — [WARN-ERR] id={$id} : " . $e->getMessage() . PHP_EOL;
    }
}

// ── Phase 2 : Soft-delete ────────────────────────────────────────────────────
$toDelete = $accountModel->getAccountsToSoftDelete();
echo $now . ' — Phase 2 — comptes à soft-delete : ' . count($toDelete) . PHP_EOL;

foreach ($toDelete as $account) {
    $id = (int) $account['id'];

    try {
        $accountModel->softDeleteForInactivity($id);
        $softDeleted++;

        echo $now . " — [DELETE] Compte id={$id} ({$account['email']}) soft-deleted" . PHP_EOL;
    } catch (\Throwable $e) {
        $deleteErrors++;
        echo $now . " — [DELETE-ERR] id={$id} : " . $e->getMessage() . PHP_EOL;
    }
}

echo $now . " — Terminé : {$warned} avertissement(s), {$warnErrors} erreur(s) warn, {$softDeleted} soft-delete(s), {$deleteErrors} erreur(s) delete" . PHP_EOL;
