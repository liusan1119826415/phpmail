<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/25
 * Time: 14:12
 */

namespace app\frontend\modules\payment\services\page\reward;


use app\common\models\coupon\OrderGoodsCoupon;

class CouponPayPageReward extends PayPageRewardInterface
{

    public $priority = -9999;

    public $giveCoupon;

    public function whetherHave()
    {
        return $this->giveCoupon() && $this->giveCoupon()->isNotEmpty();
    }
    public function _getAmount()
    {
        return 0;
    }

    public function afterPayGive()
    {
        return 0;
    }

    public function getTitle()
    {
        return '优惠券';
    }

    public function getDesc()
    {
        return '优惠券';
    }

    public function giveCoupon()
    {

        if (isset($this->giveCoupon)) {
            return $this->giveCoupon;
        }

        $order_goods_ids =  $this->getOrder()->orderGoods->pluck('id')->all();
        $records = OrderGoodsCoupon::uniacid()
            ->where('status','>=',OrderGoodsCoupon::WAIT_STATUS)
            ->whereIn('order_goods_id', $order_goods_ids)
            ->with(['hasOneCoupon'])
            ->get();

        $records->filter(function ($record) {
            return $record->hasOneCoupon;
        })->values();

        return $this->giveCoupon = $records;
    }

    public function arrayData()
    {

        $array = [];
        foreach ($this->giveCoupon() as $record) {
            $array[] = [
                'is_give' => $record->status == 1 ? 1 : 0, //不等于1为未赠送
                'id' =>$record->hasOneCoupon->id,
                'title' => $record->hasOneCoupon->name,
                'coupon_method' => $record->hasOneCoupon->coupon_method,
                'value' => $record->hasOneCoupon->coupon_method == 1 ? $record->hasOneCoupon->deduct : $record->hasOneCoupon->discount,
                'enough' => $record->hasOneCoupon->enough,
            ];
        }

        return $array;
    }

    public function getIcon()
    {
        return '';
    }

    public function getUrl()
    {
        return '';
    }

    public function getMiniUrl()
    {
        return '';
    }
}