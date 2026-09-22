<?php
/**
 * Created by PhpStorm.
 * Author:  
 * Date: 2017/3/2
 * Time: 下午4:55
 */

namespace app\frontend\modules\order\services\status;


use app\common\models\Order;

class Close extends Status
{
    private $order;
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function getStatusName()
    {
        return __('order.cancelled');
    }

    public function getValue()
    {
        if(in_array($this->order->order_type,[2,3,4,5])){
            return -1;
        }
        return 8;
    }
}