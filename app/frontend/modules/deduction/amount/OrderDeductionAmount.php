<?php

namespace app\frontend\modules\deduction\amount;

class OrderDeductionAmount extends BaseOrderDeductionAmountHandle
{
    public function _init()
    {

    }

    public function getAmount()
    {
        return $this->amount;
    }
}