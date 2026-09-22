<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/8/9
 * Time: 14:42
 */

namespace app\common\modules\refund\optional;


use app\common\models\Order;
use app\common\modules\refund\product\RefundOrderTypeBase;

abstract class OptionalTypesAbstract implements OptionalTypesInterface
{

    protected $order;


    protected $afterSalesService;

    /**
     * @param Order $order
     * @param RefundOrderTypeBase $orderType
     */
    public function __construct(Order $order, $afterSalesService = null)
    {
        $this->order = $order;

        $this->afterSalesService = $afterSalesService;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getRefundService()
    {
        return $this->afterSalesService;
    }
}