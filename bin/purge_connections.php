<?php

/**
 * Script de purge des sessions — suppression des connexions révoquées ou expirées.
 *
 * À planifier via cron (ex: tous les jours à 03h00) :
 *   0 3 * * * php /var/www/crabitan_bellevue/bin/purge_connections.php >> /var/log/crabitan/purge.log 2>&1
 *
 * Sur IONOS : Hébergement → Cron jobs → ajouter la commande ci-dessus.
 *
 * Supprime les lignes de la table `connections` dont le statut est 'revoked'
 * ou dont la date d'expiration est dépassée — évite la croissance infinie de la table.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH', ROOT_PATH . '/src');
define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';

$model   = new \Model\ConnectionModel();
$purged  = $model->purgeExpired();

echo date('Y-m-d H:i:s') . ' — ' . $purged . ' session(s) purgée(s)' . PHP_EOL;
