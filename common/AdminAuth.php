<?php

declare(strict_types=1);

namespace common;

//后台用户验证类
use admin\model\Admin;
use admin\model\Role;
use myphp\Helper;

class AdminAuth extends \myphp\BaseAuth
{
    use \MyMsg;

    /**
     * @var AdminAuth
     */
    private static $instance;
    private $roleRow = [];

    public static function getInstance(): AdminAuth
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public static function getPurview($roleId = 0): string
    {
        return self::getInstance()->roleData($roleId)['purview'] ?? '';
    }

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
     * @param int $roleId //todo 后期考虑多角色处理权限
     * @return array
     */
    public function roleData(int $roleId = 0): array
    {
        $roleId = $roleId ?: (int)session('adminRole');
        if (!isset($this->roleRow[$roleId])) {
            $this->roleRow[$roleId] = Role::fields('rank_val,name,purview')->where(['id' => $roleId])->one();
            if ($this->roleRow[$roleId]) {
                $purview = strtr($this->roleRow[$roleId]['purview'], ';', ',');
                //组合配置的权限设置 角色.角色编号
                if (isset(\myphp::$cfg['role'][$roleId])) {
                    $purview = $purview . ',' . \myphp::$cfg['role'][$roleId];
                }
                $this->roleRow[$roleId]['purview'] = $purview;
            } else {
                $this->roleRow[$roleId] = [
                    'rank_val' => 0,
                    'name' => '-',
                    'purview' => ''
                ];
            }
        }
        return $this->roleRow[$roleId];
    }

    //检测用户是否登陆
    public function adminLogin(): bool
    {
        return (bool)session('adminId');
    }

    public function adminCheck()
    {
        if (!$this->adminLogin()) {
            //$redirect = U(\myphp::$cfg['auth_gateway']);
            $redirect = strpos(\myphp::$cfg['auth_gateway'], 'http') === 0 ? \myphp::$cfg['auth_gateway'] : U(\myphp::$cfg['auth_gateway']);

            throw new \Exception(Helper::outMsg('你未登录,请先登录!', $redirect), 200);
        }
        if (!$this->chkPurview()) {
            //log处理
            toLog(cookie('adminName') . '：' . self::err(), 'adminlogin');
            throw new \Exception(Helper::outMsg(self::err()), 200);
        }
    }
}
