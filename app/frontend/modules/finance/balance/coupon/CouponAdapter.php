<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/18
 * Time: 15:10
 */

namespace app\frontend\modules\finance\balance\coupon;


use app\frontend\modules\finance\balance\PreBalanceRechargeDiscount;
use app\frontend\modules\finance\balance\PriceCalculator;

class CouponAdapter extends CouponAdapterAbstract
{
    public static function getMemberCouponList($balanceRecharge)
    {
        $a = \app\common\models\MemberCoupon::uniacid()
            ->where('uid', $balanceRecharge->member_id)
            ->get();
        return $a;
    }

    public function getMemberCouponId()
    {
        return $this->memberCoupon->id;
    }

    public function getCouponId()
    {
        return $this->getCoupon()->id;
    }

    public function getMemberCoupon()
    {
        return $this->memberCoupon;
    }

    public function getCoupon()
    {
        return $this->memberCoupon->belongsToCoupon;
    }

    public function getCouponName()
    {
        return $this->getCoupon()->name;
    }

    public function enough()
    {
        return $this->getCoupon()->enough;
    }

    public function isComplex()
    {
       return false;
    }

    public function _isOptional()
    {
        return false;
    }

    public function couponMethod()
    {
        return $this->getCoupon()->coupon_method;
    }

    public function discountValue()
    {
        return $this->couponMethod() == 1 ? $this->getFixed() : $this->getRatio();
    }

    public function getRatio()
    {
        return $this->getCoupon()->discount;
    }

    public function getFixed()
    {
        return $this->getCoupon()->deduct;
    }

    public function getRatioDiscountAmount()
    {
        return (1 - $this->getRatio() /10) * $this->getCouponBeforePaymentAmount();
    }

    public function getFixedDiscountAmount()
    {
        return min($this->getFixed(), $this->getCouponBeforePaymentAmount());
    }

    public function getAmount()
    {
        switch ($this->discountMethod()) {
            case 'fixed':
                $amount = $this->getFixedDiscountAmount();
                break;
            case 'ratio':
                $amount = $this->getRatioDiscountAmount();
                break;
            default:
                $amount  = 0;
                break;
        }

        return bankerRounding($amount);
    }



}