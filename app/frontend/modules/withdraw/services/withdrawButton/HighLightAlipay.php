<?php

namespace app\frontend\modules\withdraw\services\withdrawButton;

class HighLightAlipay extends DefaultWithdrawButton
{
    protected $value = 'high_light_alipay';

    private $high_light_name;

    public function isEnabled(): bool
    {
        if (!app('plugins')->isEnabled('high-light') || !\Yunshop\HighLight\services\SetService::getStatus()) {
            return false;
        }
        return true;
    }

    public function getName(): string
    {
        return $this->withdraw() . '到 支付宝-' . $this->getDiyName();
    }

    public function getOtherName(): string
    {
        return '支付宝-' . $this->getDiyName();
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

    private function getDiyName()
    {
        if (!isset($this->high_light_name)) {
            $this->high_light_name = \Yunshop\HighLight\services\SetService::getDiyName();
        }
        return $this->high_light_name;
    }
}