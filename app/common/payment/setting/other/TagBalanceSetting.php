<?php

namespace app\common\payment\setting\other;

use app\common\payment\setting\BasePaymentSetting;
use Yunshop\TagBalance\services\SettingService;

class TagBalanceSetting extends BasePaymentSetting
{
    public function canUse()
    {
        return app('plugins')->isEnabled('tag-balance') && SettingService::payOpen();
    }

    public function getName()
    {
        return app('plugins')->isEnabled('tag-balance') ? SettingService::diyName() : '充值标签';
    }
}