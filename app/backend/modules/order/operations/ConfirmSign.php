<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class ConfirmSign extends BackendOrderBase
{
    public function getApi()
    {
        return 'plugin.supplier.supplier.controllers.freight.logiserv-order.confirmSign';
    }

    public function getName()
    {

        return "确认签收";
    }

    public function getValue()
    {
        return 16;
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