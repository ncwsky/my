<?php

declare(strict_types=1);

namespace common\sms;

class SmsAliCode
{
    use \MyMsg;

    const REQUEST_URL = "https://dysmsapi.aliyuncs.com/";

    private $accessKeyId = "your access key id";
    private $accessKeySecret = "your access key secret";

    public function __construct(string $keyId, string $secret)
    {
        // fixme 必填: 请参阅 https://ak-console.aliyun.com/ 取得您的AK信息
        $this->accessKeyId = $keyId;
        $this->accessKeySecret = $secret;
    }

    /**
     * @param string $mobile
     * @param string $code
     * @param string $sign
     * @param string $msgId
     * @return bool|\stdClass
     */
    public function send(string $mobile, string $code, string $sign, string $msgId = '')
    {
        $params = array();
        // fixme 必填: 短信接收号码
        $params['PhoneNumbers'] = $mobile;

        // fixme 必填: 短信签名，应严格按"签名名称"填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $params['SignName'] = $sign;

        // fixme 必填: 短信模板Code，应严格按"模板CODE"填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $params['TemplateCode'] = "SMS_193524981";

        // fixme 可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
        $params['TemplateParam'] = json_encode(['code' => $code]);
        /*        Array (
                    "code" => "12345",
                    "product" => "阿里通信"
                );*/

        // fixme 可选: 设置发送短信流水号
        $params['OutId'] = $msgId ?: str_replace('.', '', uniqid('', true));

        // fixme 可选: 上行短信扩展码, 扩展码字段控制在7位或以下，无特殊需求用户请忽略此字段
        #$params['SmsUpExtendCode'] = "1234567";

        // 此处可能会抛出异常，注意catch
        return $this->request(
            array_merge($params, [
                "RegionId" => "cn-hangzhou",
                "Action" => "SendSms",
                "Version" => "2017-05-25",
            ])
        );
    }

    private function encode(string $str)
    {
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);
        return $res;
    }

    /**
     * 生成签名并发起请求
     *
     * @param array $params
     * @return bool|\stdClass 返回API接口调用结果，当发生错误时返回false
     */
    public function request(array $params)
    {
        $apiParams = array_merge(array(
            "SignatureMethod" => "HMAC-SHA1",
            "SignatureNonce" => uniqid((string)mt_rand(0, 0xffff), true),
            "SignatureVersion" => "1.0",
            "AccessKeyId" => $this->accessKeyId,
            "Timestamp" => gmdate("Y-m-d\TH:i:s\Z"),
            "Format" => "JSON",
        ), $params);
        ksort($apiParams);

        $sortedQueryStringTmp = "";
        foreach ($apiParams as $key => $value) {
            $sortedQueryStringTmp .= "&" . $this->encode($key) . "=" . $this->encode($value);
        }

        $stringToSign = 'POST&%2F&' . $this->encode(substr($sortedQueryStringTmp, 1));

        $sign = base64_encode(hash_hmac("sha1", $stringToSign, $this->accessKeySecret . "&", true));

        $signature = $this->encode($sign);

        try {
            $content = \Http::doPost(self::REQUEST_URL, "Signature={$signature}{$sortedQueryStringTmp}");
        } catch (\Exception $e) {
            return self::err($e->getMessage());
        }
        $ret = json_decode($content, true);
        if ($ret['Code'] != 'OK') {
            return self::err($ret['Message']);
        }
        return $ret;
    }
}