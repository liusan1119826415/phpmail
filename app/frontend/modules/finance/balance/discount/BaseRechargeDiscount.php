<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/18
 * Time: 14:09
 */

namespace app\frontend\modules\finance\balance\discount;


use app\frontend\modules\finance\balance\PreBalanceRechargeDiscount;
use app\frontend\modules\finance\balance\PriceCalculator;

abstract class BaseRechargeDiscount
{
    /**
     * @var PriceCalculator
     */
    protected $calculator;
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

    public function __construct(PriceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }
    public function getCode()
    {
        return $this->code;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getId()
    {
        return 0;
    }

    public function getDesc()
    {
        return '';
    }

    /**
     * @return bool
     */
    public function calculated()
    {
        return isset($this->amount);
    }

    public function preSave()
    {
        return true;
    }


    /**
     * 获取总金额
     * @return float
     */
    public function getAmount()
    {
        if (isset($this->amount)) {
            return $this->amount;
        }


        $this->amount = $this->_getAmount();
        if($this->amount && $this->preSave()){
            // 将抵扣总金额保存在订单优惠信息表中
            //统一算法记录金额保存算法为：四舍六入五成双
            $preOrderDiscount = new PreBalanceRechargeDiscount([
                'amount' => bankerRounding($this->amount),
                'code' => $this->getCode(),
                'discount_id' => $this->getId(),
                'name' => $this->getName(),
                'desc' => $this->getDesc(),
            ]);
            $preOrderDiscount->setBalanceRecharge($this->calculator->balanceRecharge);
        }
        return $this->amount;
    }

    /**
     * 获取金额
     * @return int
     */
    abstract protected function _getAmount();

}