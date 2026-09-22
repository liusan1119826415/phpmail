<?php

namespace app\frontend\modules\orderGoods;

use app\frontend\modules\orderGoods\discount\BaseDiscount;
use app\frontend\modules\orderGoods\discount\BaseDiscountAfterDeduction;
use app\frontend\modules\orderGoods\price\option\BaseOrderGoodsPrice;

class OrderGoodsDiscountAfterDeductionPriceNode extends BaseOrderGoodsPriceNode
{
    /**
     * @var BaseDiscount
     */
    private $discount;

    public function __construct(BaseOrderGoodsPrice $orderGoodsPrice, BaseDiscountAfterDeduction $discount)
    {
        $this->discount = $discount;
        parent::__construct($orderGoodsPrice, $discount->getWeight());
    }

    function getKey()
    {
        return $this->discount->getCode();
    }

    /**
     * @return float|int|mixed
     * @throws \app\common\exceptions\AppException
     */
    function getPrice()
    {
        return $this->orderGoodsPrice->getPriceBefore($this->getKey()) - $this->discount->getAmount();
    }

}