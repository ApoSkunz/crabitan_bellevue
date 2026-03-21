<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('SRC_PATH',  ROOT_PATH . '/src');
define('LANG_PATH', ROOT_PATH . '/lang');

require_once ROOT_PATH . '/vendor/autoload.php';
require_once ROOT_PATH . '/config/config.php';

use Core\Router;
use Core\Request;

$request = new Request();
$router  = new Router($request);

require_once ROOT_PATH . '/config/routes.php';

$router->dispatch();
