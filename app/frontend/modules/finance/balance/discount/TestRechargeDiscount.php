<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/18
 * Time: 14:22
 */

namespace app\frontend\modules\finance\balance\discount;


class TestRechargeDiscount extends BaseRechargeDiscount
{
    protected $code = 'test';

    protected $name = '测试余额充值优惠';

    protected function _getAmount()
    {
        return 10;
    }
}