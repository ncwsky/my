<?php

declare(strict_types=1);

/**
 * @param string $name
 * @return lib_redis
 */
function redis(string $name = 'redis'): lib_redis
{
    return myphp::redis($name);
}

/**
 * @return \myphp\cache\File
 */
function getCache(): \myphp\cache\File
{
    //return \myphp\Cache::getInstance('file', GetC('cache_option'));
    static $cache;
    if (!$cache) {
        $cache = new \myphp\cache\File();
        //$cache->setCacheDir(RUNTIME . '/cache');
        //$cache->setCachePrefix('_');
    }
    return $cache;
}

//循环日期记录
function cLog(string $name = 'clog')
{
    static $log;
    if (!isset($log[$name])) {
        $log[$name] = new RotateLog(RUNTIME . '/' . $name . '.log'); //, RotateLog::MODE_FIXED, 0.5*1024*1024
    }
    return $log[$name];
}

/**
 * 重试失败IP限制
//检测错误次数
if (!retryErrLimitIp('标识', false, $errMsg)) {
    return self::fail($errMsg);
}
//记录错误次数
retryErrLimitIp('标识', true, $errMsg);
//清除错误次数
retryErrLimitIp('标识', null);
 *
 * @param string $name 标识
 * @param bool|null $record 是否记录错误次数
 * @param string|null $errMsg 错误内容
 * @param int $allowTimes
 * @param int $limitMinute
 * @return bool
 */
function retryErrLimitIp(string $name, ?bool $record = true, ?string &$errMsg = '', int $allowTimes = 6, int $limitMinute = 10): bool
{
    //登陆限制 通过ip
    $ip = \myphp\Helper::getIp();
    #$allowTimes = 6;
    #$limitMinute = 10;
    //兼容处理 未配置redis时使用文件缓存处理
    $cache = GetC('redis') ? redis() : getCache();
    $retryLimitKey = '_retry.' . $name . '.Limit:' . $ip;
    if ($record === null) {
        $cache->del($retryLimitKey); //清除
        return true;
    }
    $errTimes = (int)$cache->get($retryLimitKey);
    if ($record) {
        $cache->incr($retryLimitKey);
        $cache->expire($retryLimitKey, $limitMinute * 60);
        $errMsg = "还可以重试" . ($allowTimes - $errTimes - 1) . "次";
    } else {
        if ($errTimes >= $allowTimes) {
            $limitMinute = ceil(intval($cache->ttl($retryLimitKey)) / 60);
            $errMsg = "错误次数超过" . $allowTimes . "次，请" . $limitMinute . "分钟后重试！";
            return false;
        }
    }
    return true;
}
/**
 * url组装
 * @param string $name
 * @param string $url
 * @param array $params
 * @return string
 */
function buildUrl(string $name, string $url, array $params = []): string
{
    $url = GetC('domain.'.$name) . '/' . ltrim($url, '/');
    if ($params) {
        $url .= (strpos($url, '?') ? '&' : '?') . http_build_query($params);
    }
    return $url;
}

function apiUrl(string $url, array $params = []): string
{
    return buildUrl('api', $url, $params);
}

function adminUrl(string $url, array $params = []): string
{
    return buildUrl('admin', $url, $params);
}

function toLog($msg, $tag = 'info')
{
    \myphp\Log::write($msg, $tag);
}

/**
 * @param string $func cli.php下的方法名|my xx/xx
 * @param array|string|null $params 参数，使用空格分隔
 * @param int $time 延时指定执行时间
 */
function queueCli(string $func, $params = null, int $time = 0)
{
    $data = toJson([$func, $params]);
    if ($time > time()) {
        redis()->zAdd('__queueZ', $time, $data);
    } else {
        redis()->rpush('__queue', $data); //将值推入到尾部
    }
}

/**
 * 生成密码散列值 60个字符
 * @param string $val
 * @param int $cost 建议 10-13 过大会执行过慢
 * @return string
 */
function pwd(string $val, int $cost = 10)
{
    $options = [
        'cost' => $cost,
    ];
    return password_hash($val, PASSWORD_BCRYPT, $options);
}

//生成订单号
function orderSN(int $prefix = 0, string $s = '1'): string
{
    //$chars = substr(microtime(),2,6);
    $sn = date('ymdHis'). str_pad((string)rand(0, 99), 2, '0', STR_PAD_LEFT) .substr(microtime(), 2, 4); //18位
    return $s . str_pad((string)$prefix, 5, '0', STR_PAD_LEFT) . $sn; //24位
}

/**
 * 计算年龄
 * @param string $birth
 * @param DateTime|null $now
 * @return int
 * @throws Exception
 */
function getAge(string $birth, DateTime $now = null): int
{
    if ($now === null) {
        $now = new \DateTime();
    }
    $ori = new \DateTime($birth);
    $interval = $ori->diff($now);
    return $interval->y;
}

function getProvince($code)
{
    static $province = [];
    if (isset($province[$code])) {
        return $province[$code];
    }
    $name = db('city')->table('province')->where(['code' => $code])->val('name');
    if (!$name) {
        return '';
    }
    $province[$code] = $name;
    return $province[$code];
}

function getCity($code)
{
    static $city = [];
    if (isset($city[$code])) {
        return $city[$code];
    }
    $name = db('city')->table('city')->where(['code' => $code])->val('name');
    if (!$name) {
        return '';
    }
    $city[$code] = $name;
    return $city[$code];
}

function getArea($code)
{
    static $area = [];
    if (isset($area[$code])) {
        return $area[$code];
    }
    $name = db('city')->table('area')->where(['code' => $code])->val('name');
    if (!$name) {
        return '';
    }
    $area[$code] = $name;
    return $area[$code];
}

/**
 * @param string $video
 * @param string $img
 * @param int $offset 起始位置 秒
 * @param int $q 质量 越低越好  JPEG的有效范围是2-31
 * @param string $size 输出大小 min(600\,iw):-1 最大宽度600高等比
 * @return bool
 */
function video2img(string $video, string $img, int $offset = -1, int $q = 5, string $size = ''): bool
{
    if ($offset == -1) {
        $offset = mt_rand(0, 10);
    } //随机起始秒
    if (!is_file($video)) {
        myphp::err('No such file');
        return false;
    }
    if ($size) {
        $size = '-vf scale="'.$size.'"';
    }
    $cmd = "ffmpeg -y -i $video -ss $offset -r 1 -v 16 -q:v $q $size -frames:v 1 -f image2 $img";
    $descriptorspec = [
        ["pipe", "r"],  // 标准输入，子进程从此管道中读取数据
        ["pipe", "w"],  // 标准输出，子进程向此管道中写入数据
        ["pipe", "w"] // 标准错误，写入到一个文件
    ];
    $process = proc_open($cmd, $descriptorspec, $pipes); //, $cwd
    if ($process) {
        //$in = trim(stream_get_contents($pipes[0]));
        fclose($pipes[0]);
        $out = trim(stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        // 切记：在调用 proc_close 之前关闭所有的管道以避免死锁。
        $return_value = proc_close($process);
        //var_dump($err, $out, $return_value);
        if ($err) {
            myphp::err($err);
            return false;
        }
    } else {
        myphp::err('命令管道创建失败');
        return false;
    }
    return true;
}

/**
 * 按id以1000为基准生成目录
 * @param int $id
 * @param string $dir 指定后缀目录
 * @param int $base
 * @return string
 */
function idDir(int $id, string $dir = '', int $base = 1000): string
{
    return intval($id / $base) . '/' . $id . '/' . ($dir != '' ? trim($dir, '/') . '/' : '');
}
/**
 * 按id以1000为基准生成目录
 * @param int $id
 * @param string $dir 指定后缀目录
 * @param int $base
 * @return string
 */
function avgDir(int $id, string $dir = '', int $base = 1000): string
{
    return strval($id % $base) . '/' . strval($id) . '/' . ($dir !== '' ? trim($dir, '/') . '/' : '');
}

//是否支付宝
function isAlipay(): bool
{
    if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false) {
        return true;
    }
    return false;
}

//10秒内限制5次请求限制
function ipReqLimit($name, $num = 5, $sec = 10)
{
    if (isset($_SERVER['HTTP_ALI_CDN_REAL_IP'])) { #阿里cdn
        $ip = $_SERVER['HTTP_ALI_CDN_REAL_IP'];
    } elseif (isset($_SERVER['HTTP_X_TENCENT_UA']) && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    $redis = GetC('redis') ? redis() : getCache(); //兼容处理 未配置redis时使用文件锁定处理
    if (!$redis->lockOnce($name . ':' . $ip, $sec)) {
        $n = $redis->get($name . ':' . $ip);
        if ($n > $num) {
            return false;
        }
    }
    return true;
}
//加锁 解锁 主要用于保证并发时操作的原子性 会阻塞
function redisLock(string $lockKey, int $lockTimeout = 10)
{
    $redis = GetC('redis') ? redis() : getCache(); //兼容处理 未配置redis时使用文件锁定处理
    return $redis->lockBlock($lockKey, $lockTimeout);
}
//加锁 解锁 主要用于判断是否重复操作
function redisLockOnce(string $lockKey, int $lockTimeout = 10, lib_redis $redis = null): bool
{
    if (!$redis) {
        $redis = GetC('redis') ? redis() : getCache(); //兼容处理 未配置redis时使用文件锁定处理
    }
    return $redis->lockOnce($lockKey, $lockTimeout);
}

/**
 * 消息id 用于重复处理限制
 * @param string $key
 * @return int
 */
function msgId(string $key = ''): int
{
    if ($key == '') {
        $key = '_kh_msg_id';
    }
    $redis = GetC('redis') ? redis() : getCache();
    $message_id = $redis->incr($key);
    if ($message_id >= 0xFFFFA000) { #0xFFFFFFFF 留点余量缓冲
        $redis->set($key, 0);
    }
    return $message_id;
}

//对外接口通用签名 对所有待签名参数按照字段名的ASCII 码从小到大排序（字典序）后，使用 URL 键值对的格式（即key1=value1&key2=value2…）拼接成字符串string1
function openSign(string $key, string $name = 'sign'): bool
{
    $data = \myphp\Helper::isPost() ? $_POST : $_GET;
    if (!isset($data[$name])) {
        return false;
    }
    unset($data['c'], $data['a']);
    $sign = $data[$name];
    $md5 = getSign($key, $data, $name);
    if ($md5 == $sign) {
        return true;
    }
    toLog(sprintf("%s+%s -> md5:%s", http_build_query($data), $key, $md5) . ' != ' . $sign, 'openSign');
    return false;
}

//对外接口通用签名
function getSign(string $key, array $data = [], string $name = 'sign'): string
{
    unset($data[$name]);
    ksort($data); //键名升序
    $string = '';
    foreach ($data as $k => $v) {
        if ($string) {
            $string .= '&';
        }
        $string .= $k . '=' . $v;
    }
    return md5(sprintf("%s+%s", $string, $key));
}

/**
 * 临时上传文件转为正常上传文件
 * @param int $uid
 * @param string $pic
 * @param string $dir
 * @param bool $isTmp 是不是临时文件
 * @return string
 */
function tmp2file(int $uid, string $pic, string $dir = '', bool &$isTmp = false): string
{
    $isTmp = false;
    if (strpos($pic, '/tmp/') !== false) { //临时文件 移动
        $root = __DIR__ . '/../web';
        if (!file_exists($root . $pic)) { //文件不存在
            return '';
        }
        $isTmp = true;
        $new_dir = '/up/' . avgDir($uid, $dir);
        if (!is_dir($root . $new_dir)) {
            mkdir($root . $new_dir, 0755, true);
        }
        $new_pic = str_replace('/tmp/', $new_dir, $pic);
        $ret = rename($root . $pic, $root . $new_pic);
        toLog('tmpUid2file rename ' . toJson($ret) . ' ' . $pic . ' -> ' . $new_pic);
        $pic = $new_pic;
    }
    return $pic;
}

/**
 * 临时上传文件转为正常上传文件
 * @param string $pic
 * @param string $new_dir '/up/' . avgDir($uid, $dir)
 * @param bool $isTmp 是不是临时文件
 */
function tmpToFile(string &$pic, string $new_dir, bool &$isTmp = false)
{
    $isTmp = false;
    if (strpos($pic, '/tmp/') === false) return;
    //临时文件 移动
    $root = __DIR__ . '/../web';
    if (!file_exists($root . $pic)) { //文件不存在
        $pic = '';
        return;
    }
    $isTmp = true;
    if (!is_dir($root . $new_dir)) {
        mkdir($root . $new_dir, 0755, true);
    }
    $new_pic = str_replace('/tmp/', $new_dir, $pic);
    $ret = rename($root . $pic, $root . $new_pic);
    toLog('tmpToFile rename ' . toJson($ret) . ' ' . $pic . ' -> ' . $new_pic);
    $pic = $new_pic;
}

/**
 * 生成不限制的小程序码
 * @param string $path
 * @param string $scene
 * @param string $val
 * @param string $qrFile
 * @param string $cfgName
 * @return array|bool|string
 * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
 * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
 */
function miniQrCode(string $path, string $scene, string $val = '', string $qrFile = '', string $cfgName = 'wx_mini')
{
    if (!$scene || preg_match("/[^\w!#$&'()*+,\/:;=?@\-._~]/", $scene)) {
        return \myphp::err('参数为空或格式无效');
    }
    //缓存数据
    if ($val !== '') {
        redis()->setex('qr:' . $scene, 600, $val);
    }

    $app = \EasyWeChat\Factory::miniProgram(GetC($cfgName));
    $env_version = $_GET['env_ver'] ?? ''; //指定版本  release, trial, develop
    $options = [
        'page' => $path,
        'width' => 220,
    ];
    if ($env_version) {
        $options['env_version'] = $env_version;
    }
    $response = $app->app_code->getUnlimit($scene, $options);
    $base64 = 1;
    // 保存小程序码 文件|base64
    if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
        if ($qrFile) {
            $filename = basename($qrFile);
            $response->saveAs(SITE_WEB . dirname($qrFile), $filename);
            $base64 = 0;
        } else {
            $qrFile = base64_encode($response->getBody()->getContents());
        }
    } else {
        toLog($response, 'qrFail');
        return \myphp::err('小程序二维获取失败:' . $response['errmsg']);
    }
    return ['scene' => $scene, 'qr' => $qrFile, 'base64' => $base64];
}

// 跳转提示信息输出: ([0,1]:)信息标题, url, 辅助信息, 等待时间（秒） 用于前端自动定义信息输出模板
function outMsg(string $msg, string $url = '', string $info = '', int $time = 1)
{
    $is_url = false;
    if ($url == '') {
        $jumpUrl = 'javascript:close_win()';
        $js = 'close_win()';
    } elseif (substr($url, 0, 11) == 'javascript:') {
        $jumpUrl = $url;
        $js = substr($url, 11);
    } else {
        $jumpUrl = $url;
        $js = "window.location='$jumpUrl'";
        $is_url = true;
    }
    $ok = substr($msg, 0, 2); //提示状态 默认为普通
    $code = 0;
    $flag = 'normal'; //普通提示
    if ($ok == '1:' || $ok == '0:') {
        $msg = substr($msg, 2);
        if ($ok == '0:') {
            $code = 1;
            $flag = 'error'; //错误提示
        } else {
            $flag = 'success'; //成功提示
        }
    }

    if (ob_get_length() !== false) {
        ob_clean();
    }//清除页面
    if (\myphp\Helper::isAjax()) { //ajax输出
        $data = ['info' => $info, 'time' => $time];
        if ($is_url) {
            $data['_url'] = $jumpUrl;
        }
        $json = ['code' => $code, 'msg' => $msg, 'data' => $data];
        if (IS_CLI) {
            \myphp::conType('application/json');
            return \myphp\Helper::toJson($json);
        }
        exit(\myphp\Helper::toJson($json));
    }

    $out_html = '<!doctype html><html><head><meta charset="utf-8"><title>'.($url != 'nil' ? '跳转提示' : '信息提示').'</title><style type="text/css">*{padding:0;margin:0}body{background:#fff;font-family:"Microsoft YaHei";color:#333;font-size:100%}.system-message{padding:1.5em 3em}.system-message h1{font-size:6.25em;font-weight:400;line-height:120%;margin-bottom:.12em}.system-message .jump{padding-top:.625em}.system-message .jump a{color:#333}.system-message .success{color:#207E05}.system-message .error{color:#da0404}.system-message .normal,.system-message .success,.system-message .error{line-height:1.8em;font-size:2.25em}.system-message .detail{font-size:1.2em;line-height:160%;margin-top:.8em}</style><script src='.ROOT_DIR.'"/static/js/comm.js"></script></head><body><div class="system-message">';

    $out_html .= '<h1>'. ($code ? ':(' : ':)').'</h1><p class="'.$flag.'">'.$msg.'</p>'; //输出

    $out_html .= $info != '' ? '<p class="detail">'.$info.'</p>' : '';
    if ($url != 'nil') { //提示不跳转
        $out_html .= '<p class="jump">页面自动 <a id="href" href="'.$jumpUrl.'">跳转</a>  等待时间： <b id="time">'.$time.'</b></p></div><script type="text/javascript">var pgo=0,t=setInterval(function(){var time=document.getElementById("time");var val=parseInt(time.innerHTML)-1;time.innerHTML=val;if(val<=0){clearInterval(t);if(pgo==0){pgo=1;'.$js.';}}},1000);</script></body></html>';
    }
    if (IS_CLI) {
        \myphp::conType('text/html');
        return $out_html;
    }
    exit($out_html);
}

/**
 * 解析域名获取ip
 * @param string $domain
 * @return mixed|string
 */
function dns2ip(string $domain)
{
    return dns_get_record($domain, DNS_A)[0]['ip'] ?? '';
}

/**
 * 限定指定外网ip或是内网ip判定
 * @param string|\Closure $domain
 * @param string $lan
 * @return bool
 */
function inLanIp($domain, string $lan = '192.168.0.'): bool
{
    if (is_string($domain)) {
        $ip = dns2ip($domain);
    } else {
        $ip = $domain instanceof \Closure ? call_user_func($domain) : '0.0.0.0';
    }
    if (!empty($_SERVER['IS_CLI_RUN'])) { //是脚本执行不拦截
        return true;
    }

    $remoteIp = '0.0.0.1';
    //toLog($_SERVER, 'srv');
    if (isset($_SERVER['HTTP_ALI_CDN_REAL_IP'])) { #阿里cdn
        $remoteIp = $_SERVER['HTTP_ALI_CDN_REAL_IP'];
    } elseif (isset($_SERVER['HTTP_X_TENCENT_UA']) && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) { //腾讯
        $remoteIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $remoteIp = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $remoteIp = $_SERVER['REMOTE_ADDR'];
    }
    if (strpos($remoteIp, $lan) === 0) {
        return true;
    } //内网
    //toLog($ip.'---'.$remoteIp, 'ip');
    return in_array($remoteIp, (array)$ip);
}

/**
 * 过滤表情符
 * @param string $str
 * @return string|null
 */
function filterEmoji(string $str)
{
    return preg_replace_callback('/./u', function ($match) {return strlen($match[0]) >= 4 ? '' : $match[0];}, $str);
}
/**
 * @param int $start 运行时间开始标识
 * @param string $flag 标识名
 * @return mixed
 */
function runtime(int $start = 0, string $flag = '')
{
    static $flags, $time;
    if ($start) {
        if ($flag) {
            if (!isset($time['__all'])) {
                return 0;
            }
            $time[$flag] = microtime(true);
        } else { //初始
            $time = ['__all' => microtime(true)];
            $flags = [];
        }
        return 0;
    } elseif (!isset($flags)) {
        return 0;
    }
    if ($flag) {
        if (!isset($time['__all'])) {
            return 0;
        }
        $run = number_format(microtime(true) - $time['__all'], 4);
        // [self, run]
        $flags[$flag] = isset($time[$flag]) ? [number_format(microtime(true) - $time[$flag], 4), $run] : $run;
        return $flags[$flag];
    }
    $flags['__all'] = number_format(microtime(true) - $time['__all'], 4);
    return $flags;
}

/**
 * 记录消耗时间
 * @param int $start 非0耗时起始 0计算耗时
 * @return int|string
 */
function costTime(int $start = 0)
{
    static $time;
    if ($start) {
        $time = microtime(true);
        return 0;
    } else {
        return number_format(microtime(true) - $time, 4);
    }
}
