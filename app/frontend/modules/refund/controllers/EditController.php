<?php
/**
 * Created by PhpStorm.
 * Author:  
 * Date: 2017/4/14
 * Time: 上午11:59
 */

namespace app\frontend\modules\refund\controllers;

use app\common\components\ApiController;
use app\common\exceptions\AppException;
use app\common\models\Order;
use app\common\models\refund\RefundGoodsLog;
use app\frontend\models\OrderGoods;
use app\frontend\modules\refund\models\RefundApply;
use app\frontend\modules\refund\services\operation\RefundEditApply;
use app\frontend\modules\refund\services\RefundOperationService;
use app\frontend\modules\refund\services\RefundService;
use Illuminate\Support\Facades\DB;
class EditController extends ApiController
{
    public function index()
    {
        $this->validate([
            'refund_id' => 'required|integer',
        ]);


        $refundApply = RefundApply::detail()->find(request()->input('refund_id'));
        if(!isset($refundApply)){
            throw new AppException('未找到该退款申请');
        }

        $order = Order::find($refundApply->order_id);
        if (!isset($order)) {
            throw new AppException('订单不存在');
        }


        $data = RefundService::editRefundApply($order);

        $data['refundApply'] = $refundApply->toArray();

        return $this->successJson('成功',$data);

    }

    //订单其他费用退款
    protected function getOrderOtherPrice($order)
    {
        return $order->fee_amount + $order->service_fee_amount;
    }


    public function storeV2()
    {
        $this->validate([
//            'reason' => 'required|string',
            'content' => 'sometimes|string',
            'refund_type' => 'required|integer',
            'refund_id' => 'required|integer'
        ],request(), []);
        $refundApply = RefundEditApply::find(request()->input('refund_id'));

        if ($refundApply->uid != \YunShop::app()->getMemberId()) {
            throw new AppException('无效申请,该订单属于其他用户');
        }

        if (!isset($refundApply)) {
            throw new AppException('退款申请不存在');
        }
        $refundApply->execute();

        if(request()->input('is_all') == 2){

            // 获取用户提交的退款商品
            $order_goods = request()->input('order_goods');

            $order_goods_ids = array_column($order_goods, 'id');

            $totalArrays = array_column($order_goods,'total','id');
            $totalRefundAmount = OrderGoods::whereIn('id', array_keys($totalArrays))->get()->sum(function ($orderGoods) use ($totalArrays) {
                return ($orderGoods->payment_amount / $orderGoods->total) * $totalArrays[$orderGoods->id];
            });

            if($refundApply->order->goods_price - $totalRefundAmount < $refundApply->order->price){
                throw new AppException('退款部分商品已经超出预付定金，请选择整单退款');
            }


            // 获取所有子订单的 refund_id
            $refundIds = \app\frontend\models\Order::where('parent_id', $refundApply->order_id)->where('refund_id','!=',0)
                ->pluck('refund_id')
                ->toArray();



            // 获取当前已有的退款商品
            $existingRefundGoods = OrderGoods::whereIn('refund_id', $refundIds)->get()->keyBy('id')->toArray();

            // 计算被移除的商品
            $previousRefundGoods = array_keys($existingRefundGoods);  // 之前的商品 ID
            $currentRefundGoods = $order_goods_ids;                   // 用户提交的商品 ID
            $removedGoods = array_diff($previousRefundGoods, $currentRefundGoods);

            // 取消没有退款商品的子订单申请
            if($removedGoods){
                foreach ($removedGoods as $goodsId) {
                    $subOrderId = $existingRefundGoods[$goodsId]['order_id'];

                    // 检查该子订单是否还有其他退款商品
                    $stillHasRefundGoods = OrderGoods::where('order_id', $subOrderId)
                        ->whereNotIn('id', $removedGoods)
                        ->exists();

                    if (!$stillHasRefundGoods) {
                        // 取消该子订单的退款申请
                        RefundOperationService::refundCancel(['refund_id'=> $existingRefundGoods[$goodsId]['refund_id']]);
                    }
                }
            }


            // 计算新增的商品
            $newAddedGoods = array_diff($currentRefundGoods, $previousRefundGoods);
            if($newAddedGoods){
                // 为新增的商品创建子订单退款申请
                foreach ($newAddedGoods as $goodsId) {
                    $subOrderId = OrderGoods::where('id', $goodsId)->value('order_id');

                    // 检查子订单是否已有退款申请
                    $refundOrderExists = \app\frontend\models\Order::where('id', $subOrderId)->first();

                    if ($refundOrderExists->refund_id == 0) {
                        // 创建新的退款申请
                        $this->applyRefund($refundOrderExists);
                    }elseif($refundOrderExists->refund_id >0){
                        $refundApplyID = RefundEditApply::find($refundOrderExists->refund_id);
                        $refundApplyID->execute();
                    }


                }
            }

            if(empty($removedGoods) && empty($newAddedGoods)){

                $orderIds = \app\frontend\models\Order::where('parent_id', $refundApply->order_id)
                    ->pluck('id')
                    ->toArray();

                $existingRefundGoodss = OrderGoods::whereIn('order_id', $orderIds)->get();
                foreach ($existingRefundGoodss as $orderGoods)
                {
                    if($orderGoods->refund_id >0){
                        $refundApplyID = RefundEditApply::find($orderGoods->refund_id);
                        $refundApplyID->execute();
                    }
                }
            }




        }else{
            $orderIds = \app\frontend\models\Order::where('parent_id', $refundApply->order_id)
                ->pluck('id')
                ->toArray();

            $existingRefundGoodss = OrderGoods::whereIn('order_id', $orderIds)->get();
            foreach ($existingRefundGoodss as $orderGoods)
            {
                if($orderGoods->refund_id >0){
                    $refundApplyID = RefundEditApply::find($orderGoods->refund_id);
                    $refundApplyID->execute();
                }else{
                    $this->applyRefund($orderGoods->order);
                }
            }
        }


        return $this->successJson('成功');
    }


    private function applyRefund($order){
        $existRefund = \app\common\models\refund\RefundApply::uniacid()
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


    public function store()
    {

        $this->validate([
//            'reason' => 'required|string',
            'content' => 'sometimes|string',
            'refund_type' => 'required|integer',
            'refund_id' => 'required|integer'
        ],request(), []);


        $refundApply = RefundEditApply::find(request()->input('refund_id'));


        if ($refundApply->uid != \YunShop::app()->getMemberId()) {
            throw new AppException('无效申请,该订单属于其他用户');
        }

        if (!isset($refundApply)) {
            throw new AppException('退款申请不存在');
        }

        $refundApply->execute();


        return $this->successJson('成功', $refundApply->toArray());
    }
}