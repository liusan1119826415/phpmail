<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

use app\common\facades\Setting;

class ConsolWithdrawAlipay extends DefaultWithdrawButton
{
    protected $value = 'consol_withdraw_alipay';

    public function isEnabled(): bool
    {
        if (!app('plugins')->isEnabled('consol-withdraw') || !Setting::get('consol-withdraw.set.plugin_enable')) {
            return false;
        }
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 耕耘灵活用工-支付宝';
    }

    public function getOtherName(): string
    {
        return '支付宝-耕耘灵活用工';
    }

    public function getTips(): string
    {
        return "通过审核后将由工作人员打款到您的支付宝！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-all_alipay';
    }

    public function getTitle(): string
    {
        return '支付宝-耕耘灵活用工';
    }
}