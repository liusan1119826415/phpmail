<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class HuiisBank extends DefaultWithdrawButton
{
    protected $value = 'huiis_bank';

    public function isEnabled(): bool
    {
        return app('plugins')->isEnabled('cloud-pay-money') && \Setting::get('plugin.cloud-pay-money.is_open');
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 银行卡-云汇算';
    }

    public function getOtherName(): string
    {
        return '银行卡-云汇算';
    }

    public function getTips(): string
    {
        return "通过审核后将由工作人员打款到您的银行卡！";
    }

    public function getIcon(): string
    {
        return 'icon-yinhangka-yunhuisuan';
    }

    public function getTitle(): string
    {
        return '银行卡-云汇算';
    }
}