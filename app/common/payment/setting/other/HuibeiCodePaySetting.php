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

class HuibeiCodePaySetting extends BasePaymentSetting
{
	public function canUse()
	{
        $set=\Setting::get('plugin.huibei-pay');
        return app('plugins')->isEnabled('big-screen')&&app('plugins')->isEnabled('huibei-pay')&&$set['is_open']&&$set['is_open_code_pay']&&isset(request()->pc);
	}
}