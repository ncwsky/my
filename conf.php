<?php
$cfg = [
    //数据库连接信息
	'db' => array(
		'dbms' => 'mysql', //数据库 驱动类型为pdo时使用
		'server' => '192.168.0.219',	//数据库主机
		'name' => 'yxdj',	//数据库名称
		'user' => 'root',	//数据库用户
		'pwd' => 'root',	//数据库密码
		'port' => 3307,     // 端口
	),
    //城市数据
    'city' => array(
        'dbms' => 'sqlite',
        'name' => __DIR__.'/data.sqlite',	//数据库名称
    ),
    'redis' => array(
        'name' => 'redis',
        'host' => '192.168.0.246',
        'port' => 6379,
        'password'=>'123456',
        'select'=>2, //选择库
    ),
    'session' => [
        //'type'=>'file',
        /*
	    'type'=> 'redis', //redis|file 内置处理 默认系统file
        'prefix' => '_dj_', //用于非file方式的名前缀
        'host' => '192.168.0.246',
        'port' => 6379,
        'password'=>'123456',
        'select'=>2, //选择库*/
        'expire' => 7200, //默认有效期
	],
    'log_level'=>1,
    'desktop_name' => '免费看短剧', //桌标名称
    //'gzip'=>true,
    //微信登录授权url 走第三方授权
    'wx_auth_url'=>'http://ngrok0.tun.guanliyuangong.com/weixin/proxy-auth-login',
    'domain'=>[
        'api'=>'http://192.168.0.219:8051',
        #'api'=>'http://chain.tun.guanliyuangong.com',
        'admin'=>'http://192.168.0.219:8051/admin',
        'agent'=>'http://192.168.0.219:8051/agent',
    ],
    'app_res_path'=>'http://192.168.0.219:8051',
    'frp_key'=> 'Sx#DNVAqi$ZsJUo!yTj+CBfjyhTM2a1ka+pDgcZU3Ftj5&ZMid51O2v*zZ', //frp加密密码
    'encode_key'=> 'Msm-emSqMByDI&rj2cNZY8VXFgMgt7gdw#scva@G$nieIpxA3S_xRiFuHPmk8', //数据编码key
    'jwt_key'=>'Qxa_0UiU@Sx#DNVAqi$ZsJUo!yTj+CBfjyhTM2a1ka+pDgcZU3Ftj5&ZMid51O2v*zZQxy2cZXD3B.Gvcz65JDOE7rSkvBFRhc=',
    'sign_key'=>'3a5c9f27b@Sx#a02ae2f01188f27a5296d5d',
    'rpc_key' =>'yhTM2a#U3Ftj5&ZMid51O2v*zZQxy2cZXD3B',

    'refund_pwd' => '', //总后台退款操作码
    'wx_mini'=>[ //微信小程序（测试号）
        'app_id'=>'wxc22d7e791a439ee6',
        'secret'=>'de0d9d2492057471fcef3362979aa335',
        'response_type' => 'array',
        'log' => [
            'level' => 'error', //'debug',
            'file' => __DIR__.'/log/wechat.log'
        ]
    ],
    'wxp'=>[ //微信公众号（测试号）
        'app_id'=>'wx58749988615e6020',
        'secret'=>'3ef0e261cbb846e44f5fb74e9902bce2',
        'response_type' => 'array',
        'log' => [
            'level' => 'error', //'debug',
            'file' => __DIR__.'/log/wechat.log'
        ]
    ],
    'payment'=>[
        'sqb' => [ //收钱吧
            'vendor_sn' => '', //开发者序列号
            'vendor_key' => '', //开发者密钥
            'code' => '',//商户激活码
            'app_id' => '', //C扫B支付
            'mini_app_id' => '', //小程序支付
        ]
    ],
    //中间件
    'middleware' => [
        //\myphp\middleware\Cors::class,
        \myphp\middleware\Options::class
    ],
    'url_maps'=>[
        '/MP_verify_<echo>.txt'=>'/index/mp_verify',
    ],
    'jzdj'=>[
        'app_id'=>'',
        'secret'=>''
    ]
];
if(is_file(__DIR__.'/conf.local.php')){
    $cfg = array_merge($cfg, require(__DIR__.'/conf.local.php'));
}