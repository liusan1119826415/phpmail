<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/24
 * Time: 15:50
 */

namespace app\frontend\modules\finance\balance;

use app\common\models\finance\BalanceRechargeDiscountLog;
use app\frontend\modules\finance\balance\coupon\CouponAdapterAbstract;

class PreBalanceRechargeCouponDiscount extends BalanceRechargeDiscountLog
{
    protected $balanceRecharge;


    /**
     * @var CouponAdapterAbstract
     */
    public $couponAdapter;

    public function setRechargeCoupon(PreBalanceRecharge $balanceRecharge)
    {
        $this->balanceRecharge = $balanceRecharge;
        $this->uniacid = \YunShop::app()->uniacid;
        $this->member_id = $balanceRecharge->member_id;
        $balanceRecharge->getCoupons()->push($this);
    }

    public function afterSaving()
    {
        if ($this->couponAdapter) {
            $this->couponAdapter->saveUseCoupon();
        }
    }
}