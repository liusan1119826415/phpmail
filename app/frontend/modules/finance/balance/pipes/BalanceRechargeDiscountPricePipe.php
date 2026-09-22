<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/18
 * Time: 14:05
 */

namespace app\frontend\modules\finance\balance\pipes;

use app\frontend\modules\finance\balance\discount\BaseRechargeDiscount;
use app\frontend\modules\finance\balance\PriceCalculator;

class BalanceRechargeDiscountPricePipe  extends InitialPricePipe
{

    /**
     * @var BaseRechargeDiscount
     */
    protected $discount;

    public function __construct(PriceCalculator $calculator,BaseRechargeDiscount $discount, $weight)
    {
        $this->discount = $discount;
        parent::__construct($calculator, $weight);
    }



    public function getKey()
    {
        return $this->discount->getCode();
    }

    public function getAmount()
    {
        return $this->discount->getAmount();
    }

    /**
     * @return mixed
     */
    public function getPrice()
    {
        return max($this->calculator->getPriceBefore($this->getKey()) - $this->getAmount(),0);
    }
}