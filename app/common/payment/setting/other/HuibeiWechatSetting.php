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

class HuibeiWechatSetting extends BasePaymentSetting
{
	public function canUse()
	{
	    $set=\Setting::get('plugin.huibei-pay');
        return app('plugins')->isEnabled('huibei-pay')&&$set['is_open']&&$set['is_open_wechat']&&in_array(request()->input('type'), [1,2]);
	}
}