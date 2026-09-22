<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/12/22
 * Time: 15:16
 */

namespace app\backend\modules\refund\services\operation;

use app\backend\modules\order\models\OrderGoods;
use app\backend\modules\refund\services\RefundMessageService;
use app\common\events\order\AfterOrderRefundedEvent;
use app\common\events\order\AfterOrderRefundSuccessEvent;
use app\common\models\refund\RefundProcessLog;
use app\common\modules\refund\services\RefundService;
use Illuminate\Support\Facades\DB;
/**
 * 确认退款
 * Class RefundComplete
 * @package app\backend\modules\refund\services\operation
 */
class RefundPartial extends RefundOperation
{

    protected $statusBeforeChange = [
        self::WAIT_CHECK,
    ];
    protected $statusAfterChanged = self::COMPLETE;
    protected $name = '退款部分商品';
    protected $timeField = 'refund_time';

    protected function afterEventClass()
    {
        //event(new AfterOrderRefundedEvent($this->order));
        return new AfterOrderRefundSuccessEvent($this);
    }

    protected function updateBefore()
    {
        $this->price = $this->getRequest()->input('refund_custom_money')?:$this->price;
    }


    protected function updateAfter()
    {

        $this->updateOrderGoodsRefundStatus();



    }

    //必须要触发完退款事件，才订单关闭
    protected function triggerEventAfter()
    {
        //更新订单支付记录状态
        if ($this->order->hasOneOrderPay) {
            $this->order->hasOneOrderPay->refund();
        }

        if ($this->isPartRefundV2()) {
            if($this->order->parent_id >0){
                $existsOrderGoods =  OrderGoods::where('order_id',$this->order->id)->where('refund_success',0)->exists();
                if(!$existsOrderGoods){
                   // $this->cancelRefund();
                    $this->closeOrder();

                }
            }


        } else {
            $this->closeOrder();
        }

    }

    protected function writeLog()
    {
        $detail = [
            $this->getRefundTypeName()[$this->refund_type].'完成',
            $this->refund_type == self::REFUND_TYPE_REFUND_MONEY ? '商家确认退款':'商家确认收货',
        ];
        $processLog = RefundProcessLog::logInstance($this, RefundProcessLog::OPERATOR_SHOP);
        $processLog->setAttribute('operate_type', RefundProcessLog::OPERATE_REFUND_COMPLETE);
        $processLog->saveLog($detail);
    }

    protected function sendMessage()
    {
        RefundMessageService::passMessage($this);//通知买家

        if (app('plugins')->isEnabled('instation-message')) {
            event(new \Yunshop\InstationMessage\event\OrderRefundSuccessEvent($this));
        }
    }
}