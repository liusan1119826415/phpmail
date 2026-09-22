<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/18
 * Time: 15:27
 */

namespace app\frontend\modules\finance\balance\pipes;


use app\frontend\modules\finance\balance\PriceCalculator;

class BalanceRechargeCouponPricePipe extends InitialPricePipe
{


    public function __construct(PriceCalculator $calculator, $weight)
    {
        parent::__construct($calculator, $weight);
    }



    public function getKey()
    {
        return 'coupon';
    }

    public function getAmount()
    {

        $discountPrice = $this->calculator->getCouponService()->getDiscountPrice();


        return $discountPrice;
    }

    /**
     * @return mixed
     */
    public function getPrice()
    {
        $amount = $this->getAmount();

        if ($amount) {
            return max($this->calculator->getPriceBefore($this->getKey()) - $amount,0);
        }

        return $this->calculator->getPriceBefore($this->getKey());
    }
}