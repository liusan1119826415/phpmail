<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/11/13
 * Time: 11:07
 */

namespace app\common\payment\setting\integration;

use app\common\payment\setting\BasePaymentSetting;
use app\common\services\payment\IntegrationPay;

class WechatIntegrationSetting extends BasePaymentSetting
{
    public function canUse()
    {
        $paySet =  \Setting::get('payment.integration_pay');

        $payType = explode(',', $paySet['account_info']['pay_type']);

        $type = request()->input('type');
        
        return in_array($type, [1,2]) && IntegrationPay::pluginSwitch() && $paySet['integration_pay']
            && $paySet['wechat_pay'] && ($payType && in_array('WECHAT', $payType)) && $this->miniEnable($paySet);
    }

    //小程序是否显示微信支付
    protected function miniEnable($paySet)
    {
        if (request()->input('type') == 2 && $paySet['hide_wechat_pay']) {
            return false;
        }

        return true;
    }
}
