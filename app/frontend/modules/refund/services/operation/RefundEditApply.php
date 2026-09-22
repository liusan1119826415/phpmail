<?php
/**
 * Created by PhpStorm.
 *
 * 
 *
 * Date: 2021/12/24
 * Time: 18:28
 */

namespace app\frontend\modules\refund\services\operation;


use app\backend\modules\refund\services\operation\RefundOperation;
use app\common\events\order\BeforeOrderRefundChangeEvent;
use app\common\events\order\OrderRefundApplyEditEvent;
use app\common\exceptions\AppException;
use app\common\models\OrderGoods;
use app\common\models\refund\RefundProcessLog;
use app\Jobs\RefundJob;

class RefundEditApply extends RefundOperation
{
//    protected $statusAfterChanged = self::WAIT_CHECK;
    protected $name = '修改申请';

    protected $editData;

    protected $refundGoods;

    protected function operationValidate()
    {
        if(in_array($this->status,[6,7])){
             throw new AppException("订单已经退款成功");
        }
    }

    protected function afterEventClass()
    {
        return new OrderRefundApplyEditEvent($this);
    }

    protected function updateBefore()
    {
        $refundApplyData = $this->getRequest()->only([
            'reason', 'content', 'refund_type','receive_status','refund_way_type','freight_price','other_price',
        ]);

        event(new BeforeOrderRefundChangeEvent($this,$refundApplyData));

        if (is_array($this->getRequest()->input('images'))) {
            $refundApplyData['images'] = $this->getRequest()->input('images');
        } else {
            $refundApplyData['images'] = $this->getRequest()->input('images') ? json_decode($this->getRequest()->input('images'), true):[];
        }

        if (isset($refundApplyData['freight_price'])) {
            $this->freight_price = $refundApplyData['freight_price'];
        }

        if (isset($refundApplyData['other_price'])) {
            $this->other_price = $refundApplyData['other_price'];
        }

        //退款总金额,换货售后退款金额为0
        /*$price = bcadd($this->apply_price, ($this->other_price +  $this->freight_price),2);
        if($this->getRequest()->input('is_all') == 2){
            return $this->order->price;
        }else{
            $this->price = min($this->order->price, $price);
        }*/


        //$this->is_all =  $this->getRequest()->input('is_all');
        $this->editData = $refundApplyData;

        $this->service_status = request()->input('service_status')?:1;

        $this->fill($refundApplyData);
        $this->backWay()->init($this);  //走退货方式验证
    }

    protected function updateAfter()
    {

        if ($this->getRefundGoods()) {
            $this->updateOrderGoodsRefundLog($this->getRefundGoods());
        }

        $this->setBackWay();    //保存退货方式内容
    }


    protected function getRefundGoods()
    {
        if (isset($refundGoods)) {
            return $this->refundGoods;
        }

        $refundGoods = $this->requestRefundGoods();

        if (!$refundGoods) {
            $refundGoods = $this->order->orderGoods->map(function (OrderGoods $orderGoods) {
                //已退款金额
                $refundedAmount = $orderGoods->manyRefundedGoodsLog->sum('refund_price');
                //已退款数量
                $refundedTotal = $orderGoods->getRefundTotal();

                return [
                    'id'=> $orderGoods->id,
                    'total'=> max($orderGoods->total - $refundedTotal,0),
                    'refund_price'=> max($orderGoods->payment_amount - $refundedAmount,0),
                ];
            })->values()->all();
        } else {
            $filteredGoods = array_filter($refundGoods, function($item) {
                return $item['order_id'] == $this->order->id;
            });
            $totalArrays = array_column($filteredGoods,'total','id');
            $refundGoods = $this->order->orderGoods->whereIn('id',array_keys($totalArrays))->map(function ($orderGoods) use ($totalArrays) {
                $refund_price = ($orderGoods->payment_amount / $orderGoods->total) * $totalArrays[$orderGoods->id];
                return ['id'=> $orderGoods->id, 'total'=>$totalArrays[$orderGoods->id],'refund_price'=> $refund_price];
            })->values()->all();

        }
        /*if(!in_array($this->order->order_type,[2,3,4])){
            if (!$refundGoods) {
                throw new AppException('无商品可售后');
            }
        }*/




        return $this->refundGoods = $refundGoods;
    }


    public function requestRefundGoods()
    {

        if (is_array($this->getRequest()->input('order_goods'))) {
            $refundGoods = $this->getRequest()->input('order_goods');
        } else {
            $refundGoods = json_decode($this->getRequest()->input('order_goods'), true);
        }

        return $refundGoods;
    }


    protected function writeLog()
    {
        $detail = [
            '售后类型：'.  $this->getRefundTypeName()[$this->refund_type],
            $this->refund_type == static::REFUND_TYPE_EXCHANGE_GOODS ? '': '退款金额：'.$this->price,
            $this->editData['freight_price']?'修改运费:'.$this->editData['freight_price'] :'',
            $this->editData['other_price']?'修改其他费用:'.$this->editData['other_price'] :'',
            '售后原因：'.$this->reason,
            '说明：'.$this->content,
        ];

        $processLog = RefundProcessLog::logInstance($this, RefundProcessLog::OPERATOR_MEMBER);
        $processLog->setAttribute('operate_type', RefundProcessLog::OPERATE_CHANGE_APPLY);
        $remark = request()->input();
        $processLog->saveLog($detail,$remark);

//        dispatch(new RefundJob($this->uniacid, request()->input('is_all'), $this->id))
//            ->delay(now()->addMinutes(2));
        //dispatch(new RefundJob($this->uniacid, 1, $this->id));
    }
}