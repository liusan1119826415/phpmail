<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class Send extends BackendOrderBase
{
    public function getApi()
    {

       if($this->order->status == 1 && $this->order->orderStatus == 3){
           return 'plugin.supplier.supplier.controllers.order.supplier-order.confirmSend';
       }
        return 'order.vue-operation.send';
    }

    public function getName()
    {
        return __('order.confirm_delivery');
    }

    public function getValue()
    {
        return self::ADMIN_SEND;
    }

    public function enable()
    {
        return true;
    }

    public function getType()
    {
        return self::TYPE_PRIMARY;
    }
}