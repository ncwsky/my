<?php

declare(strict_types=1);

namespace common\sms;

class SmsMailHandle implements SmsMailSendInterface
{
    use \MyMsg;

    const WAYS_ALI_CODE = 'aliCode';

    public $ways = self::WAYS_ALI_CODE;

    public function doSend(string $receive, string $code)
    {
        if ($this->ways == self::WAYS_ALI_CODE) {
            $aliCode = GetC('sms.ali');
            if (empty($aliCode)) {
                throw new \Exception('短信未配置');
            }
            $sms = new SmsAliCode($aliCode['accessKeyId'], $aliCode['accessKeySecret']);
            $smsSendRet = $sms->send($receive, $code, "重庆玩吧");
            if (!$smsSendRet) {
                throw new \Exception(SmsAliCode::err());
            }
        } else {
            throw new \Exception('短信配置无效');
        }
    }
}
