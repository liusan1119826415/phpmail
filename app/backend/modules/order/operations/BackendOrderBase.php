<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/2/25
 * Time: 17:23
 */

namespace app\backend\modules\order\operations;

use app\backend\modules\order\services\type\OrderTypeFactory;
use app\common\models\Order;
use app\frontend\modules\order\operations\OrderOperationInterface;

abstract class BackendOrderBase implements OrderOperationInterface
{
    const ADMIN_PAY = 1;
    const ADMIN_SEND = 2;
    const ADMIN_RECEIVE = 3;
    const ADMIN_CLOSE = -1;

    const ADMIN_UPLOAD = 4;

    const ADMIN_CHANGE_PRICE = 5;

    const UPDATE_SEND = 11;

    /**
     * @var Order
     */
    protected $order;

    protected $orderType;

    /**
     * BackendOrderOperationBase constructor.
     * @param Order $order
     * @param OrderTypeFactory $orderType
     */
    public function __construct(Order $order, OrderTypeFactory $orderType)
    {
        $this->order = $order;

        $this->orderType = $orderType;
    }
}