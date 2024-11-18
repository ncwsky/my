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
cliRun($a, $params);

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
    echo PHP_EOL;
}

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

/**
 * 生成phar文件
 * php cli.php phar
 * php -d phar.readonly=0 cli.php phar   使用 -d 参数来临时修改 phar.readonly 设置
 * @param int $sigName
 * @param string $private_key_file
 */
function cliPhar($sigName='sha256', $private_key_file=''){
    $pharFile = __DIR__ . '/my.phar';
    if (file_exists($pharFile)) {
        unlink($pharFile);
    }

    $sigTypeMap = ['md5' => Phar::MD5, 'sha1' => Phar::SHA1, 'sha256' => Phar::SHA256, 'sha512' => Phar::SHA512, 'openssl' => Phar::OPENSSL];
    $sigType = $sigTypeMap[$sigName] ?? Phar::SHA256;

    $exRegex = '#^(?!.*(\.log|\.pid|\.sh|\.gitignore|runLock|composer.lock|composer.json|/.github/|/.idea/|/.git/|/runtime/|/log/|/vendor-bin/|/build/))(.*)$#';
    $phar = new Phar($pharFile, 0, 'my');
    $phar->startBuffering();

    if ($sigType === Phar::OPENSSL) {
        if (!file_exists($private_key_file)) {
            throw new RuntimeException("使用'Phar::OPENSSL'签名需要提供私钥文件");
        }
        $private = openssl_get_privatekey(file_get_contents($private_key_file));
        $pkey = '';
        openssl_pkey_export($private, $pkey);
        $phar->setSignatureAlgorithm($sigType, $pkey);
    } else {
        $phar->setSignatureAlgorithm($sigType);
    }

    $phar->buildFromDirectory(__DIR__, $exRegex);

    $exFiles = [
        '.gitattributes',
        'app.conf.php',
        'app.conf.local.php',
        'conf.php',
        'conf.local.php'
    ];
    foreach ($exFiles as $file) {
        if($phar->offsetExists($file)){
            $phar->delete($file);
        }
    }

    echo '开始生成Phar',PHP_EOL;
    $phar->setStub("#!/usr/bin/env php
<?php
define('IN_PHAR', true);
Phar::mapPhar('my');
require 'phar://my/app.php';
__HALT_COMPILER();
");

    $phar->stopBuffering();
    unset($phar);
    echo 'Phar生成完成',PHP_EOL;
}

/**
 * 简单的应用异步处理
 * php cli.php Queue
 * @param int $size
 */
function cliQueue($size=100){
    $run = (int)redis()->get('__queue_run');
    if ($run >= 10) {
        echo '最多允许10个处理进程', PHP_EOL;
        return;
    }
    redis()->incr('__queue_run'); //记录运行进程数

    $n = 0;
    try {
        $time = time();
        //延迟入列
        $items = redis()->zrevrangebyscore('__queueZ', $time, '-inf');
        if ($items) {
            echo '延迟处理数:' . count($items), PHP_EOL;
            foreach ($items as $cmd) {
                redis()->rpush('__queue', $cmd); //将值推入到尾部
            }
            redis()->zremrangebyscore('__queueZ', '-inf', $time); //清除已处理的数据
        }
        //处理队列
        while ($data = redis()->lpop('__queue')) {
            $n++;
            list($func, $params) = json_decode($data, true);
            $start_time = microtime(true);
            echo date("Y/m/d H:i:s").' start: ' . $data, PHP_EOL;
            if (function_exists($func)) {
                cliRun($func, $params);
            } else {
                if (is_array($params)) {
                    $params = http_build_query($params, "", "&", PHP_QUERY_RFC3986);
                }
                $params = (strpos($func, '?') ? '&' : '?') . $params;
                $cmd = strpos($params, '&') ? '"' . $func . $params . '"' : $func . $params;
                $cmd = 'php my ' . $cmd;
                $ret = shell_exec($cmd);
                echo $cmd . ' : ', $ret, PHP_EOL;
            }
            echo date("Y/m/d H:i:s").' end: '.run_time($start_time), PHP_EOL;

            if ($n > $size) break; //限制条数 防止有数据时进程一直处理
        }
    } catch (\Exception $e) {
        //todo alarm
        toLog(sprintf('line:%s, file:%s, err:%s, trace:%s', $e->getLine(), $e->getFile(), $e->getMessage(), $e->getTraceAsString()), 'queue');
    }
    redis()->decr('__queue_run');

    echo 'queue ok['.$n.']',PHP_EOL;
}