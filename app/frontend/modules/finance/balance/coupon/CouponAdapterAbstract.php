<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/18
 * Time: 18:27
 */

namespace app\frontend\modules\finance\balance\coupon;


use app\common\models\ShamFalseModel;
use app\frontend\modules\finance\balance\PreBalanceRechargeCouponDiscount;
use app\frontend\modules\finance\balance\PriceCalculator;
use Illuminate\Database\Eloquent\Model;

abstract class CouponAdapterAbstract
{


    protected $memberCoupon;
    /**
     * @var PriceCalculator
     */
    protected $calculator;

    /**
     * @var ShamFalseModel
     */
    protected $dataClass;


    protected $selected;

    public function __construct($memberCoupon,PriceCalculator $calculator)
    {
        $this->calculator = $calculator;

        $this->memberCoupon = $memberCoupon;
    }
    protected function newShowModel()
    {
        return new \app\common\models\ShamFalseModel();
    }

    final protected function initAttributes(Model $model)
    {

        $model->fill([
            'id' => $this->getMemberCouponId(),
            'member_coupon_id' => $this->getMemberCouponId(),
            'coupon_id' => $this->getCouponId(),
            'enough' => $this->enough(),
            'used' => 0,
            'use_time' => 0,
            'valid' => false,
            'checked' => false,
        ]);

        return $model;
    }

    public function dataShowModel()
    {
        if (!isset($this->dataClass)) {
            $model = $this->initAttributes($this->newShowModel());
            $model->setRelation('couponAdapter', $this);
            $this->dataClass = $model;
        }

        return $this->dataClass;
    }

    abstract public function getMemberCouponId();
    abstract public function getCouponId();
    abstract public function getMemberCoupon();

    abstract public function getCoupon();

    abstract public function enough();

    abstract public function getAmount();

    abstract public function getCouponName();

    /**
     * @return integer|boolean
     */
    abstract public function isComplex();

    abstract public function couponMethod();

    abstract public function discountValue();
    /**
     * @return boolean
     */
    abstract public function _isOptional();


    //充值记录保存触发
    public function saveUseCoupon()
    {

    }

    final public function getDiscountAmount()
    {
        return bankerRounding($this->getAmount());
    }


    public function getCouponBeforePaymentAmount()
    {
        return $this->calculator->getPriceBefore('coupon');
    }

    public function getUnusedEnoughAmount()
    {
        $usedEnough = $this->calculator->getCoupons()->sum(function (self $usedCoupon) {
            return $usedCoupon->enough();
        });
        return $this->getCouponBeforePaymentAmount() - $usedEnough;
    }

    public function discountMethod()
    {
        switch ($this->couponMethod()) {
            case 1:
                $coupon_method =  'fixed';
                break;
            case 2:
                $coupon_method = 'ratio';
                break;
            default:
                $coupon_method = null;
                break;
        }

        return $coupon_method;
    }

    /**
     * 优惠券可使用验证
     * @return bool 1是 0否
     */
    public function valid()
    {
        if (!$this->isOptional()) {
            return false;
        }
        if (!$this->unique()) {
            return false;
        }
        if (!$this->priceValid()) {
            return false;
        }
        return true;
    }

    //实际已用
    public function priceValid()
    {
        //不小于 满减额度
        if (!float_lesser($this->getUnusedEnoughAmount(), $this->enough())) {
            return true;
        }
        return false;
    }

    /**
     * 有效的
     * @return bool
     */
    public function priceOptional()
    {
        // 商品价格 不小于 满减额度
        if (!float_lesser($this->getCouponBeforePaymentAmount(), $this->enough())) {
            return true;
        }

        return false;
    }

    /**
     * 优惠券可选
     * @return bool
     */
    public function isOptional()
    {

        if (!$this->discountMethod()) {
            //\Log::debug("优惠券{$this->getMemberCoupon()->id}", '满减类型设置无效');
            return false;
        }

        //满足额度
        if (!$this->priceOptional()) {
            //\Log::debug("优惠券{$this->getMemberCoupon()->id}", '不满足额度');
            return false;
        }

        //未使用
        if ($this->isUse()) {
            //\Log::debug("优惠券{$this->getMemberCoupon()->id}", '已使用');
            return false;
        }

        if ($this->_isOptional()) {
            return false;
        };

        return true;
    }

    /**
     * 用户优惠券所属的优惠券未被选中过
     * @return bool 1 支持多张 0不支持多张
     */
    public function unique()
    {
        //允许多张使用
        if ($this->isComplex()) {
            return true;
        }

        $memberCoupons = $this->calculator->getCoupons();

        if ($memberCoupons->isEmpty()) {
            return true;
        }

        /**
         * @var self $select
         */
        foreach ($memberCoupons as $select) {
            if ($select->isSelected() == true && $select->getCouponId() == $this->getCouponId()) {
                //\Log::debug("优惠券{$this->getMemberCouponId()}", '同一单不能使用多张此类型优惠券');
                return false;
            }
        }

        return true;
    }

    public function isUse()
    {
        return $this->dataShowModel()->used;
    }

    public function isSelected()
    {
        return $this->selected ? true : false;
    }



    //价格计算已使用优惠券
    public function setCalculatorCoupon()
    {
        $this->calculator->pushCoupon($this);
    }

    public function touchPreAttributes()
    {
        return [];
    }

    public function couponAttributes()
    {

        $attributes = [
            'name' => $this->getCouponName(),
            'discount_method' => $this->discountMethod(),
            'discount_value' => $this->discountValue(),
        ];

        return $attributes;
    }

    public function toModel()
    {
        $attributes = array_merge($this->couponAttributes(), $this->touchPreAttributes());

        $this->dataShowModel()->fill($attributes);

        return $this->dataShowModel();
    }

    /**
     * 激活优惠券
     */
    public function activate()
    {
        //防止重复执行
        if ($this->selected) {
            return;
        }

        $this->selected = 1;
        //记录优惠券被选中了
        $this->dataShowModel()->checked = 1;
        $this->dataShowModel()->used = 1;
        $this->dataShowModel()->use_time = time();

        $preOrderDiscount = new PreBalanceRechargeCouponDiscount([
            'amount' => bankerRounding($this->getDiscountAmount()),
            'code' => 'coupon',
            'discount_id' => $this->getMemberCouponID(),
            'name' => $this->getCouponName(),
            'desc' => '',
        ]);
        $preOrderDiscount->couponAdapter = $this;

        $preOrderDiscount->setRechargeCoupon($this->calculator->balanceRecharge);

    }

}