<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/8/9
 * Time: 14:57
 */

namespace app\common\modules\refund\optional;

use app\common\models\Order;
use app\common\models\refund\RefundApply;

class ExchangeGoods  extends OptionalTypesAbstract
{
    public function enable()
    {

        return !$this->getOrder()->isVirtual() && $this->getOrder()->status >= Order::WAIT_RECEIVE;
    }

    public function getName()
    {
        return '换货';
    }

    public function getValue()
    {
        return  RefundApply::REFUND_TYPE_EXCHANGE_GOODS;
    }

    public function getIcon()
    {
        return 'icon-fontclass-daifahuo';
    }

    public function getDesc()
    {
        return '已收到货，需要更换';
    }

    public function notReceived()
    {
        return [];
    }

    public function received()
    {
        return [];
    }
}