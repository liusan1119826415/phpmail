<?php

namespace app\common\events\balance;

use app\common\models\finance\BalanceRecharge;

class RechargeCreatedEvent
{
    protected BalanceRecharge $balanceRecharge;

    public function __construct(BalanceRecharge $balanceRecharge)
    {
        $this->balanceRecharge = $balanceRecharge;
    }

    /**
     * @return BalanceRecharge
     */
    public function getBalanceRecharge(): BalanceRecharge
    {
        return $this->balanceRecharge;
    }
}
