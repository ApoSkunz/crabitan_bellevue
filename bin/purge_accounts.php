<?php

/**
 * Script de purge RGPD — anonymisation des comptes supprimés après 30 jours.
 *
 * À planifier via cron (ex: tous les jours à 02h00) :
 *   0 2 * * * php /var/www/crabitan_bellevue/bin/purge_accounts.php >> /var/log/crabitan/purge.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 *
 * Les données de commandes sont conservées (obligations légales comptables — 10 ans).
 * Seules les données personnelles identifiantes sont anonymisées.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');
define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';

$model  = new \Model\AccountModel();
$purged = $model->purgeScheduledDeletions();

echo date('Y-m-d H:i:s') . ' — ' . $purged . ' compte(s) anonymisé(s)' . PHP_EOL;
