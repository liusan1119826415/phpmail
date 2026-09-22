<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class WorkerWithdrawAlipay extends DefaultWithdrawButton
{
    protected $value = 'worker_withdraw_alipay';

    public function isEnabled(): bool
    {
        return app('plugins')->isEnabled('worker-withdraw') && \Yunshop\WorkerWithdraw\services\SettingService::usable([],1);
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 支付宝-好灵工';
    }

    public function getOtherName(): string
    {
        return '支付宝-好灵工';
    }

    public function getTips(): string
    {
        return "通过审核后将打款到个人支付宝-好灵工！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-balance_j';
    }

    public function getTitle(): string
    {
        return '支付宝-好灵工';
    }
}