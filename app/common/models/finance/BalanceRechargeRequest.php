<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/25
 * Time: 16:27
 */

namespace app\common\models\finance;

use app\common\models\BaseModel;

class BalanceRechargeRequest extends BaseModel
{
    protected $table = 'yz_balance_recharge_request';

    protected $guarded = [''];

    protected $casts = [
        'request' => 'json',
    ];

    public function hasOneRecharge()
    {
        return $this->hasOne(BalanceRecharge::class, 'id', 'recharge_id');
    }
}