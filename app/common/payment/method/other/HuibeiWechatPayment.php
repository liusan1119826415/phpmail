<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022/6/30
 * Time: 15:16
 */
namespace app\common\payment\method\other;

use app\common\payment\method\BasePayment;
use app\common\payment\setting\other\HuibeiWechatSetting;


class HuibeiWechatPayment extends BasePayment
{
    public $code = 'huibeiWechatPay';

    public function __construct(HuibeiWechatSetting $paymentSetting)
    {
        $this->setSetting($paymentSetting);
    }
}