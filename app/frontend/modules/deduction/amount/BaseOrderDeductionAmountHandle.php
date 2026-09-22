<?php

namespace app\frontend\modules\deduction\amount;

use app\frontend\models\order\PreOrderDeduction;

abstract class BaseOrderDeductionAmountHandle
{
    protected $preOrderDeduction;

    protected $amount;

    public function __construct(PreOrderDeduction $preOrderDeduction,$amount)
    {
        $this->preOrderDeduction = $preOrderDeduction;
        $this->amount = $amount;
        $this->_init();
    }

    /**
     * @return PreOrderDeduction
     */
    public function getOrderDeduction(): PreOrderDeduction
    {
        return $this->preOrderDeduction;
    }

    abstract function _init();

    abstract function getAmount();
}