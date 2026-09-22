<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class ResetPublishOrder extends BackendOrderBase
{
    public function getApi()
    {
        return 'feight.logistics.publishOrder';
    }

    public function getName()
    {

        return "重新发布";
    }

    public function getValue()
    {
        return 33;
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