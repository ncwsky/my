<?php
namespace common;

use EasyWeChat\Factory;
use EasyWeChat\Kernel\Support\Collection;
use GuzzleHttp\Exception\GuzzleException;
use myphp\Helper;
use Psr\Http\Message\ResponseInterface;

class Weixin
{
    use \MyMsg;
    //openid for work
    public const OP_DEV = 'o8E801u7dC30OOOjGEwO7fE-EOmM';

    #后台非公司网络登陆时微信验证
    public static $admOuterNetWxAuth = [self::OP_DEV];

    public const WX_CFG = 'wxp';

    /**
     * @var \EasyWeChat\OfficialAccount\Application[]
     */
    public static $app = [];
    public static $wxAsync = true; //微信异步通知 默认异步

    /**
     * @param string $name
     * @return \EasyWeChat\OfficialAccount\Application
     */
    public static function getApp(string $name = self::WX_CFG)
    {
        if (!isset(self::$app[$name])) {
            self::$app[$name] = Factory::officialAccount(GetC($name));
        }
        return self::$app[$name];
    }

    public static function clog($msg)
    {
        Helper::toFileLog(__DIR__ . '/../log/Weixin-' . date("Ym") . '.log', $msg);
    }

    /**
     * 通用模板消息发送
     * @param string $openid
     * @param string $template_id
     * @param array $sendData
     * @param string $url
     * @param string $cfg
     * @return mixed
     */
    public static function pushCommon(string $openid, string $template_id, array $sendData, string $url = '', string $cfg = self::WX_CFG)
    {
        try {
            $app = static::getApp($cfg);
            $data = [
                'touser' => $openid,
                'template_id' => $template_id,
                'data' => $sendData,
            ];
            if ($url) {
                $data['url'] = $url;
            }
            return $app->template_message->send($data); //response_type 受控制
        } catch (\Exception $e) {
            static::clog('file:' . $e->getFile() . 'line:' . $e->getLine() . ',code:' . $e->getCode() . ',msg:' . $e->getMessage());
            return null;
        }
    }

    /**
     * 公众号发送一次性订阅消息
     * @param string $openid
     * @param string $template_id
     * @param array $sendData
     * @param int $scene
     * @param string $url
     * @param string $cfg
     * @return mixed
     */
    public static function mpSubscribe(string $openid, string $template_id, array $sendData, int $scene = 0, string $url = '', string $cfg = self::WX_CFG)
    {
        try {
            $app = static::getApp($cfg);
            $data = [
                'touser' => $openid,
                'template_id' => $template_id,
                'scene' => $scene,
                'data' => $sendData,
            ];
            if ($url) {
                $data['url'] = $url;
            }
            return $app->template_message->sendSubscription($data); //response_type 受控制
        } catch (\Exception $e) {
            static::clog('file:' . $e->getFile() . 'line:' . $e->getLine() . ',code:' . $e->getCode() . ',msg:' . $e->getMessage());
            return null;
        }
    }

    /**
     * 小程序发送订阅消息
     * @param string $openid
     * @param string $template_id
     * @param array $sendData
     * @param string $page
     * @param string $cfg
     * @return mixed
     */
    public static function miniSubscribe(string $openid, string $template_id, array $sendData, string $page = '', string $cfg = self::WX_CFG)
    {
        try {
            $app = static::getApp($cfg);
            $data = [
                'touser' => $openid,
                'template_id' => $template_id,
                'data' => $sendData,
            ];
            if ($page) {
                $data['page'] = $page;
            }
            return $app->subscribe_message->send($data); //response_type 受控制
        } catch (\Exception $e) {
            static::clog('file:' . $e->getFile() . 'line:' . $e->getLine() . ',code:' . $e->getCode() . ',msg:' . $e->getMessage());
            return null;
        }
    }

    /**
     * 生成临时二维码
     * @param int|string $sceneVal int[32位非0整型], string[1-64]
     * @param int $time 最长30【2592000】天
     * @param string $cfg
     * @return array|bool
     */
    public static function qr($sceneVal, int $time = 610, string $cfg = self::WX_CFG)
    {
        $app = static::getApp($cfg);
        try {
            $qrRet = $app->qrcode->temporary($sceneVal, $time);
            if (!isset($qrRet["url"])) {
                throw new \Exception(toJson($qrRet));
            }
            return [
                "url" => $qrRet["url"],
                "imageUrl" => $app->qrcode->url($qrRet["ticket"]),
            ];

            #return $qrRet; //[ticket,expire_seconds,url]
        } catch (\Exception $e) {
            self::clog('创建二维码失败：' . $e->getMessage());
            return self::err('创建二维码失败：' . $e->getMessage());
        }
        #return $app->qrcode->temporary($sceneVal, $time);
    }

    /**
     * 生成永久二维码
     * @param int|string $sceneVal int[1-100000], string[1-64]
     * @param string $cfg
     * @return array|\EasyWeChat\Kernel\Support\Collection|object|\Psr\Http\Message\ResponseInterface|string
     */
    public static function foreverQr($sceneVal, string $cfg = self::WX_CFG)
    {
        $app = static::getApp($cfg);
        try {
            $qrRet = $app->qrcode->forever($sceneVal); //[ticket,url]
            if (!isset($qrRet["url"])) {
                throw new \Exception(toJson($qrRet));
            }
            return [
                "url" => $qrRet["url"],
                "imageUrl" => $app->qrcode->url($qrRet["ticket"]),
            ];
            #return $qrRet;
        } catch (\Exception $e) {
            return self::err('创建二维码失败：' . $e->getMessage());
        }
        #return $app->qrcode->forever($sceneVal); //[ticket,url]
    }

    public const AES_KEY = 'fEhrTmbbQI';

    //用于请求识别有效性
    public static function enAesJson($data, $expiry = 0)
    {
        if (!is_scalar($data)) {
            $data = toJson($data);
        }
        return base64_encode(\AES::encode(sprintf('%010d', $expiry ? $expiry + time() : 0) . $data, self::AES_KEY));
    }

    public static function deAesJson($data)
    {
        $data = \AES::decode(base64_decode($data), self::AES_KEY);
        $time = (int)substr($data, 0, 10);
        if ($time == 0 || $time - time() > 0) {
            return json_decode(substr($data, 10), true);
        }
        return null;
    }

    //微信消息提醒
    public static function notice($title, $msg, $remark = '', $openid = [self::OP_DEV])
    {
        $apiUrl = 'https://api.guanliyuangong.com/default/notice';
        $data = [
            'openid' => is_array($openid) ? implode(',', $openid) : $openid,
            'title' => $title,
            'msg' => $msg,
            'remark' => $remark,
        ];
        return \Http::doPost($apiUrl, base64_encode(Helper::rc4(Helper::toJson($data), GetC('rc4_key'))), 10, ['Accept:*/*']);
    }
}
