<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class Receive extends BackendOrderBase
{
    public function getApi()
    {
        return 'order.vue-operation.receive';
    }

    public function getName()
    {

        if($this->order->order_type == 5){
            return "上传资料";
        }
        return __('order.confirm_receiving');
    }

    public function getValue()
    {
        return self::ADMIN_RECEIVE;
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