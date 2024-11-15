#!/usr/bin/env php
<?php
$_SERVER['SCRIPT_FILENAME'] = __FILE__; //重置运行

define('APP_PATH',__DIR__.'/app');
define('COMMON', __DIR__.'/common');

require __DIR__ . '/vendor/myphps/my-php-srv/Load.php';
require __DIR__ . '/vendor/workerman/workerman/Autoloader.php';
require __DIR__ . '/vendor/myphps/myphp/GetOpt.php';

//解析命令参数
GetOpt::parse('sp:l:n:', ['help', 'swoole', 'port:', 'num:']);
//处理命令参数
$isSwoole = GetOpt::has('s', 'swoole');
$port = (int)GetOpt::val('p', 'port');
$num = (int)GetOpt::val('n', 'num');
//自动检测
if (!$isSwoole && !SrvBase::workermanCheck() && defined('SWOOLE_VERSION')) {
    $isSwoole = true;
}

if (GetOpt::has('h', 'help')) {
    echo 'Usage: php app.php OPTION [restart|reload|stop]
   or: app.php OPTION [restart|reload|stop]

   --help
   -p --port=6051    监听端口
   -n --num=10       进程数量
   -s --swoole       swolle运行', PHP_EOL;
    exit(0);
}

$port > 0 && define('APP_PORT', $port);
$num > 0 && define('APP_RUN_NUM', $num);

$config = require(__DIR__ . '/app.conf.php');
if ($isSwoole) {
    $srv = $config['type'] == 'http' ? new SwooleHttpSrv($config) : new SwooleSrv($config);
} else {
    \Workerman\Protocols\Http\Session::$autoUpdateTimestamp = true;
    $srv = new WorkerManSrv($config);
    Worker2::$stopTimeout = 10; //强制进程结束等待时间
}
$srv->run($argv);