<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/18
 * Time: 14:12
 */

namespace app\common\models\finance;

use app\common\models\BaseModel;

class BalanceRechargeDiscountLog extends BaseModel
{
    protected $table = 'yz_balance_recharge_discount_log';

    protected $guarded = [''];


    public function hasOneRecharge()
    {
        return $this->hasOne(BalanceRecharge::class, 'id', 'recharge_id');
    }
}