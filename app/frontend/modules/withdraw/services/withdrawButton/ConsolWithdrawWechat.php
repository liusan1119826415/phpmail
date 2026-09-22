<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

use app\common\facades\Setting;

class ConsolWithdrawWechat extends DefaultWithdrawButton
{
    protected $value = 'consol_withdraw_wechat';

    public function isEnabled(): bool
    {
        if (!app('plugins')->isEnabled('consol-withdraw') || !Setting::get('consol-withdraw.set.plugin_enable')) {
            return false;
        }
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 耕耘灵活用工-微信';
    }

    public function getOtherName(): string
    {
        return '微信-耕耘灵活用工';
    }

    public function getTips(): string
    {
        return "通过审核后将由工作人员打款到您的微信！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-balance_i';
    }

    public function getTitle(): string
    {
        return '微信-耕耘灵活用工';
    }
}