<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

use app\common\facades\Setting;

class Wechat extends DefaultWithdrawButton
{
    protected $value = 'wechat';

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 微信';
    }

    public function getOtherName(): string
    {
        return '微信';
    }

    public function getTips(): string
    {
        return "通过审核后将打款到个人微信零钱！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-card_weixin';
    }

    public function getTitle(): string
    {
        return $this->getName();
    }
}