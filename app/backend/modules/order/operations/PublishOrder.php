<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class PublishOrder extends BackendOrderBase
{
    public function getApi()
    {
        return 'feight.logistics.publishOrder';
    }

    public function getName()
    {

        return "发布订单";
    }

    public function getValue()
    {
        return 30;
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