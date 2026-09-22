<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/1
 * Time: 下午6:43
 */

namespace app\frontend\modules\order\operations\member;

use app\frontend\modules\order\operations\OrderOperation;

class Product extends OrderOperation
{
    public function getApi()
    {
        return 'project.order.viewProduct';
    }

    public function getName()
    {
        return '查看生产进度';
    }

    public function getValue()
    {
        return static::PRODUCT;
    }

    public function enable()
    {
        if($this->order->order_type != 1){
            return false;
        }
        if ($this->order->isPending()) {
            return false;
        }
        if($this->order->orderStatus != 1){
            return false;
        }
        if($this->order->status != 1 && $this->order->orderStatus != 1){
            return false;
        }
        return true;
    }
}
