<?php
/**
 * Created by PhpStorm.
 * User: CGOD
 * Date: 2019/12/17
 * Time: 10:52
 */

namespace app\common\models\coupon;


use app\common\models\BaseModel;
use app\common\models\OrderGoods;
use app\common\models\Coupon;

class OrderGoodsCouponSend extends BaseModel
{
    protected $table = 'yz_order_goods_coupon_send';

    protected $guarded = [''];

    public function orderGoodsCoupon()
    {
        return $this->belongsTo(OrderGoodsCoupon::class, 'order_goods_coupon_id','id');
    }
}