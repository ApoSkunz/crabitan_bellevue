<?php

/**
 * Bootstrap commun à tous les scripts cron (bin/).
 * À inclure en tête de chaque script via : require_once __DIR__ . '/cron-bootstrap.php';
 */

declare(strict_types=1);

defined('ROOT_PATH') || define('ROOT_PATH', dirname(__DIR__, 2));
defined('SRC_PATH')  || define('SRC_PATH', ROOT_PATH . '/src');
defined('LANG_PATH') || define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/src/helpers.php';
require_once ROOT_PATH . '/config/config.php';
