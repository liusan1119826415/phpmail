<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/4/22
 * Time: 14:28
 */

namespace app\frontend\modules\finance\listeners;

use app\common\events\order\AfterOrderRefundSuccessEvent;
use app\common\modules\order\OrderDeductReturn;
use app\common\services\credit\ConstService;
use app\common\services\finance\BalanceChange;
use Illuminate\Foundation\Bus\DispatchesJobs;
use app\common\events\order\AfterOrderCanceledEvent;

class OrderDeductionRollback
{
    use DispatchesJobs;

    /**
     * @var
     */
    private $orderModel;

    public function subscribe($events)
    {
        // 订单关闭 抵扣回滚
        $events->listen(
            AfterOrderCanceledEvent::class, self::class. '@orderCancel'
        );

        /**
         *
         * 订单退款成功,抵扣回滚
         */
        $events->listen(AfterOrderRefundSuccessEvent::class, self::class. '@refundReturn');

    }


    public function orderCancel(AfterOrderCanceledEvent $event)
    {

        $this->orderModel = $event->getOrderModel();

        //订单关闭返还抵扣的余额
        $this->orderBalanceRollback();

    }

    public function refundReturn(AfterOrderRefundSuccessEvent $event)
    {
        $this->orderModel = $event->getOrderModel();

        $refund = $event->getModel();

        $this->orderRefundBalance($refund);

    }

    protected function orderRefundBalance($refund)
    {
        if (!\Setting::get('finance.balance.balance_deduct_rollback')) {
            return;
        }

        $result = (new OrderDeductReturn($this->orderModel, 'balance'))->refundReturnTotal($refund);

        if ($result['status'] && $result['data']['amount']) {

            $this->balanceRollback($result['data']['amount'],$refund->refund_sn, "订单[{$this->orderModel->order_sn}]退款[{$refund->refund_sn}],返还余额抵扣");
        } else {
            \Log::debug($result['msg'], [$this->orderModel->order_sn, $refund->id]);
        }
    }

    protected function orderBalanceRollback()
    {
        if (!\Setting::get('finance.balance.balance_deduct_rollback')) {
            return;
        }

        //存在售后关联记录关闭的默认是订单退款完成关闭的不需要再次进行处理
        if (!$this->orderModel->refund_id) {

            $result = (new OrderDeductReturn($this->orderModel, 'balance'))->closeReturnTotal();
            if ($result['status'] && $result['data']['amount']) {

                $this->balanceRollback($result['data']['amount'],$this->orderModel->order_sn);

            } else {
                \Log::debug($result['msg']."-{$this->orderModel->order_sn}",$result['data']);
            }

        }
    }

    protected function balanceRollback($coin,$relation, $note = '')
    {
        $data = [
            'member_id' => $this->orderModel->uid,
            'remark' => $note?: '订单['.$this->orderModel->order_sn.']关闭，返还余额抵扣余额' . $coin,
            'relation' =>  $relation,
            'operator' => ConstService::OPERATOR_ORDER,
            'operator_id' =>  $this->orderModel->id,
            'change_value' => $coin,
        ];
        $result = (new BalanceChange())->cancelDeduction($data);
        return $result;
    }
}
