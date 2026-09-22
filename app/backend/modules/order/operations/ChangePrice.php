<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class ChangePrice extends BackendOrderBase
{
    public function getApi()
    {
        return 'plugin.supplier.supplier.controllers.order.supplier-order.changePrice';
    }

    public function getName()
    {
        return "修改价格";
    }

    public function getValue()
    {
        return self::ADMIN_CHANGE_PRICE;
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