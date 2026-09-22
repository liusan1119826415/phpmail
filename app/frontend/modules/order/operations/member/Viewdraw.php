<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/1
 * Time: 下午6:43
 */

namespace app\frontend\modules\order\operations\member;

use app\frontend\modules\order\operations\OrderOperation;

class Viewdraw extends OrderOperation
{
    public function getApi()
    {
        return 'project.order.viewDraw';
    }

    public function getName()
    {
        return '查看图纸';
    }

    public function getValue()
    {
        return static::VIEW_DRAW;
    }

    public function enable()
    {
        //查询状态

        if ($this->order->isPending()) {
            return false;
        }
        if($this->order->order_type != 1){
            return false;
        }
        return true;
    }
}
