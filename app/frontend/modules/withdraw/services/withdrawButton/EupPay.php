<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class EupPay extends DefaultWithdrawButton
{
    protected $value = 'eup_pay';

    public function isEnabled(): bool
    {
        if (!app('plugins')->isEnabled('eup-pay')) {
            return false;
        }
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 EUP';
    }

    public function getOtherName(): string
    {
        return 'EUP';
    }

    public function getTips(): string
    {
        return "通过审核后将打款到个人银行卡！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-all_alipay';
    }

    public function getTitle(): string
    {
        return $this->getName();
    }
}