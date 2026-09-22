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

class WaitReceive extends Status
{
    protected $name = '收货';
    protected $api = 'order.operation.receive';
    protected $value;
    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->value = static::COMPLETE;
    }

    public function getStatusName()
    {
        if($this->order->is_all_send_goods == 1){
            return __('order.partial_delivery');
        }else{
//            return "待{$this->name}";
           // return __('order.wait_receive');
            return "待验收";
        }

    }

    public function getValue()
    {


        if($this->order->order_type == 2){
            return 2;
        }

        return 6;
    }
}