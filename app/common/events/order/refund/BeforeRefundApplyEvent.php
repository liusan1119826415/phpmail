<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/7/28
 * Time: 9:40
 */

namespace app\common\events\order\refund;

use app\common\events\Event;
use app\common\models\refund\RefundApply;

/**
 * 退款申请前事件
 * Class BeforeRefundApplyEvent
 * @package app\common\events\order\refund
 */
class BeforeRefundApplyEvent extends Event
{
    /**
     * @var RefundApply
     */
    protected $refund;

    public function __construct($refund)
    {
        $this->refund = $refund;
    }

    /**
     * @return RefundApply
     */
    public function getModel()
    {
        return $this->refund;
    }

}