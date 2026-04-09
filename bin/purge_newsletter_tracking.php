<?php

/**
 * Script de purge du tracking newsletter — suppression des données d'ouverture et de clic
 * antérieures à 13 mois (conformité RGPD Art. 5-1-e, intérêt légitime).
 *
 * À planifier via cron (ex: le 1er de chaque mois à 04h00) :
 *   0 4 1 * * php /var/www/crabitan_bellevue/bin/purge_newsletter_tracking.php >> /var/log/crabitan/purge.log 2>&1
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

$model   = new \Model\NewsletterTrackingModel();
$deleted = $model->purgeOlderThan13Months();

echo date('Y-m-d H:i:s') . ' — ' . $deleted . ' enregistrement(s) de tracking newsletter purgé(s)' . PHP_EOL;
