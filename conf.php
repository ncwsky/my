<?php
$cfg = [
    //数据库连接信息
    'db' => array(
        'dbms' => 'mysql', //数据库 mysql sqlite pgsql mssql oracle
        'server' => '127.0.0.1',    //数据库主机
        'name' => '',    //数据库名称
        'user' => '',    //数据库用户
        'pwd' => '',    //数据库密码
        'port' => 3306
    ),
    //城市数据
    'city' => array(
        'dbms' => 'sqlite',
        'name' => '' // __DIR__.'/data.sqlite',	//城市数据 https://github.com/modood/Administrative-divisions-of-China/tree/master/dist
    ),
    'redis' => array(
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'select' => 0 //选择库
    ),/*
    'session' => [
        //'type'=>'file',
        'expire' => 7200, //有效期
        /*
	    'type'=> 'redis', //redis|file 内置处理 默认系统file
        'prefix' => '_ss_', //用于非file方式的名前缀
        'host' => '127.0.0.1',
        'port' => 6379,
        'password'=>'',
        'select'=>2 //选择库 * /
    ],*/
    'log_dir' => __DIR__ . '/log', //日志记录主目录名称
    'log_level' => 1, //日志级别 0-5
    //'app_res_path'=>'http://www.test.com', //指定模板资源地址
    'domain' => [
        #'api'=>'http://www.test.com',
    ],
    'encode_key' => 'Mid51O2v*zZQxy2cZXD3B.Gvcz65ieIpxA3S_xRiFuHPmk8', //数据编码key
    'jwt_key' => 'QxyhTM2a1ka+pDgcZU3Fa_0UiU@Sx#DNVAqi$ZsQxy2cZXD3B.Gvcz65JDOE7rSkvBFRhJUo!yTj+CBfjyhTM2a1ka+pDgcZU3Ftj5&ZMid51O2v*zZc=',
    //中间件
    'middleware' => [
        //\myphp\middleware\Cors::class,
        \myphp\middleware\Options::class
    ],
    'url_maps' => [
        #'/index/notify/<type>'=>'/index/notify',
        #'/test.txt'=>'/index/test'
    ],
    'module_maps' => [ //模块映射 模块名=>模块（项目）路径
        #'user'=>'/module/user', //在/module/目录下
    ]
];
if (is_file(__DIR__ . '/conf.local.php')) {
    $cfg = array_merge($cfg, require(__DIR__ . '/conf.local.php'));
}