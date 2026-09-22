<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/2
 * Time: 下午4:55
 */

namespace app\frontend\modules\order\services\status;


use app\common\models\Order;

class WaitSend extends Status
{
    private $order;
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function getStatusName()
    {
        $name = __('order.wait_send');
        switch ($this->order->dispatch_type_id) {
            case 4:
                $name = __('order.to_be_confirmed');
                break;

        }
       if($this->order->order_type == 1){
           if($this->order->orderStatus == 1){
               return "生产中";
           }elseif ($this->order->orderStatus == 2){
               return "待付尾款";
           }elseif ($this->order->orderStatus == 3){
               return "待发货";
           }
       }else{
           return "待发货";
       }


        return $name;
    }

    public function getValue()
    {

        //检查子订单状态
        if($this->order->order_type == 1){
            if($this->order->orderStatus == 1){
                return 3;
            }elseif ($this->order->orderStatus == 2){
                return 4;
            }elseif ($this->order->orderStatus == 3){
                return 5;
            }
        }
        if($this->order->order_type == 2){
            return 1;
        }

    }
}