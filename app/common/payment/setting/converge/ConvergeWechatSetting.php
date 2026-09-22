<?php
/**
 * Created by PhpStorm.
 *
 * 
 *
 * Date: 2021/8/26
 * Time: 14:02
 */

namespace app\common\payment\setting\converge;

use app\common\payment\setting\BasePaymentSetting;

class ConvergeWechatSetting extends BasePaymentSetting
{
    public function canUse()
    {
        // 如果类型是app支付. 后台汇聚-> 微信-app+支付 开关控制此处

        return (request()->input('type') != 5 || request()->scope == 'tjpcps')
            && request()->input('type') != 8 // 支付宝登录不显示
            && app('plugins')->isEnabled('converge_pay')
            && \Setting::get('plugin.convergePay_set.wechat.wechat_status')
            && $this->appEnable()
            && $this->cpsEnable()
            && $this->miniEnable();

    }

    public function getWeight()
    {
        return 999;
    }

    //小程序是否显示微信支付
    protected function miniEnable()
    {
        //开启隐藏小程序微信-汇聚支付
        if (request()->input('type') == 2 && \Setting::get('plugin.convergePay_set.wechat.wechat_mini_hide')) {
            return false;
        }

        return true;
    }

    // App 打包
    protected function appEnable()
    {
        $app_enable = true;
        if (request()->input('type') == 7 && !\Setting::get('plugin.convergePay_set.wechat.wechat_card_status')) {
            $app_enable = false;
        }

        return $app_enable;
    }

    // 聚合CPS
    protected function cpsEnable()
    {
        $cps_enable = true;
        if ((request()->input('type') == 15
                || (request()->type == 5 && request()->scope == 'tjpcps')
            )
            && !\Setting::get('plugin.convergePay_set.wechat.wechat_card_status')) {
            $cps_enable = false;
        }

        return $cps_enable;
    }
}