<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class HuiisWechat extends DefaultWithdrawButton
{
    protected $value = 'huiis_wx';

    public function isEnabled(): bool
    {
        return app('plugins')->isEnabled('cloud-pay-money') && \Setting::get('plugin.cloud-pay-money.is_open');
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 微信-云汇算';
    }

    public function getOtherName(): string
    {
        return '微信-云汇算';
    }

    public function getTips(): string
    {
        return "通过审核后将由工作人员打款到您的微信！";
    }

    public function getIcon(): string
    {
        return 'icon-weixin-yunhuisuan';
    }

    public function getTitle(): string
    {
        return '微信-云汇算';
    }
}