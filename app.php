#!/usr/bin/env php
<?php
$_SERVER['SCRIPT_FILENAME'] = __FILE__; //重置运行

define('APP_PATH',__DIR__.'/app');
define('COMMON', __DIR__.'/common');

require __DIR__ . '/vendor/myphps/my-php-srv/Load.php';
require __DIR__ . '/vendor/workerman/workerman/Autoloader.php';
\Workerman\Protocols\Http\Session::$autoUpdateTimestamp = true;
$srv = new WorkerManHttpSrv(require(__DIR__ . '/wokerman.conf.php'));
$srv->run($argv);