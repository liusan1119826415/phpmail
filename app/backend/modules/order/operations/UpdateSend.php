<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/6/28
 * Time: 14:38
 */

namespace app\backend\modules\order\operations;


use app\backend\modules\dispatch\models\DispatchType;

class UpdateSend extends BackendOrderBase
{
    public function getApi()
    {
        return 'order.vue-operation.update-logistics';
    }

    public function getName()
    {
        return __('order.modify_logistics');
    }

    public function getValue()
    {
        return self::UPDATE_SEND;
    }

    public function enable()
    {

        if (!in_array($this->order->dispatch_type_id,[DispatchType::EXPRESS]) || $this->order->isVirtual()) {
            return false;
        }

        return true;
    }

    public function getType()
    {
        return self::TYPE_WARNING;
    }
}