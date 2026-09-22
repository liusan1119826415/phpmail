<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

use app\common\facades\Setting;

class Balance extends DefaultWithdrawButton
{
    protected $value = 'balance';

    public function isEnabled(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到' . $this->getDiyName();
    }

    public function getOtherName(): string
    {
        return $this->getDiyName();
    }

    public function getTips(): string
    {
        return "通过审核后将打款到系统{$this->getOtherName()}！";
    }

    public function getIcon(): string
    {
        return 'iconfont icon-fontclass-fanli';
    }

    public function getTitle(): string
    {
        return $this->getName();
    }

    private function getDiyName()
    {
        $set = Setting::get('shop.lang.zh_cn');
        return $set['member_center']['credit'] ? : '余额';
    }
}