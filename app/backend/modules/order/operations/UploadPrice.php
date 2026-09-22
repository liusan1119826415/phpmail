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

class UploadPrice extends BackendOrderBase
{
    public function getApi()
    {

        return 'plugin.supplier.supplier.controllers.freight.bidding-order.updatePrice';

    }

    public function getName()
    {
        if ($this->order->hasOneSupplierPrice->bidding_status == 1 && request()->mtype == 3) {

            return '上传报价';
        } elseif ($this->order->hasOneSupplierPrice->bidding_status == 2 && request()->mtype == 3) {
            return "修改报价";
        } elseif ($this->order->hasOneSupplierInstallPrice->bidding_status == 1 && request()->mtype == 5) {
            return "上传报价";
        } elseif ($this->order->hasOneSupplierInstallPrice->bidding_status == 2 && request()->mtype == 5) {
            return "修改报价";
        }

    }

    public function getValue()
    {
        return 12;
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