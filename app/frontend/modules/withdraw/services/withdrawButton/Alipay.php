<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class Alipay extends DefaultWithdrawButton
{
    protected $value = 'alipay';

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 支付宝';
    }

    public function getOtherName(): string
    {
        return '支付宝';
    }

    public function getTips(): string
    {
        return "通过审核后将打款到个人支付宝！";
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