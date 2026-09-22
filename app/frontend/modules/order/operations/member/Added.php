<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/1
 * Time: 下午6:43
 */

namespace app\frontend\modules\order\operations\member;

use app\frontend\modules\order\operations\OrderOperation;

class Added extends OrderOperation
{
    public function getApi()
    {
        return 'project.door-order.addAmount';
    }

    public function getName()
    {
        return '资料下载';
    }

    public function getValue()
    {
        return static::PRODUCT;
    }

    public function enable()
    {

        if($this->order->order_type == 5){
            return true;
        }

    }
}
