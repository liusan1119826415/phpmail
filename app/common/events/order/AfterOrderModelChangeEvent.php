<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/17
 * Time: 下午9:12
 */

namespace app\common\events\order;


use app\common\events\Event;
use app\common\models\Order;

class AfterOrderModelChangeEvent extends Event
{

    public $order;
    public $type;

    public function __construct($order, $type)
    {
        $this->order = $order;
        $this->type = $type;
    }


    public function getType()
    {
        return $this->type;
    }

    /**
     * (监听者)获取订单model
     * @return Order
     */
    public function getOrderModel()
    {
        return $this->order;
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

}