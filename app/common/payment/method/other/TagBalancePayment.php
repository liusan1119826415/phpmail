<?php

namespace app\common\payment\method\other;

use app\common\payment\method\BasePayment;
use app\common\payment\setting\other\TagBalanceSetting;
use Yunshop\TagBalance\services\BalanceService;

class TagBalancePayment extends BasePayment
{
    public $code = 'tagBalancePay';

    public function __construct(TagBalanceSetting $paymentSetting)
    {
        $this->setSetting($paymentSetting);
    }

    public function getUsable()
    {
        if (\YunShop::app()->getMemberId() == 0) {
            return 0;
        }
        return BalanceService::memberTagBalance(\YunShop::app()->getMemberId());
    }

    public function getOther()
    {
        return [
            'usable' => $this->getUsable()
        ];
    }
}