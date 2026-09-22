<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/18
 * Time: 14:35
 */

namespace app\frontend\modules\finance\balance\coupon;


use app\common\helpers\ArrayHelper;
use app\frontend\modules\finance\balance\PriceCalculator;
use Illuminate\Support\Collection;

class RechargeCouponService
{
    /**
     * @var PriceCalculator
     */
    protected $calculator;

    protected $selectedMemberCoupon;

    static protected $memberCoupons;


    public function __construct(PriceCalculator $calculator)
    {
        $this->calculator = $calculator;

    }

    //有效已选择优惠券
    public function getDiscountPrice()
    {
        $amount = $this->getAllValidCoupons()->sum(function (CouponAdapterAbstract $coupon) {
            if (!$coupon->valid()) {
                return 0;
            }
            $coupon->activate();
            $coupon->setCalculatorCoupon();

            return $coupon->getDiscountAmount();
        });

        return $amount;
    }

    /**
     * 获取所有选中并有效的优惠券
     * @return Collection
     */
    public function getAllValidCoupons()
    {

        $result = $this->getSelectedMemberCoupon()->filter(function (CouponAdapterAbstract $coupon) {

            return $coupon->valid();
        });

        return $result;
    }

    /**
     * 获取所有有效的优惠券
     * @return Collection
     */
    public function getOptionalCoupons()
    {
        //其他优惠组合后可选的优惠券（未选中）
        $coupons = $this->getMemberCoupon()->filter(function (CouponAdapterAbstract $coupon) {
            //不可选
            if (!$coupon->isOptional()) {
                return false;
            }
            $coupon->dataShowModel()->valid = $coupon->valid();//界面标蓝（是否可选）
            $coupon->dataShowModel()->checked = false;//界面选中
            return true;
        })->values();

        //把已选的优惠券加到未选的后面，之后合并相同的覆盖之前未选数据
        $coupons = collect($this->calculator->getCoupons())->map(function (CouponAdapterAbstract $coupon) {

            $coupon->dataShowModel()->valid = true;//界面标蓝（是否可选）
            $coupon->dataShowModel()->checked = true;//界面选中
            return $coupon;
        })->merge($coupons);

        $mergeCoupon = $this->newCollection($coupons)->map(function (CouponAdapterAbstract $coupon) {
            return $coupon->toModel();
        })->unique('member_coupon_id');
        //按member_coupon的id倒序
        $collect = $mergeCoupon->sortByDesc('member_coupon_id')->values();

        return $collect;
    }

    /**
     * 用户拥有并选中的优惠券
     * @return Collection
     */
    protected function getSelectedMemberCoupon()
    {
        if (!isset($this->selectedMemberCoupon)) {
            $member_coupon_ids = ArrayHelper::unreliableDataToArray(\Request::input('member_coupon_ids'));
            $coupon_list = [];
            //不实用集合方法是因为集合会把选中的顺序重新排序
            foreach ($member_coupon_ids as $k => $v) {

                $coupon = $this->getMemberCoupon()->first(function(CouponAdapterAbstract $coupon) use ($v) {
                    return $coupon->getMemberCouponId() == $v;
                });
                if ($coupon) {
                    $coupon_list[$k] = $coupon;
                }
            }

            $this->selectedMemberCoupon = collect($coupon_list);
        }

        return $this->selectedMemberCoupon;
    }



    public function newCollection($items = [])
    {
        return new \app\framework\Database\Eloquent\Collection($items);
    }


    /**
     * 用户拥有的优惠券
     * @return Collection
     */
    public function getMemberCoupon()
    {
        if (!isset(self::$memberCoupons)) {
            return self::$memberCoupons = $this->getCurrentMemberCoupon();
        }
        return self::$memberCoupons;

    }

    /**
     * 这里需要读取配置获取所以支持使用的会员优惠券
     * @return Collection
     */
    protected function getCurrentMemberCoupon()
    {
        $memberCouponList = $this->couponConfigs();

        return $memberCouponList;
    }

    protected function couponConfigs()
    {
//       \app\common\modules\shop\ShopConfig::current()->push('shop-foundation.balance.recharge-coupon', [
//           'coupon_adapter_class' => CouponAdapter::class,//获取会员可用优惠券
//           'get_coupon_method' => 'getMemberCouponList',
//        ]);

        $configs = \app\common\modules\shop\ShopConfig::current()->get('shop-foundation.balance.recharge-coupon');

        $allMemberCoupon = collect([]);
        foreach ($configs as $config) {

            $class = array_get($config,'coupon_adapter_class');
            $function = array_get($config,'get_coupon_method');

            if (class_exists($class) && method_exists($class,$function)) {

                $memberCouponCollect = $class::$function($this->calculator->balanceRecharge);

                if ($memberCouponCollect && count($memberCouponCollect) > 0) {
                    foreach ($memberCouponCollect as $memberCoupon) {
//                        $memberCoupon->couponAdapterClass = $couponAdapter;
//                        $allMemberCoupon->push($memberCoupon);
                        $allMemberCoupon->push(new $class($memberCoupon, $this->calculator));
                    }
                }
            }

        }

        return $allMemberCoupon;
    }

}