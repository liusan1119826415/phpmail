<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2024/6/14
 * Time: 9:36
 */

namespace app\frontend\modules\finance\balance\pipes;

use app\frontend\modules\finance\balance\PriceCalculator;
use app\frontend\modules\order\PriceNode;

class InitialPricePipe extends PriceNode
{
    protected $weight;

    /**
     * @var PriceCalculator
     */
    protected $calculator;

    public function __construct(PriceCalculator $calculator, $weight)
    {
        $this->calculator = $calculator;

        parent::__construct($weight);
    }

    public function getKey()
    {
        return 'initialPrice';
    }


    /**
     * @return mixed
     */
    public function getPrice()
    {
        return $this->calculator->getInitialRechargeAmount();
    }
}