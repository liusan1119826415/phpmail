<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/12/10
 * Time: 17:18
 */

namespace app\backend\modules\refund\services\button;


class Reject extends RefundButtonBase
{
    public function getApi()
    {
        if($this->refund->order->supp_id >0){
            return 'plugin.supplier.supplier.controllers.order.vue-operation.reject';
        }
        return 'refund.vue-operation.reject';
    }

    public function getName()
    {
        return '拒绝申请';
    }

    public function getValue()
    {
        return -1;
    }

    public function enable()
    {
        return $this->refund->isRefunding() && $this->refund->status < 4;
    }

    public function getType()
    {
        return self::TYPE_DANGER;
    }

    public function getDesc()
    {
       return '';
    }
}