<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class ConfirmSend extends BackendOrderBase
{
    public function getApi()
    {
        return 'plugin.supplier.supplier.controllers.freight.logiserv-order.confirmSend';
    }

    public function getName()
    {

        return "确认发货";
    }

    public function getValue()
    {
        return 15;
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