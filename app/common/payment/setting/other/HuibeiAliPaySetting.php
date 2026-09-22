<?php
/**
 * Created by PhpStorm.
 *
 * 
 *
 * Date: 2021/8/26
 * Time: 14:02
 */

namespace app\common\payment\setting\other;


use app\common\payment\setting\BasePaymentSetting;

class HuibeiAliPaySetting extends BasePaymentSetting
{
	public function canUse()
	{
        $set=\Setting::get('plugin.huibei-pay');
        return app('plugins')->isEnabled('huibei-pay')&&$set['is_open']&&$set['is_open_alipay_pay']&&in_array(request()->type,[1,5]);
	}
}