<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class WorkerWithdrawBank extends DefaultWithdrawButton
{
    protected $value = 'worker_withdraw_bank';

    public function isEnabled(): bool
    {
        return app('plugins')->isEnabled('worker-withdraw') && \Yunshop\WorkerWithdraw\services\SettingService::usable([],1);
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 银行卡-好灵工';
    }

    public function getOtherName(): string
    {
        return '银行卡-好灵工';
    }

    public function getTips(): string
    {
        return "通过审核后将打款到银行卡-好灵工！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-balance_f';
    }

    public function getTitle(): string
    {
        return '银行卡-好灵工';
    }
}