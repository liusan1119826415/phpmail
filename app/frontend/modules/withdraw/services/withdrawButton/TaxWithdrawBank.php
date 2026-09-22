<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

use app\common\facades\Setting;

class TaxWithdrawBank extends DefaultWithdrawButton
{
    protected $value = 'tax_withdraw_bank';

    public function isEnabled(): bool
    {
        if (!app('plugins')->isEnabled('tax-withdraw') || !Setting::get('tax-withdraw.set.plugin_enable')) {
            return false;
        }
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 ' . $this->getDiyName() . '-银行卡';
    }

    public function getOtherName(): string
    {
        return $this->getDiyName() . '-银行卡';
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
        return '银行卡-税惠添薪';
    }

    private function getDiyName()
    {
        $div_name = '税惠添薪';
        if (app('plugins')->isEnabled('tax-withdraw')) {
            $div_name = TAX_WITHDRAW_DIY_NAME;
        }
        return $div_name;
    }
}