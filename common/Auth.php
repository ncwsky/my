<?php

namespace common;

//后台用户验证类
use common\model\Admin;
use myphp\Helper;

class Auth
{
    use \MyMsg;

    private $roleRow = [];

    public function __construct()
    {
        if (!empty($_SERVER['IS_CLI_RUN'])) { //脚本执行
            $uid = isset($_GET['_uid']) ? (int)$_GET['_uid'] : 0;
            unset($_GET['_uid']);
            if ($uid) {
                $admin = Admin::create()->where(['id' => $uid])->one();
                $user = $admin['username'];
                //用户
                cookie('adminName', $user);
                //session
                session('adminRole', $admin['role']);
                session('adminId', $admin['id']);
            }
        }
    }

    /**
     * 获取指定角色的权限数据
     * @param int $roleId
     * @return mixed
     * @throws \Exception
     */
    public function roleData($roleId = 0)
    {
        $roleId = $roleId ?: (int)session('adminRole');
        if (!isset($this->roleRow[$roleId])) {
            $this->roleRow[$roleId] = db()->getOne('select id,rank,name,purview from `role` where `id`=' . $roleId);
        }
        return $this->roleRow[$roleId];
    }

    /**
     * 权限验证
     * @param string|null $control 单独调用时设置的控制器名称
     * @param string|null $action 设置为空字符时仅验证控制器
     * @param array|null $get 附加参数的权限验证
     * @return bool
     * @throws \Exception
     */
    public function chkPurview($control=null, $action=null, $get=null)
    {
        $roleId = (int)session('adminRole');
        $row = $this->roleData($roleId);
        if (empty($row)) {
            return self::err('用户所属角色无效!');
        }

        $control===null && cookie('adminRoleName', $row['name']); //角色名
        $purview = json_decode($row['purview'], true);
        //组合配置的权限设置 角色.角色编号
        if (isset(\myphp::$cfg['role'][$row['id']])) {
            $purview = array_merge($purview, \myphp::$cfg['role'][$row['id']]);
        }

        if (!is_array($purview)) {
            return self::err('用户所属角色没有权限记录信息!');
        }
        //超级管理员
        if (isset($purview['isadmin'])) {
            return true;
        }
        if ($control === null) $control = strtolower(\myphp::$env['c']);    //获得控制器名
        if (!isset($purview[$control])) {
            return self::err('用户所属角色没有' . $control . '的权限!');
        }
        if ($purview[$control] === 1) { #拥有此模块所有权限
            return true;
        }
        if ($action === null) {
            $action = strtolower(\myphp::$env['a']); //获得方法名
        } elseif ($action === '') { //自定义传值 仅验证控制器
            return true;
        }

        if (!isset($purview[$control][$action])) {
            return self::err('用户所属角色没有' . $control . '/' . $action . '操作的权限!');
        }

        $err = '用户所属角色没有' . $control . '/' . $action . '操作的权限!';
        if ($purview[$control][$action] == '!') { //支持 参数值为“!”表示没有此权限
            return self::err($err);
        }
        //指定参数的权限验证 只支持一个参数的验证  一般是在自定权限中才会设置
        if ($purview[$control][$action] !== 1) { // hy=1 或 hy-1
            $para = strtr($purview[$control][$action], '-', '=');
            if (strpos($para, '=')) {
                list($_k, $_v) = explode('=', $para);
                if ($get === null) {
                    if (isset($_GET[$_k]) && $_GET[$_k] == $_v) return true;
                } else {
                    if (isset($get[$_k]) && $get[$_k] == $_v) return true;
                }
            }
            return self::err($err);
        }
        return true;
    }

    //检测用户是否登陆
    public function adminLogin()
    {
        return session('adminId');
    }

    public function adminCheck()
    {
        if (!$this->adminLogin()) {
            $redirect = \myphp::$cfg['auth_gateway'];
            throw new \Exception(Helper::outMsg('你未登录,请先登录!', $redirect), 200);
        }
        if (!$this->chkPurview()) {
            //log处理
            toLog(cookie('adminName') . '：' . self::err(), 'adminlogin');
            throw new \Exception(Helper::outMsg(self::err()), 200);
        }
    }
}