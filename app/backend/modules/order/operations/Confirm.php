<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/23
 * Time: 下午1:40
 */

namespace app\backend\modules\order\operations;

class Confirm extends BackendOrderBase
{
    public function getApi()
    {
        return 'plugin.supplier.supplier.controllers.order.supplier-order.uploadDraw';
    }

    public function getName()
    {
        if($this->order->order_type == 5){
            return "上传人员信息";
        }
        return "上传图纸";
    }

    public function getValue()
    {
        return self::ADMIN_UPLOAD;
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