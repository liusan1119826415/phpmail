<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

use app\common\facades\Setting;

class JianzhimaoBank extends DefaultWithdrawButton
{
    protected $value = 'jianzhimao_bank';

    public function isEnabled(): bool
    {
        if (!app('plugins')->isEnabled('jianzhimao-withdraw') || !Setting::get('jianzhimao-withdraw.set.plugin_enable')) {
            return false;
        }
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 兼职猫-银行卡';
    }

    public function getOtherName(): string
    {
        return '兼职猫-银行卡';
    }

    public function getTips(): string
    {
        return "通过审核后将由工作人员打款到您的银行卡！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-balance_f';
    }

    public function getTitle(): string
    {
        return '银行卡-兼职猫';
    }
}