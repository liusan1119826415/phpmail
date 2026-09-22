<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/1
 * Time: 下午6:43
 */

namespace app\frontend\modules\order\operations\member;

use app\frontend\modules\order\operations\OrderOperation;

class ChangePrice extends OrderOperation
{
    public function getApi()
    {
        return 'project.order.changePrice';
    }

    public function getName()
    {
        return '修改价格';
    }

    public function getValue()
    {
        return 65;
    }

    public function enable()
    {
        if(!in_array($this->order->order_type,[4])){
            return false;
        }

        return false;
    }
}
