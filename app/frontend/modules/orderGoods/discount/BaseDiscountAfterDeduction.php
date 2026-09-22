<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/5/23
 * Time: 下午3:55
 */

namespace app\frontend\modules\orderGoods\discount;

use app\frontend\models\orderGoods\PreOrderGoodsDiscount;
use app\frontend\models\orderGoods\PreOrderGoodsDiscountAfterDeduction;
use app\frontend\modules\order\models\PreOrder;
use app\common\modules\orderGoods\models\PreOrderGoods;

abstract class BaseDiscountAfterDeduction
{
    /**
     * @var PreOrder
     */
    protected $orderGoods;
    /**
     * 优惠名
     * @var string
     */
    protected $name;
    /**
     * 优惠码
     * @var
     */
    protected $code;
    /**
     * @var float
     */
    private $amount;
    protected $weight;

    public function __construct(PreOrderGoods $orderGoods)
    {
        $this->orderGoods = $orderGoods;
    }

    public function setWeight($weight)
    {
        $this->weight = $weight;
    }

    public function getWeight()
    {
        return $this->weight;

    }

    public function getCode()
    {
        return $this->code;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * 获取总金额
     * @return float|int
     */
    public function getAmount()
    {
        if (isset($this->amount)) {
            return $this->amount;
        }

        $this->amount = $this->_getAmount();
        if ($this->amount) {
            // 将抵扣总金额保存在订单优惠信息表中
            $preOrderGoodsDiscount = new PreOrderGoodsDiscountAfterDeduction([
                'discount_code' => $this->getCode(),
                'amount' => $this->amount ?: 0,
                'name' => $this->getName(),
            ]);
            $preOrderGoodsDiscount->setOrderGoods($this->orderGoods);
        }

        return $this->amount ?: 0;
    }

    /**
     * 订单优惠已分摊优惠金额
     * @return mixed
     */
    protected function orderGoodsDiscountTotal()
    {
        return $this->orderGoods->order->orderGoods->sum(function (PreOrderGoods $preOrderGoods) {

            $discount = $preOrderGoods->getOrderGoodsDiscounts()->where('discount_code',$this->getCode())->first();

            return $discount?$discount->amount : 0;
        });
    }

    /**
     * 订单是否存在该种优惠
     * @return bool
     */
    protected function orderDiscountCalculated()
    {
        return $this->orderGoods->order->getDiscount()->getAmountByCode($this->getCode())->calculated();
    }

    /**
     * @return float
     */
    abstract protected function _getAmount();

}