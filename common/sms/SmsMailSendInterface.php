<?php

namespace common\sms;

interface SmsMailSendInterface
{
    /**
     * @param string $receive
     * @param string $code
     * @return void
     * @throws \Exception
     */
    public function doSend(string $receive, string $code);
}
