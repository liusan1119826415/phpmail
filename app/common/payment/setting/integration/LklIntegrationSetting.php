<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/1/4
 * Time: 14:46
 */

namespace app\common\payment\setting\integration;

use app\common\payment\setting\BasePaymentSetting;
use app\common\services\payment\IntegrationPay;

class LklIntegrationSetting extends BasePaymentSetting
{
    public function canUse()
    {
        $paySet =  \Setting::get('payment.integration_pay');

        $payType = explode(',', $paySet['account_info']['pay_type']);


        //条件：1、聚合支付；2、支付通道等于拉卡拉
        return  IntegrationPay::pluginSwitch() && $paySet['integration_pay'] &&
            $paySet['account_info']['channel_type'] == 1 && $paySet['lkl_cashier'] &&  ($payType && in_array('CASH', $payType));
    }
}