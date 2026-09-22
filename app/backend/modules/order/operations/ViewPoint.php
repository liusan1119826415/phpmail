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

class ViewPoint extends BackendOrderBase
{
    public function getApi()
    {
        return 'order.vue-operation.update-logistics';
    }

    public function getName()
    {

        if(request()->mtype == 5){
            return "查看安装清单";
        }else{
            return "查看提货点";
        }


    }

    public function getValue()
    {
        return 13;
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