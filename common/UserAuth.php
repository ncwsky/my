<?php

declare(strict_types=1);

namespace common;

//用户验证类
use myphp\BaseAuth;

class UserAuth extends BaseAuth
{
    use \MyMsg;

    public static $uid = 0;

    //检测用户是否登陆
    public static function isLogin(): bool
    {
        $auth = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            unset($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['HTTP_X_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_X_AUTHORIZATION'];
            unset($_SERVER['HTTP_X_AUTHORIZATION']);
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } elseif (isset($_GET['token'])) {
            $auth = $_GET['token'];
        }
        if (strpos($auth, 'Bearer') === 0) {
            $auth = substr($auth, 7);
        }

        try {
            $jwt = \Firebase\JWT\JWT::decode($auth, new \Firebase\JWT\Key(GetC('jwt_key'), 'HS384'));
            self::$uid = $jwt->uid;
            //session('role', 'test');
        } catch (\Exception $e) {
            #throw new \Exception(toJson(\myphp\Control::fail('登录状态失效', 100)), 200);
            \myphp::send(\myphp\Control::fail('登录状态失效', 100));
            return false;
            #return \myphp::res()->withBody(\myphp\Control::fail('登录状态失效', 100));
        }

        return true;
    }
}
