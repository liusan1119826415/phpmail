<?php
/**
 * Created by PhpStorm.
 * Author:  
 * Date: 2017/3/2
 * Time: 下午4:55
 */

namespace app\frontend\modules\order\services\status;


use app\common\models\Order;

class Confirm extends Status
{
    private $order;
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function getStatusName()
    {
        if($this->order->order_type == 5){
            return "待接单";
        }
        return "待确认图纸";
    }

    public function getValue()
    {
        return 1;
    }
}