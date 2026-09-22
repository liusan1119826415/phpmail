<?php

namespace app\common\payment\setting\other;

use app\common\facades\Setting;
use app\common\payment\setting\BasePaymentSetting;

class MerchantLoanPaySetting extends BasePaymentSetting
{
    public function canUse()
    {
        return app('plugins')->isEnabled('merchant-loan-pay')
            && Setting::get('merchant-loan-pay.set.plugin_enable');
    }
}