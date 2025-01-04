<?php

declare(strict_types=1);
//Phar包路径处理
if (class_exists(Phar::class, false) && Phar::running(false)) {
    define('MY_PHAR_PATH', Phar::running());
    define('APP_RUN_DIR', dirname(Phar::running(false)));
} else {
    define('APP_RUN_DIR', __DIR__);
}

require __DIR__ . "/vendor/autoload.php";
require APP_RUN_DIR . "/conf.php";
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
function cliRun($a, $params)
{
    try {
        if (!function_exists($a)) {
            throw new \Exception($a .' not exists');
        }
        $ret = call_user_func_array($a, $params);
        if (is_bool($ret)) {
            echo $ret ? 'ok' : 'fail:'.\myphp\Tool::err();
        } elseif (is_scalar($ret)) {
            echo $ret;
        } elseif ($ret !== null) {
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
function cliModel(string $table = '1', string $namespace = 'common\model', string $baseName = '\myphp\Model', string $dbName = 'db')
{
    if ($table != '1') {
        $tables = strpos($table, ',') ? explode(',', $table) : [$table];
    } else {
        $tables = db($dbName)->getTables();
    }
    foreach ($tables as $name) {
        $ret = \myphp\Tool::initModel($name, $namespace, $baseName, $dbName);
        if ($ret) {
            echo $name.': ok'.PHP_EOL;
        } else {
            echo $name.': fail:'.\myphp\Tool::err().PHP_EOL;
        }
    }
    echo 'done'.PHP_EOL;
}

/**
 * 生成phar文件 仅用于常驻内存模式下运行
 * php cli.php phar
 * php -d phar.readonly=0 cli.php phar   使用 -d 参数来临时修改 phar.readonly 设置
 * @param string $sigName
 * @param string $private_key_file
 */
function cliPhar(string $sigName = 'sha256', string $private_key_file = '')
{
    if (!is_dir(__DIR__ . '/dist/web')) {
        mkdir(__DIR__ . '/dist/web', 0755, true);
    }
    $pharFile = __DIR__ . '/dist/my.phar';
    if (file_exists($pharFile)) {
        unlink($pharFile);
    }

    $sigTypeMap = ['md5' => Phar::MD5, 'sha1' => Phar::SHA1, 'sha256' => Phar::SHA256, 'sha512' => Phar::SHA512, 'openssl' => Phar::OPENSSL];
    $sigType = $sigTypeMap[$sigName] ?? Phar::SHA256;

    $exRegex = '#^(?!.*(\.log|\.pid|\.sh|\.gitignore|runLock|composer.lock|composer.json|/.github/|/.idea/|/.git/|/runtime/|/log/|/vendor/bin/|/build/|/dist/|/web/))(.*)$#';
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
        'README.md',
        'app.conf.php',
        'app.conf.local.php',
        'conf.php',
        'conf.local.php'
    ];
    foreach ($exFiles as $file) {
        if ($phar->offsetExists($file)) {
            $phar->delete($file);
        }
    }
    // /web/index.php
    $phar->addFromString('web/index.php', "<?php
//Phar包路径处理
define('MY_PHAR_PATH', Phar::running());
define('APP_RUN_DIR', dirname(Phar::running(false)));

define('APP_PATH', MY_PHAR_PATH.'/app');
define('COMMON', MY_PHAR_PATH.'/common');
require MY_PHAR_PATH . '/vendor/autoload.php';
require APP_RUN_DIR . '/conf.php';
require MY_PHAR_PATH . '/vendor/myphps/myphp/base.php';
myphp::Run();");

    echo '开始生成Phar',PHP_EOL;
    //在直接使用my.phar时直接执行app.php
    $phar->setStub("#!/usr/bin/env php
<?php
Phar::mapPhar('my');
require 'phar://my/app.php';
__HALT_COMPILER();
");

    $phar->stopBuffering();
    unset($phar);
    //复制配置文件
    copy(__DIR__ . '/app.conf.php', __DIR__ . '/dist/app.conf.php');
    copy(__DIR__ . '/conf.php', __DIR__ . '/dist/conf.php');
    copy(__DIR__ . '/web/.htaccess', __DIR__ . '/dist/web/.htaccess');
    //生成脚本执行文件
    copy(__DIR__.'/my.bat', __DIR__.'/dist/my.bat');
    copy(__DIR__ . '/cmd.php', __DIR__ . '/dist/cmd.php');
    copy(__DIR__ . '/queue.sh', __DIR__ . '/dist/queue.sh');
    file_put_contents(__DIR__.'/dist/cli.php', "#!/usr/bin/env php
<?php
/*
结合crontab使用
#执行命令
* * * * * cd pwd && /usr/bin/sh ./queue.sh Queue 2
* * * * * cd pwd && /usr/bin/sh ./queue.sh ClearTmp
*/
require 'phar://my.phar/cli.php';");
    file_put_contents(__DIR__.'/dist/my', "#!/usr/bin/env php
<?php
/**
 * cli模式下脚本执行入口
 * my --init 应用初始化
 * my [--run=指定应用目录] [m/]c/a [\"b=1&d=1\"|b=1 d=1]
 */
require 'phar://my.phar/my';");
    //web
    file_put_contents(__DIR__.'/dist/web/index.php', "<?php
require 'phar://../my.phar/web/index.php';");

    echo 'Phar生成完成',PHP_EOL;
}

/**
 * 简单的应用异步处理
 * php cli.php Queue
 * @param int $size
 */
function cliQueue(int $size = 100)
{
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
            [$func, $params] = json_decode($data, true);
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

            if ($n > $size) {
                break;
            } //限制条数 防止有数据时进程一直处理
        }
    } catch (\Exception $e) {
        //todo alarm
        toLog(sprintf('line:%s, file:%s, err:%s, trace:%s', $e->getLine(), $e->getFile(), $e->getMessage(), $e->getTraceAsString()), 'queue');
    }
    redis()->decr('__queue_run');

    echo 'queue ok['.$n.']',PHP_EOL;
}

/**
 * 每10分钟执行一次 清除临时图片
 * php cli.php ClearTmp
 */
function cliClearTmp()
{
    //清除tmp过期图片 使用定时任务来处理
    echo SITE_WEB . '/tmp run clear' . PHP_EOL;
    $clearTmp = new \myphp\File();
    $clearTmp->setDir(SITE_WEB . '/tmp');
    $clearTmp->clear(3600, true);
    //$clearTmp->setDir(ROOT.'/tmp');
    //$clearTmp->clear(3600, true);
    echo SITE_WEB . '/tmp run end' . PHP_EOL;
}
