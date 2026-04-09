<?php

/**
 * Script RGPD — purge chronique des adresses orphelines.
 *
 * Supprime définitivement les adresses marquées preserved_for_order = 1
 * dont toutes les commandes associées sont en statut terminal (delivered / cancelled).
 *
 * Logs écrits hors webroot dans var/logs/purge_addresses.log.
 *
 * À planifier via cron (ex: tous les jours à 03h00) :
 *   0 3 * * * php /var/www/crabitan_bellevue/bin/purge_addresses.php >> /var/www/crabitan_bellevue/var/logs/purge_addresses.log 2>&1
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

$now          = date('Y-m-d H:i:s');
$addressModel = new \Model\AddressModel();

echo $now . ' — === Purge adresses orphelines ===' . PHP_EOL;

$orphaned = $addressModel->getOrphanedAddresses();
echo $now . ' — Adresses orphelines éligibles : ' . count($orphaned) . PHP_EOL;

if ($orphaned === []) {
    echo $now . ' — 0 adresse purgée' . PHP_EOL;
    exit(0);
}

foreach ($orphaned as $addr) {
    echo $now . " — Adresse id={$addr['id']} (user_id={$addr['user_id']}) éligible à la purge" . PHP_EOL;
}

try {
    $purged = $addressModel->purgeOrphaned();
    echo $now . " — Terminé : {$purged} adresse(s) supprimée(s)" . PHP_EOL;
} catch (\Throwable $e) {
    echo $now . ' — ERREUR : ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
