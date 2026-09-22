<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class Logistic extends BackendOrderBase
{
    public function getApi()
    {
        return 'plugin.supplier.supplier.controllers.furn.install-order.getLogisticsTracks';
    }

    public function getName()
    {
        return "查看物流";
    }

    public function getValue()
    {
        return 50;
    }

    public function enable()
    {
        return true;
    }

    public function getType()
    {
        return self::TYPE_TEXT;
    }
}