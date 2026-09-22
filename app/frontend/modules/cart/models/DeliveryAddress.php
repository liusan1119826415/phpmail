<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/5/20
 * Time: 10:17
 */

namespace app\frontend\modules\cart\models;

use app\common\models\BaseModel;
use app\framework\Http\Request;

/**
 * Class DeliveryAddress
 * @property string address
 * @property string mobile
 * @property string realname
 * @property int order_id
 * @property int province_id
 * @property int city_id
 * @property int district_id
 * @property string note
 * @property int street_id
 * @package app\frontend\modules\cart\models
 */
class DeliveryAddress extends BaseModel
{

}