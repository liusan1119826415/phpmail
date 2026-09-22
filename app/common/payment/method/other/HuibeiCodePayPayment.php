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
use app\common\payment\setting\other\HuibeiCodePaySetting;


class HuibeiCodePayPayment extends BasePayment
{
    public $code = 'huibeiCodePay';

    public function __construct(HuibeiCodePaySetting $paymentSetting)
    {
        $this->setSetting($paymentSetting);
    }
}