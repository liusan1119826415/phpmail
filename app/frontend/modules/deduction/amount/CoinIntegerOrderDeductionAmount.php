<?php

namespace app\frontend\modules\deduction\amount;

class CoinIntegerOrderDeductionAmount extends OrderDeductionAmount
{
    public function _init()
    {
        $coin = $this->getOrderDeduction()->newCoin()->setMoney($this->amount)->getCoin();
        $coin = intval($coin);
        $this->amount = $this->getOrderDeduction()->newCoin()->setCoin($coin)->getMoney();
    }
}