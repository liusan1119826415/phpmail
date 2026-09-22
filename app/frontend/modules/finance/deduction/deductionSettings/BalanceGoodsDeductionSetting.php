<?php
/**
 * Created by PhpStorm.
 * 
 *
 *
 * Date: 2021/6/15
 * Time: 11:02
 */

namespace app\frontend\modules\finance\deduction\deductionSettings;

use app\frontend\modules\deduction\DeductionSettingInterface;

class BalanceGoodsDeductionSetting implements DeductionSettingInterface
{
    //这个开关是获取商品设置的值是回去统一设置的

    public function getWeight()
    {
        return 10;
    }

    /**
     * @var \app\frontend\models\goods\Sale
     */
    private $setting;

    function __construct($goods)
    {
        $defaultSale= new \app\common\models\Sale(['goods_id'=>164]);
        $this->setting = $goods->hasOneSale?:$defaultSale;

    }

    // todo 将运费抵扣分离出去
    public function isEnableDeductDispatchPrice()
    {
        return false;
    }

    public function isMaxDisable()
    {
        return !\Setting::get('finance.balance.balance_deduct') || empty($this->setting->balance_deduct);

    }

    public function isMinDisable()
    {
        return !\Setting::get('finance.balance.balance_deduct') || empty($this->setting->balance_deduct);
    }

    /**
     * 不抵扣运费
     * @return bool
     */
    public function isDispatchDisable()
    {
        return true;
    }

    public function getMaxFixedAmount()
    {
        return $this->setting->max_balance_deduct?:false;
    }

    public function getMaxPriceProportion()
    {
        return $this->setting->max_balance_deduct?:false;
    }

    public function getMaxDeductionType()
    {
        if (empty($this->setting->max_balance_deduct)) {
            return false;
        }
        if ($this->setting->balance_deduct_type == 1) {
            return 'GoodsPriceProportion';
        }
        return 'FixedAmount';
    }


    public function getMinDeductionType()
    {
        if (empty($this->setting->min_balance_deduct)) {
            return false;
        }
        if ($this->setting->balance_deduct_type == 1) {
            return 'GoodsPriceProportion';
        }
        return 'FixedAmount';
    }

    public function getMinFixedAmount()
    {
        return $this->setting->min_balance_deduct?:false;
    }

    public function getMinPriceProportion()
    {
        return $this->setting->min_balance_deduct?:false;
    }


    public function getDeductionAmountType()
    {
        return false;
    }

    public function getAffectDeductionAmount()
    {
        return false;
    }
}
