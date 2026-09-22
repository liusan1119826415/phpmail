<?php

namespace app\common\payment\method\other;

use app\common\payment\method\BasePayment;
use app\common\payment\setting\other\MerchantLoanPaySetting;

class MerchantLoanPayPayment extends BasePayment
{
    public $code = 'merchantLoanPay';

    public function __construct(MerchantLoanPaySetting $paymentSetting)
    {
        $this->setSetting($paymentSetting);
    }
}