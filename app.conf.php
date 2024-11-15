<?php
$appConfig = [
    'name' => 'myApp', //服务名
    'ip' => '0.0.0.0', //监听地址
    'port' => defined('APP_PORT') ? APP_PORT : 6051, //监听地址
    'type' => 'http', //类型[http websocket tcp udp] swoole:websocket同时支持http请求
    'setting' => [
        'count' => defined('APP_RUN_NUM') ? APP_RUN_NUM : 10,    // 异步非阻塞CPU核数的1-4倍最合理 同步阻塞按实际情况来填写 如50-100
        'stdoutFile' => __DIR__ . '/stdout.log', //终端输出
        'pidFile' => __DIR__ . '/server.pid',
        'logFile' => __DIR__ . '/server.log', //日志文件

        //'reusePort'=> true, //设置当前worker是否开启监听端口复用(socket的SO_REUSEPORT选项)
        //'reloadable' => true, //设置当前Worker实例是否可以reload 默认为true
        'user' => 'www-data', //设置worker/task子进程的进程用户 提升服务器程序的安全性
        //swoole
        'open_http2_protocol' => true,
    ],
    'worker_load' => [
        __DIR__ . "/conf.php",
        __DIR__ . "/vendor/myphps/myphp/base.php",
        __DIR__ . '/vendor/autoload.php'
    ]
];
if (is_file(__DIR__ . '/app.conf.local.php')) {
    $appConfig = array_merge($appConfig, require(__DIR__ . '/app.conf.local.php'));
}
return $appConfig;