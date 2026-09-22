<?php

namespace app\frontend\modules\refund\controllers;

use app\common\components\ApiController;
use app\common\events\order\OrderRefundApplyDataEvent;
use app\common\events\order\OrderRefundApplyEvent;
use app\common\exceptions\AppException;
use app\common\models\refund\RefundApply;
use app\common\services\SystemMsgService;
use app\framework\Http\Request;
use app\frontend\models\Order;
use app\frontend\models\OrderGoods;
use app\frontend\modules\refund\services\RefundService;
use app\frontend\modules\refund\services\RefundMessageService;
use app\frontend\modules\order\services\MiniMessageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/4/12
 * Time: 下午4:24
 */
class ApplyController extends ApiController
{

    protected function getOrder()
    {
        return Order::select(['id', 'status', 'plugin_id', 'is_plugin', 'is_virtual', 'goods_price', 'order_goods_price', 'price', 'refund_id',
            'dispatch_price', 'fee_amount', 'service_fee_amount', 'pay_time', 'no_refund','order_type'])
            ->with(['orderGoods']);
    }


    public function index(Request $request)
    {

        $this->validate([
            'order_id' => 'required|integer'
        ]);
        $order = $this->getOrder()->find($request->input('order_id'));
        if (!isset($order)) {
            throw new AppException('订单不存在');
        }

        if ($order->refund_id) {

            return (new  DetailController())->getDetail($order->refund_id);
            // throw new AppException('已存在售后申请，处理中');
        }

        $data = RefundService::refundApplyData($order);


        event(($event = new OrderRefundApplyDataEvent($data)));

        return $this->successJson('成功', $event->getData());

//        //处理订单可退款商品数量
//        $order->orderGoods->map(function ($orderGoods) {
//            $orderGoods->refundable_total = $orderGoods->total - $orderGoods->after_sales['refunded_total'];
//            $orderGoods->unit_price = bankerRounding($orderGoods->payment_amount / $orderGoods->total);
//        });
//
//
//        $refundTypes = RefundService::getOptionalType($order);
//
//        $data = compact('order','refundTypes');
//
//        $refundedPrice = \app\common\models\refund\RefundApply::getAfterSales($order->id)->get();
//
//
//        $orderOtherPrice = $this->getOrderOtherPrice($order);
//
//        //这里减去运费和其他费用是因为前端直接拿这个字段当订单金额，但是售后现在把运费分离出来了。
//        $data['order']['price'] = max($order->price - $order->dispatch_price - $orderOtherPrice,0);
//
//        //可退运费
//        $data['refundable_freight'] = max(bcsub($order->dispatch_price, $refundedPrice->sum('freight_price'),2),0);
//        //订单可退其他费用
//        $data['refundable_other'] = max(bcsub($orderOtherPrice, $refundedPrice->sum('other_price'),2),0);
//
//        //支持部分退款的订单类型，平台订单，供应商订单，中台供应链
//        $data['support_batch'] = in_array($order->plugin_id, [0,92,120]);
//
//        $data['send_back_way'] = RefundService::getSendBackWay($order);
//
//        event(($event = new OrderRefundApplyDataEvent($data)));
//
//        return $this->successJson('成功', $event->getData());
    }

    //订单其他费用退款
    protected function getOrderOtherPrice($order)
    {

        return $order->fee_amount + $order->service_fee_amount;
    }


    public function storeV2(Request $request)
    {

        $this->validate([
            'reason' => 'required|string',
            'content' => 'sometimes|string',
            'refund_type' => 'required|integer',
            'order_id' => 'required|integer',
        ], $request, [
            'reason.required' => '退款原因未选择',
            'refund_type.required' => '退款方式未选择',
        ]);

        $order = Order::find($request->input('order_id'));
        if (!isset($order)) {
            throw new AppException('订单不存在');
        }
        if ($order->uid != \YunShop::app()->getMemberId()) {
            throw new AppException('无效申请,该订单属于其他用户');
        }
        if(in_array($order->order_type,[1,2,3,4])){
            if ($order->status < Order::WAIT_SEND) {
                throw new AppException('订单未付款,无法退款');
            }
        }

        $this->applyRefund($order);
        //如果是部分商品退款，检查是哪些订单
       /* if($request->input('is_all') == 2){
            $order_goods = $request->input('order_goods');
            $orderGoodsIds = array_column($order_goods,'id');
            $totalArrays = array_column($order_goods,'total','id');

            $totalRefundAmount = OrderGoods::whereIn('id', array_keys($totalArrays))->get()->sum(function ($orderGoods) use ($totalArrays) {
                return ($orderGoods->payment_amount / $orderGoods->total) * $totalArrays[$orderGoods->id];
            });

            if($order->goods_price - $totalRefundAmount < $order->price){
                throw new AppException('退款部分商品已经超出预付定金，请选择整单退款');
            }




            $orderGoodss = OrderGoods::whereIn('id',$orderGoodsIds)->get();
            $orderGoodss->map(function ($orderGoods){

                $this->applyRefund($orderGoods->order);
            });

        }else{*/
        if($order->order_type == 1){

                $orders = Order::where('parent_id',$order->id)->whereIn('status',[1,2,3])->get();
                $orders->map(function ($orderItem){
                    $this->applyRefund($orderItem);
                });

            //$this->checkHour72Supplier($order,$orders);

        }


      //  }



        $order->refund_status = 1;
        $order->save();




        return $this->successJson('成功');

    }



    protected function checkHour72Supplier($order,$orders)
    {
        $now = Carbon::now();
        $orders->map(function ($orderItem) use($now){
            $threeDaysLater = $orderItem->first_pay_time->copy()->addHours(72);
            if($orderItem->pay_type_id == 16 && !$now->lessThan($threeDaysLater)){
                $status = 1;
                $orderItem->hasOneRefundApply->status = $status;
                $orderItem->hasOneRefundApply->progress_status = $status;
                $orderItem->hasOneRefundApply->supplier_pass_time = time();
                $orderItem->hasOneRefundApply->save();
            }
        });

        $threeDaysLater = $order->first_pay_time->copy()->addHours(72);

        if($order->pay_type_id == 16 && !$now->lessThan($threeDaysLater)){
                $status = 1;
                $order->hasOneRefundApply->status = 0;
                $order->hasOneRefundApply->progress_status = $status;
                $order->hasOneRefundApply->supplier_pass_time = time();
                $order->hasOneRefundApply->save();
        }
    }


    private function applyRefund($order){
        $existRefund = RefundApply::uniacid()
            ->where('order_id', $order->id)
            ->where('status', '>=', RefundApply::WAIT_CHECK)
            ->where('status', '<', RefundApply::COMPLETE)->count();

        if ($existRefund) {
            throw new AppException('申请已提交,处理中');
        }

        //防止重复操作产出多条申请记录
        $restrictAccess = \app\common\services\RequestTokenService::limitRepeat('f_order_refund' . $order->id);
        if (!$restrictAccess) {
            throw new AppException('检测到重复提交，请刷新页面重新申请');
        }

        $refundApply = new \app\frontend\modules\refund\services\operation\RefundApply();
        $refundApply->setRelation('order', $order);

        $refundApply->relatedPluginOrder()->applySubmitValidate();

        DB::transaction(function () use ($refundApply) {
            $refundApply->execute();
        });
    }


    public function store(Request $request)
    {
        $this->validate([
//            'reason' => 'required|string',
            'content' => 'sometimes|string',
            'refund_type' => 'required|integer',
            'order_id' => 'required|integer',
        ], $request, [
            'reason.required' => '退款原因未选择',
            'refund_type.required' => '退款方式未选择',
        ]);


        $order = Order::find($request->input('order_id'));
        if (!isset($order)) {
            throw new AppException('订单不存在');
        }
        if ($order->uid != \YunShop::app()->getMemberId()) {
            throw new AppException('无效申请,该订单属于其他用户');
        }
        if ($order->status < Order::WAIT_SEND) {
            throw new AppException('订单未付款,无法退款');
        }


//        if ($order->hasOneRefundApply && $order->hasOneRefundApply->isRefunding()) {
//            throw new AppException('申请已提交,处理中');
//        }

        $existRefund = RefundApply::uniacid()
            ->where('order_id', $order->id)
            ->where('status', '>=', RefundApply::WAIT_CHECK)
            ->where('status', '<', RefundApply::COMPLETE)->count();

        if ($existRefund) {
            throw new AppException('申请已提交,处理中');
        }

        //防止重复操作产出多条申请记录
        $restrictAccess = \app\common\services\RequestTokenService::limitRepeat('f_order_refund' . $order->id);
        if (!$restrictAccess) {
            throw new AppException('检测到重复提交，请刷新页面重新申请');
        }


        $refundApply = new \app\frontend\modules\refund\services\operation\RefundApply();
        $refundApply->setRelation('order', $order);

        $refundApply->relatedPluginOrder()->applySubmitValidate();
        $order->refund_status = 1;
        $order->save();
        DB::transaction(function () use ($refundApply) {
            $refundApply->execute();
        });

        return $this->successJson('成功', $refundApply->toArray());

    }

    public function getRefundData(Request $request)
    {
        $this->validate([
            'order_id' => 'required|integer'
        ]);
        $order = $this->getOrder()->find($request->input('order_id'));

        $data = RefundService::refundApplyData($order);
    }
}