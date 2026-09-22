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
use app\common\payment\setting\other\HuibeiAliPaySetting;


class HuibeiAliPayPayment extends BasePayment
{
    public $code = 'huibeiAliPay';

    public function __construct(HuibeiAliPaySetting $paymentSetting)
    {
        $this->setSetting($paymentSetting);
    }
}