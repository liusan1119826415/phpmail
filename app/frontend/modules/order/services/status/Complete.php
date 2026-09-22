<?php
/**
 * Created by PhpStorm.
 * Author:  
 * Date: 2017/3/2
 * Time: 下午4:55
 */

namespace app\frontend\modules\order\services\status;


use app\common\models\DispatchType;
use app\common\models\Order;

class Complete extends Status
{
    private $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function getStatusName()
    {
        return __('order.transaction_complete');
    }


    public function getValue()
    {

        if($this->order->order_type == 5){
            return 3;
        }

        if($this->order->order_type == 2){
            return 3;
        }
        return 7;
    }

}