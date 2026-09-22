<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class EplusWithdrawBank extends DefaultWithdrawButton
{
    protected $value = 'eplus_withdraw_bank';

    public function isEnabled(): bool
    {
        return app('plugins')->isEnabled('eplus-pay') && \Yunshop\EplusPay\services\SettingService::usable();
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 银行卡-智E+';
    }

    public function getOtherName(): string
    {
        return '银行卡-智E+';
    }

    public function getTips(): string
    {
        return "通过审核后将打款到个人银行卡！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-balance_f';
    }

    public function getTitle(): string
    {
        return $this->getName();
    }
}