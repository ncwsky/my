<?php
require __DIR__ . "/vendor/autoload.php";
require __DIR__ . "/conf.php";
require __DIR__ . "/vendor/myphps/myphp/base.php";

/*
结合crontab使用
#执行命令
* * * * * cd pwd && /usr/bin/sh ./queue.sh Queue 2
* * * * * cd pwd && /usr/bin/sh ./queue.sh ClearTmp

*/

//在项目所有目录执行此文件 自动获取项目路径 未在
if (empty($_SERVER['argv']) || count($_SERVER['argv']) == 1) {
    die('no argv');
}

if (!IS_CLI) {
    die('no cli');
}
\myphp\Log::Dir('cli');
echo implode(" ", $_SERVER['argv']).PHP_EOL;
$a = 'cli'.ucfirst($_SERVER['argv'][1]);
$params = array_slice($_SERVER['argv'], 2);

//脚本命令处理
function cliRun($a, $params){
    try {
        if(!function_exists($a)){
            throw new \Exception($a .' not exists');
        }
        $ret = call_user_func_array($a, $params);
        if (is_bool($ret)) {
            echo $ret ? 'ok' : 'fail:'.\myphp\Tool::err();
        } elseif (is_scalar($ret)) {
            echo $ret;
        } elseif($ret!==null) {
            echo toJson($ret);
        }
    } catch (\Throwable $e) {
        echo $e->getMessage();
    }
}

cliRun($a, $params);
echo PHP_EOL;

/**
 * 生成表model类
 * php cli.php Model 1 "common\model" "CommonModel"
 * @param string $table
 * @param string $namespace
 * @param string $baseName
 * @param string $dbName
 * @throws Exception
 */
function cliModel($table='1', $namespace='common\model', $baseName='\myphp\Model', $dbName='db'){
    if ($table != '1') {
        $tables = strpos($table, ',') ? explode(',', $table) : [$table];
    } else {
        $tables = db($dbName)->getTables();
    }
    foreach ($tables as $name){
        $ret = \myphp\Tool::initModel($name, $namespace, $baseName, $dbName);
        if($ret){
            echo $name.': ok'.PHP_EOL;
        }else{
            echo $name.': fail:'.\myphp\Tool::err().PHP_EOL;
        }
    }
    echo 'done'.PHP_EOL;
}