<?php
/**
 * Created by PhpStorm.
 * Name: 商城系统
 * Author: blank
 * Profile: shop
 * Date: 2023/8/21
 * Time: 16:29
 */

namespace app\common\modules\order;


use app\common\models\Order;
use app\common\models\OrderGoods;
use app\common\models\refund\RefundApply;

class OrderDeductReturn
{
    /**
     * @var Order
     */
    protected $order;

    /**
     * @var string
     */
    protected $deduct_code;

    public function __construct(Order $order,$deduct_code)
    {
        $this->deduct_code = $deduct_code;

        $this->order = $order;
    }

    public function refundReturnTotal(RefundApply $refund)
    {

        $freightDeductionCoin = 0;
        //不是部分退款需要进行 运费抵扣返还处理
        if ($refund->part_refund && $refund->part_refund != 1) {
            $freightDeduction = $this->order->freightDeductions->where('code',$this->deduct_code)->first();
            $freightDeductionCoin = $freightDeduction ? $freightDeduction->coin : 0;
        }


        $refundOrderGoods = $refund->refundOrderGoods->map(function ($refundOrderGoods) {
            return [
                'order_goods_id' => $refundOrderGoods->order_goods_id,
                'refund_total' => $refundOrderGoods->refund_total,
            ];
        });


        $order_goods_id = $refundOrderGoods->pluck('order_goods_id')->toArray();

        $deductOrderGoods = $this->order->orderGoods
            ->whereIn('id', $order_goods_id)->filter(function (OrderGoods $orderGoods) {
                return $orderGoods->orderGoodsDeductions->where('code',$this->deduct_code)->first();
            });


        if ($deductOrderGoods->isEmpty()) {

            //没有商品抵扣，但是运费抵扣不为空则返还运费抵扣
            if ($freightDeductionCoin) {
                return $this->format(1,'运费抵扣返还', ['amount' => bankerRounding($freightDeductionCoin)]);
            }

            return $this->format(0,'退款商品里没有使用['.$this->deduct_code.']抵扣的商品', []);
        }

        $returnDeduct = 0;
        foreach ($deductOrderGoods as $itemGoods) {

            $refundOrderLog = $refundOrderGoods->where('order_goods_id', $itemGoods->id)->first();

            $deductRecord = $itemGoods->orderGoodsDeductions->where('code',$this->deduct_code)->first();


            if ($refundOrderLog && $deductRecord) {

                //全额退款
                if ($itemGoods->total == $refundOrderLog['refund_total']) {
                    $returnDeduct += $deductRecord->used_coin;
                } else {

                    //反计算出单个商品抵扣数量：抵扣总数 / 商品总数量 = 单个抵扣金额
                    //这里不能保留两位小数,不然多次退款精度会有问题
                    $deductPrice = $deductRecord->used_coin / $itemGoods->total;

                    //退款商品数量 * 单个抵扣金额 = 返还抵扣金额
                    $returnDeduct += bankerRounding($deductPrice * $refundOrderLog['refund_total']);
                }

            }
        }


        return $this->format(1,'', [
            'amount' => bankerRounding($returnDeduct + $freightDeductionCoin),
        ]);
    }

    public function closeReturnTotal()
    {

        $orderDeductTotal = $this->order->deductions->where('code',$this->deduct_code)->first();

        if (!$orderDeductTotal) {
            return $this->format(0,'订单没有使用['.$this->deduct_code.']抵扣', []);
        }

        //订单售后记录
        $afterSales = RefundApply::getAfterSales($this->order->id)->with(['refundOrderGoods'])->get();

        $refundOrderGoods = $afterSales->map(function ($refundApply) {
            return $refundApply->refundOrderGoods;
        })->collapse()->groupBy('order_goods_id')->map(function ($group, $order_goods_id) {
            return [
                'order_goods_id' => $order_goods_id,
                'refund_total' => $group->sum('refund_total'),
            ];
        });

        $order_goods_id = $refundOrderGoods->pluck('order_goods_id')->toArray();
        $deductOrderGoods = $this->order->orderGoods
            ->whereIn('id', $order_goods_id)->filter(function (OrderGoods $orderGoods) {
                return $orderGoods->orderGoodsDeductions->where('code',$this->deduct_code)->first();
            });

        if ($deductOrderGoods->isEmpty()) {
            return $this->format(1, '',[
                'amount' => bankerRounding($orderDeductTotal->coin),
            ]);
        }

        $returnDeduct = 0;
        foreach ($deductOrderGoods as $itemGoods) {

            $refundOrderLog = $refundOrderGoods->where('order_goods_id', $itemGoods->id)->first();

            $deductRecord = $itemGoods->orderGoodsDeductions->where('code',$this->deduct_code)->first();

            if ($refundOrderLog && $deductRecord) {

                //全额退款
                if ($itemGoods->total == $refundOrderLog['refund_total']) {
                    $returnDeduct += $deductRecord->used_coin;
                } else {

                    //反计算出单个商品抵扣数量：抵扣总数 / 商品总数量 = 单个抵扣金额
                    //这里不能保留两位小数,不然多次退款精度会有问题
                    $deductPrice = $deductRecord->used_coin / $itemGoods->total;

                    //退款商品数量 * 单个抵扣金额 = 返还抵扣金额
                    $returnDeduct += bankerRounding($deductPrice * $refundOrderLog['refund_total']);
                }
            }
        }
        $residue_amount = bankerRounding($orderDeductTotal->coin - $returnDeduct);

        return $this->format(1,'', [
            'amount' => max($residue_amount,0),
        ]);

    }

    protected function format($status, $msg = '', $result = [])
    {
        return ['status' => $status, 'msg' => $msg, 'data' => $result];
    }
}