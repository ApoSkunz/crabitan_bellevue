<?php

/**
 * Cron de pseudonymisation des commandes de plus de 10 ans.
 *
 * À planifier quotidiennement :
 *   0 2 * * * php /var/www/html/scripts/pseudonymize-orders.php >> /var/log/crabitan-pseudonymize.log 2>&1
 *
 * Respecte l'Art. L123-22 Code de commerce (archivage 10 ans) et le RGPD Art. 5
 * (minimisation des données personnelles au-delà de la durée de conservation).
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH',  ROOT_PATH . '/src');
define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';

$model = new \Model\OrderModel();
$count = $model->pseudonymizeExpired();

$ts  = date('Y-m-d H:i:s');
$msg = "[{$ts}] pseudonymize-orders: {$count} snapshot(s) pseudonymisé(s)." . PHP_EOL;
echo $msg;
