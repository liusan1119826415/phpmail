<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/1
 * Time: 下午6:43
 */

namespace app\frontend\modules\order\operations\member;

use app\frontend\modules\order\operations\OrderOperation;

class Restore extends OrderOperation
{
    public function getApi()
    {
        return 'order.operation.restore';
    }
    public function getName()
    {

        return "恢复订单";
    }

    public function getValue()
    {
        return 61;
    }

    public function enable()
    {
        if ($this->order->isPending()) {
            return false;
        }
        if(!in_array($this->order->order_type,[2,3,4])){
            return false;
        }
        return true;
    }
}
