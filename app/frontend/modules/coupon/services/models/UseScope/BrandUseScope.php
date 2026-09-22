<?php

namespace app\frontend\modules\coupon\services\models\UseScope;


use app\common\exceptions\AppException;
use app\common\models\Goods;
use app\common\models\Store;
use app\common\modules\orderGoods\models\PreOrderGoods;
use app\frontend\modules\orderGoods\models\PreOrderGoodsCollection;
use Yunshop\StoreCashier\common\models\CashierGoods;
use Yunshop\StoreCashier\common\models\StoreGoods;

class BrandUseScope extends CouponUseScope
{
    public function _getOrderGoodsOfUsedCoupon()
    {
        $order_goods = new PreOrderGoodsCollection();
//        if (!app('plugins')->isEnabled('store-cashier')) {
//            return $order_goods;
//        }
        $order_goods = $this->coupon->getPreOrder()->orderGoods->filter(function ($orderGoods) {
            $brand_ids = $this->coupon->getMemberCoupon()->belongsToCoupon->brand_ids;
            if (!is_array($brand_ids)) {
                $brand_ids = json_decode($brand_ids, true);
            }
            $brand_ids = $brand_ids && is_array($brand_ids) ? $brand_ids : [];
            $brand_id = Goods::where('id', $orderGoods->goods_id)->value('brand_id');
            if ($brand_id && $brand_ids && in_array($brand_id, $brand_ids)) {
                return true;
            }
            return false;
        });
        if ($order_goods->unique('is_plugin')->count() > 1) {
            throw new AppException('自营商品与第三方商品不能共用一张优惠券');
        }
        return $order_goods;
    }
}