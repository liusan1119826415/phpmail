<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class SliverPoint extends DefaultWithdrawButton
{
    protected $value = 'silver_point';

    public function isEnabled(): bool
    {
        return app('plugins')->isEnabled('silver-point-pay');
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 银典支付';
    }

    public function getOtherName(): string
    {
        return '银典支付';
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
        return '银行卡-银典支付';
    }
}