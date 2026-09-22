<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/6/28
 * Time: 16:35
 */

namespace app\backend\modules\order\models;


use app\common\models\order\Express;

class OrderExpress extends Express
{
    protected $hidden = ['deleted_at'];
}