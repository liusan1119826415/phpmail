<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/4/22
 * Time: 15:54
 */

namespace app\outside\modules\order\models;


use app\common\models\OrderAddress;

class OrderAddressApi extends OrderAddress
{
    protected $hidden = [
        'id','order_id','province_id','city_id','district_id','street_id',
        'delivery_day','delivery_time','note','zipcode'
    ];
}