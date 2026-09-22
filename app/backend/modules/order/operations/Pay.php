<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class Pay extends BackendOrderBase
{
    public function getApi()
    {
        return 'order.vue-operation.pay';
    }

    public function getName()
    {
        if($this->order->order_type == 5){
            return "额外费用";
        }
        return __('order.sure_pay');
    }

    public function getValue()
    {
        return self::ADMIN_PAY;
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