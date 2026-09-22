<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/2
 * Time: 下午4:55
 */

namespace app\frontend\modules\order\services\status;


use app\common\models\Order;
use app\common\models\PayType;

class WaitPay extends Status
{
    /**
     * @var Order
     */
    private $order;
    protected $name = '付款';
    protected $value;
    protected $api = 'order.operation.pay';

    public function __construct(Order $order)
    {
        $this->value = static::PAY;

        $this->order = $order;
    }

    public function getStatusName()
    {
        if($this->order->order_type == 5){
            return "待验收";
        }

        if($this->order->order_type == 2){
            return "待付款";
        }
        if($this->order->order_type == 1 && $this->order->status == 0 && $this->order->orderStatus == 0){
            return "待付预付款";
        }
        return __('order.wait_pay_1');
    }

    public function getValue()
    {
        if($this->order->order_type == 5){
            return 2;
        }

        if($this->order->order_type == 2){
            return 0;
        }
        return 2;
    }
}