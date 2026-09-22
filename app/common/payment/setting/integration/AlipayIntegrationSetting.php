<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/13
 * Time: 11:06
 */

namespace app\common\payment\setting\integration;

use app\common\payment\setting\BasePaymentSetting;
use app\common\services\payment\IntegrationPay;


class AlipayIntegrationSetting extends BasePaymentSetting
{
    public function canUse()
    {
        $paySet =  \Setting::get('payment.integration_pay');

        $payType = explode(',', $paySet['account_info']['pay_type']);

        $type = request()->input('type');
        return $type != 2 && IntegrationPay::pluginSwitch() && $paySet['integration_pay'] &&
           $paySet['alipay_pay'] &&  ($payType && in_array('ALIPAY', $payType));
    }
}
