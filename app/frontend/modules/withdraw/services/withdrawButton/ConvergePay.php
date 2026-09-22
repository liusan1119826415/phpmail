<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class ConvergePay extends DefaultWithdrawButton
{
    protected $value = 'converge_pay';

    public function isEnabled(): bool
    {
        if (!app('plugins')->isEnabled('converge_pay')) {
            return false;
        }
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 银行卡-HJ';
    }

    public function getOtherName(): string
    {
        return '银行卡-HJ';
    }

    public function getTips(): string
    {
        return "通过审核后将打款到个人银行卡！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-pay_otherpay';
    }

    public function getTitle(): string
    {
        return $this->getName();
    }
}