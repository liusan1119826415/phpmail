<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class ConfirmProduct extends BackendOrderBase
{
    public function getApi()
    {
        return 'plugin.supplier.supplier.controllers.order.supplier-order.confirmProduct';
    }

    public function getName()
    {
        return "确认生产完成";
    }

    public function getValue()
    {
        return 20;
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