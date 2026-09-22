<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/4/10
 * Time: 下午6:05
 */

namespace app\frontend\modules\coupon\services;


use Illuminate\Support\Collection;

class MemberCouponService
{
    static private $memberCoupons;

    static private $memberCouponsSelect;

    public static function getCurrentMemberCouponCache($member)
    {
        if (!isset(self::$memberCoupons)) {
            return self::$memberCoupons = self::getCurrentMemberCoupon($member);
        }
        return self::$memberCoupons;

    }

    public static function getCurrentMemberCouponSelectCache($member)
    {
        if (!isset(self::$memberCouponsSelect)) {
            return self::$memberCouponsSelect = static::getCurrentMemberCouponCache($member)->where('selected', true);
        }
        return self::$memberCouponsSelect;
    }

    public static function  getCurrentMemberCouponSelect()
    {
        return isset(self::$memberCouponsSelect) ? self::$memberCouponsSelect : collect([]);
    }

    //仅支持单张使用的优惠券，已使用缓存
    public static function setMemberCouponsSelect($memberCoupon)
    {
        if (!isset(self::$memberCouponsSelect)) {
            self::$memberCouponsSelect = collect([$memberCoupon]);
        } else {
            if (!self::$memberCouponsSelect->where('id', $memberCoupon->id)->count()) {
                self::$memberCouponsSelect->push($memberCoupon);
            }
        }
    }


    public static function unsetMemberCoupons()
    {
        self::$memberCoupons = null;
    }

    /**
     * @param $member
     * @return Collection
     */
    public static function getCurrentMemberCoupon($member)
    {
        return $member->hasManyMemberCoupon()->with(['belongsToCoupon'])->get();
    }
}