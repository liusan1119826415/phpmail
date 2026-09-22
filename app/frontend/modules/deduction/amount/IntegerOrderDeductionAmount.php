<?php

namespace app\frontend\modules\deduction\amount;

class IntegerOrderDeductionAmount extends OrderDeductionAmount
{
    public function _init()
    {
        $this->amount = intval($this->amount);
    }
}