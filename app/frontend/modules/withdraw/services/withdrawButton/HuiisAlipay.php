<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class HuiisAlipay extends DefaultWithdrawButton
{
    protected $value = 'huiis_ali';

    public function isEnabled(): bool
    {
        return app('plugins')->isEnabled('cloud-pay-money') && \Setting::get('plugin.cloud-pay-money.is_open');
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 支付宝-云汇算';
    }

    public function getOtherName(): string
    {
        return '支付宝-云汇算';
    }

    public function getTips(): string
    {
        return "通过审核后将由工作人员打款到您的支付宝！";
    }

    public function getIcon(): string
    {
        return 'icon-zhifubao-yunhuisuan';
    }

    public function getTitle(): string
    {
        return '支付宝-云汇算';
    }
}