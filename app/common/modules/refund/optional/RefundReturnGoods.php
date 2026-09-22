<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/8/9
 * Time: 14:55
 */

namespace app\common\modules\refund\optional;


use app\common\models\Order;
use app\common\models\refund\RefundApply;

class RefundReturnGoods extends OptionalTypesAbstract
{
    public function enable()
    {
        return !$this->getOrder()->isVirtual() && $this->getOrder()->status >= Order::WAIT_RECEIVE;
    }

    public function getName()
    {
        return '退款退货';
    }

    public function getValue()
    {
        return  RefundApply::REFUND_TYPE_RETURN_GOODS;
    }

    public function getIcon()
    {
        return 'icon-fontclass-daishouhuo';
    }

    public function getDesc()
    {
        return '已收到货，需要退款退货';
    }

    public function notReceived()
    {
        return [];
    }

    public function received()
    {
        return [
            '包装或商品破损、少商品',
            '质量问题',
            '配送问题',
            '拍错/多拍/不想要',
            '其他原因（可填）',
        ];
    }
}