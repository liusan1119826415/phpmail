<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022/6/30
 * Time: 15:59
 */

namespace app\common\payment\setting\other;

use app\common\facades\Setting;
use app\common\payment\setting\BasePaymentSetting;

class IcbcWechatSetting extends BasePaymentSetting
{
    public function canUse()
    {
        return request()->input('type') == 2
            && app('plugins')->isEnabled('icbc-pay')
            && Setting::get('icbc-pay.set.plugin_enable');
    }
}