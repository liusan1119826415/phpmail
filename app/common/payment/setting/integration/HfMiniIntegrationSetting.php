<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/5/27
 * Time: 15:23
 */

namespace app\common\payment\setting\integration;

use app\common\payment\setting\BasePaymentSetting;
use app\common\services\payment\IntegrationPay;


class HfMiniIntegrationSetting  extends BasePaymentSetting
{
    public function canUse()
    {
        $paySet =  \Setting::get('payment.integration_pay');

        $payType = explode(',', $paySet['account_info']['pay_type']);

        $type = request()->input('type');
        return $type == 2 && IntegrationPay::pluginSwitch() && $paySet['integration_pay'] &&
            $paySet['MINIAPP_PAY'] &&  ($payType && in_array('MINIAPP_PAY', $payType));
    }
}