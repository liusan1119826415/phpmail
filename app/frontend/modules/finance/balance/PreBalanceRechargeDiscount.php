<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/18
 * Time: 14:14
 */

namespace app\frontend\modules\finance\balance;


use app\common\models\finance\BalanceRechargeDiscountLog;

class PreBalanceRechargeDiscount extends BalanceRechargeDiscountLog
{
    protected $balanceRecharge;


    public function setBalanceRecharge(PreBalanceRecharge $balanceRecharge)
    {
        $this->balanceRecharge = $balanceRecharge;
        $this->member_id = $balanceRecharge->member_id;
        $balanceRecharge->getDiscounts()->push($this);
    }

}