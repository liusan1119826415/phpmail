<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

use app\common\facades\Setting;

class ConsolWithdrawBank extends DefaultWithdrawButton
{
    protected $value = 'consol_withdraw_bank';

    public function isEnabled(): bool
    {
        if (!app('plugins')->isEnabled('consol-withdraw') || !Setting::get('consol-withdraw.set.plugin_enable')) {
            return false;
        }
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 耕耘灵活用工-银行卡';
    }

    public function getOtherName(): string
    {
        return '银行卡-耕耘灵活用工';
    }

    public function getTips(): string
    {
        return "通过审核后将由工作人员打款到您的银行卡！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-pay_otherpay';
    }

    public function getTitle(): string
    {
        return '银行卡-耕耘灵活用工';
    }
}