<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/14
 * Time: 9:29
 */

namespace app\frontend\modules\finance\balance;

use app\framework\Database\Eloquent\Collection;
use app\frontend\modules\finance\balance\coupon\CouponAdapter;
use app\frontend\modules\finance\balance\coupon\CouponAdapterAbstract;
use app\frontend\modules\finance\balance\coupon\RechargeCouponService;
use app\frontend\modules\finance\balance\discount\BaseRechargeDiscount;
use app\frontend\modules\finance\balance\pipes\BalanceRechargeCouponPricePipe;
use app\frontend\modules\finance\balance\pipes\BalanceRechargeDiscountPricePipe;
use app\frontend\modules\finance\balance\pipes\InitialPricePipe;
use app\frontend\modules\order\PriceNode;
use app\frontend\modules\order\PriceNodeTrait;

class PriceCalculator
{

    use PriceNodeTrait;

    public $balanceRecharge;


    /**
     * @var Collection
     */
    public $coupons;

    /**
     * @var RechargeCouponService
     */
    public $couponService;

    public function __construct(PreBalanceRecharge $balanceRecharge)
    {
        $this->balanceRecharge = $balanceRecharge;

        $this->coupons = new Collection([]);
    }


    protected $discountWeight = 0;
    /**
     * @return mixed
     * @throws \app\common\exceptions\AppException
     */
    public function _getPriceNodes()
    {

        $nodes = collect([
            new InitialPricePipe($this, 1000),
            new BalanceRechargeCouponPricePipe($this,4000),
        ]);

        $discountNodes = $this->getDiscounts()->map(function (BaseRechargeDiscount $discount) {
            $this->discountWeight += 1;
            return new BalanceRechargeDiscountPricePipe($this, $discount, 2000 + $this->discountWeight);
        });

        // 按照weight排序
        $nodes = $nodes->merge($discountNodes)->sortBy(function (PriceNode $priceNode) {
            return $priceNode->getWeight();
        })->values();
        return $nodes;
    }

    public function getInitialRechargeAmount()
    {
        return $this->balanceRecharge->getRechargeAmount();
    }

    /**
     * 充值实付金额
     */
    public function getActualPayAmount()
    {
        return max($this->getPriceAfter($this->getPriceNodes()->last()->getKey()), 0);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getDiscounts()
    {

        $discounts = collect([]);

        $configs = \app\common\modules\shop\ShopConfig::current()->get('shop-foundation.balance.recharge-discount');


        foreach ($configs as $config) {

            $discountClass = call_user_func($config['class'], $this);
            if ($discountClass->validate()) {
                $discounts->push($discountClass);
                break;
            }
        }

        //$discounts->push(new \app\frontend\modules\finance\balance\discount\TestRechargeDiscount($this));

        return $discounts;
    }

    /**
     * @return RechargeCouponService
     */
    public function getCouponService()
    {
        if (!isset($this->couponService)) {
            $this->couponService = new RechargeCouponService($this);
        }

        return $this->couponService;
    }

    public function getOptionalCoupons()
    {
        return $this->getCouponService()->getOptionalCoupons();
    }

    public function pushCoupon($coupon)
    {

        if ($this->getCoupons()->isNotEmpty() && $this->getCoupons()->firstWhere('member_coupon_id', $coupon->getMemberCouponId())) {
            return;
        }

        $this->getCoupons()->push($coupon);
    }

    public function getCoupons()
    {
       return $this->coupons;
    }



}