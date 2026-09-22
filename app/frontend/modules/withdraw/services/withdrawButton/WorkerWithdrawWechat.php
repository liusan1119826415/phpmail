<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class WorkerWithdrawWechat extends DefaultWithdrawButton
{
    protected $value = 'worker_withdraw_wechat';

    public function isEnabled(): bool
    {
        return app('plugins')->isEnabled('worker-withdraw') && \Yunshop\WorkerWithdraw\services\SettingService::usable([],2);
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 微信-好灵工';
    }

    public function getOtherName(): string
    {
        return '微信-好灵工';
    }

    public function getTips(): string
    {
        return "通过审核后将打款到微信-好灵工！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-balance_i';
    }

    public function getTitle(): string
    {
        return '微信-好灵工';
    }
}