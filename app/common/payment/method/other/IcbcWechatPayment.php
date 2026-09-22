<?php

namespace app\common\payment\method\other;

use app\common\payment\method\BasePayment;
use app\common\payment\setting\other\IcbcWechatSetting;

class IcbcWechatPayment extends BasePayment
{
    public $code = 'icbcWechatPay';

    public function __construct(IcbcWechatSetting $paymentSetting)
    {
        $this->setSetting($paymentSetting);
    }
}