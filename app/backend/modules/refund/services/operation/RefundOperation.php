<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2021/12/22
 * Time: 13:58
 */

namespace app\backend\modules\refund\services\operation;

use app\common\events\Event;
use app\common\models\Order;
use app\common\models\OrderGoods;
use app\common\models\project\OrderStage;
use app\common\models\refund\RefundGoodsLog;
use app\common\modules\refund\RefundOrderFactory;
use app\common\modules\refund\services\RefundService;
use app\framework\Http\Request;
use app\frontend\modules\refund\services\RefundBackWayService;
use Illuminate\Support\Facades\DB;
use app\common\exceptions\AppException;
use app\common\models\refund\RefundApply;
use Carbon\Carbon;
abstract class RefundOperation  extends RefundApply
{

    protected $transaction = false;

    protected $statusBeforeChange = [];
    //改变后状态
    protected $statusAfterChanged;
    protected $name = ''; //操作名称
    protected $timeField = ''; //操作时间


    protected $refundOrderType;//售后订单类型

    protected $port_type = 'frontend'; //frontend = 前端、backend = 后端

    public $params;//参数，暂时没用

    protected $backWay;

    /**
     * 表更新后
     */
    abstract protected function updateAfter();


    /**
     * 操作日志记录
     */
    abstract protected function writeLog();

    /**
     * 表更新前
     */
    abstract protected function updateBefore();

    /**
     * 发送通知
     */
    protected function sendMessage() {}


    /**
     * 退款申请操作前事件
     * @return Event|array|null
     */
    protected function beforeEventClass()
    {
        return null;
    }
    /**
     * 退款操作后事件
     * @return Event|array|null
     */
    protected function afterEventClass()
    {
        return null;
    }

    /**
     * 必须要在触发完退款操作事件，才能去操作订单。
     * 因为订单状态改变会触发订单事件
     */
    protected function triggerEventAfter()
    {

    }

    /**
     * 更新申请表
     * @return bool
     */
    protected function updateTable() {
        if (isset($this->statusAfterChanged)) {
            $this->status = $this->statusAfterChanged;
        }
        if(!empty($this->timeField)){
            $timeFields = $this->timeField;
            $this->$timeFields = time();
        }
        return $this->save();
    }

    /**
     * 操作验证
     * @throws AppException
     */
    protected function operationValidate()
    {
        if (!empty($this->statusBeforeChange) && !in_array($this->status, $this->statusBeforeChange)) {
            throw new AppException($this->status_name . '的退款申请,无法执行' . $this->name . '操作');
        }
    }

    protected function backWay()
    {
        if (!isset($this->backWay)) {
            $this->backWay = RefundBackWayService::getBackWayClass($this->refund_way_type);
            $this->backWay->setRefundApply($this);
        }
        return $this->backWay;
    }

    protected function setBackWay()
    {
        $this->backWay()->saveRelation();
    }

    /***
     * 执行操作操作
     * @return bool
     * @throws AppException
     */
    final public function execute()
    {
        $this->operationValidate(); //验证

        $this->updateBefore(); //更新表之前

        $this->triggerEvent($this->beforeEventClass()); //更新前事件

        $result = $this->updateTable();//更新表
        if (!$result) {
            throw new AppException('信息更新失败');
        }

        $this->updateAfter();//更新表之后
       //是否退款部分商品数据

        $this->writeLog(); //写入售后协商记录表

        $this->triggerEvent($this->afterEventClass()); //更新后事件

        $this->triggerEventAfter();//事件触发后需要进行的操作

      //  $this->sendMessage(); //发送通知

        return $result;
    }




    /**
     * 发布监听
     */
    final protected function triggerEvent($eventClass)
    {
        if (is_array($eventClass)) {
            foreach ($eventClass as $itemEvent) {
                if (!is_null($itemEvent) && ($itemEvent instanceof Event)) {
                    event($itemEvent);
                }
            }

        } else {
            if (!is_null($eventClass) && ($eventClass instanceof Event)) {
                event($eventClass);
            }
        }

    }

    final public function setParams($request)
    {
        $this->params = $request;
    }

    final public function getParam($key)
    {
        return array_get($this->params, $key, '');
    }

    final public function getParams()
    {
        return $this->params;
    }

    final public function getRequest()
    {
//        if (!isset($this->request)) {
//            $this->request = request();
//        }
        return request();
    }


    //对应的订单
    final public function relatedPluginOrder()
    {
        if (!isset($this->refundOrderType)) {
            $this->refundOrderType = RefundOrderFactory::getInstance()->getRefundOrder($this->order, $this->port_type);
        }

        return $this->refundOrderType;
    }

    /**
     * 售后申请记录退款商品
     * @param $refundGoods
     */
    protected function createOrderGoodsRefundLog($refundGoods)
    {

        $order_goods_ids = array_column($refundGoods, 'id');

        OrderGoods::whereIn('id', $order_goods_ids)->update(['refund_id' => $this->id]);

        foreach ($refundGoods as $goodsItem) {
            RefundGoodsLog::saveData($this,$goodsItem);

        }
    }


    /**
     * 售后修改退款商品
     * @param $refundGoods
     */
    protected function updateOrderGoodsRefundLog($refundGoods)
    {
        $order_goods_ids = array_column($refundGoods, 'id');
        OrderGoods::whereIn('id', $order_goods_ids)->update(['refund_id' => $this->id]);

        OrderGoods::whereNotIn('id', $order_goods_ids)->where('refund_id',$this->id)->update(['refund_id' => 0]);
        RefundGoodsLog::whereNotIn('order_goods_id',$order_goods_ids)->where('refund_id',$this->id)->delete();
        foreach ($refundGoods as $goodsItem) {
                RefundGoodsLog::saveData($this,$goodsItem);
        }

    }

    /**
     * 退款完成、换货完成
     * 更新订单商品表售后状态
     */
    protected function updateOrderGoodsRefundStatus()
    {

        //不是换货售后商品需要标识下退过款
        if ($this->refund_type != self::REFUND_TYPE_EXCHANGE_GOODS) {
            //is_refund 字段现在作用只是标记下该商品售后过
            $updateData['is_refund'] =  DB::raw('is_refund + 1');
        }
        $updateData['refund_id'] = 0;
        if($this->isPartRefundV2()){
            if($this->order->status == 1 && in_array($this->order->orderStatus,[1,2,3])){
               if($this->order->parent_id == 0){
                   $refundIds = Order::where('parent_id',$this->order->id)->pluck('refund_id')->toArray();
                   $refundGoodsLogs = RefundGoodsLog::whereIn('refund_id', $refundIds)->get();

                   if($refundGoodsLogs){
                       $order_total_price = 0;
                       $order_total = 0;
                       foreach ($refundGoodsLogs as $log) {


                           $order_total_price += $log->refund_price;
                           $order_total +=$log->refund_total;
                           if (!$log->orderGoods) {
                               continue; // 跳过不存在的记录
                           }

                           if ($log->refund_total >= $log->orderGoods->total) {
                               // 退款数量等于或超过订单商品数量，直接删除
                            //   $log->orderGoods->total -= $log->refund_total;
                               $log->orderGoods->refund_success = 1;
//                               $log->orderGoods->price = max(0, $log->orderGoods->price - $log->refund_price);
//                               $log->orderGoods->goods_price = max(0, $log->orderGoods->goods_price - $log->refund_price);
                               $log->orderGoods->save();
                           } else {
                               // 退款部分商品，更新数量和价格
                               $log->orderGoods->total -= $log->refund_total;
                               $log->orderGoods->price = max(0, $log->orderGoods->price - $log->refund_price);
                               $log->orderGoods->goods_price = max(0, $log->orderGoods->goods_price - $log->refund_price);
                               $log->orderGoods->payment_amount = max(0, $log->orderGoods->payment_amount - $log->refund_price);
                               $log->orderGoods->save();
                           }

                           //修改子订单
                           if($log->refund_total != $log->orderGoods->total){
                               $order_parent = Order::find($log->order_id);
                               $order_parent->goods_price = $order_parent->goods_price - $log->refund_price;
                               $order_parent->goods_total = $order_parent->goods_total - $log->refund_total;
                               $order_parent->save();
                           }


                           //修改子订单尾款金额
                           $orderStage = OrderStage::where('order_id',$log->order_id)->where('stage',2)->first();
                           $orderStage->price = max(0,$orderStage->price - $log->refund_price);
                           $orderStage->save();
                       }


                       //修改主订单
                       $this->order->goods_price = max(0,$this->order->goods_price-$order_total_price);
                       $this->order->goods_total = max(0,$this->order->goods_total - $order_total);

                       $this->order->refund_status = 1;
                       $this->order->save();


                       //修改主订单尾款金额
                       $orderStage = OrderStage::where('order_id',$this->order->id)->where('stage',2)->first();
                       $orderStage->price = max(0,$orderStage->price - $order_total_price);
                       $orderStage->save();




                   }
                   $orderIds = Order::where('parent_id', $this->order->id)->pluck('id')->toArray();

                   foreach ($orderIds as $orderId) {
                       // 获取该子订单的 refund_id
                       $refund_id = RefundGoodsLog::where('order_id', $orderId)->value('refund_id');

                       if ($refund_id) { // 确保 refund_id 存在
                           $existsOrderGoods = OrderGoods::where('order_id', $orderId)
                               ->where('refund_success', 0)
                               ->exists();

                           if (!$existsOrderGoods) {
                               $result = DB::transaction(function () use ($refund_id) {
                                   $result = (new RefundService)->payV2($refund_id);
                                   if (!$result) {
                                       \Log::debug('<------售后自动退款失败------', ['refund_id' => $refund_id]);
                                   }
                                   return $result;
                               });
                               $order = Order::find($orderId);
                               \app\backend\modules\order\services\OrderService::close($order);
                           }
                       }
                   }


               }


            }

        }
        OrderGoods::where('refund_id', $this->id)->update($updateData);

        $this->closeManyApply();

    }

    /**
     * 售后驳回、用户取消申请
     * @throws \Exception
     */
    protected function delRefundOrderGoodsLog()
    {
        RefundGoodsLog::where('refund_id', $this->id)->delete();

        //更新订单商品表售后字段
        OrderGoods::where('refund_id', $this->id)->update(['refund_id'=> 0]);

        $this->closeManyApply();
    }

    protected function cancelRefund()
    {
        return $this->order->cancelRefund();
    }

    protected function closeOrder()
    {
        return $this->order->close();
    }

    //todo blank-05-23 有一种情况就是订单同时拥有多条售后记录问题，造成售后记录异常
    protected function closeManyApply()
    {
        $errorRefundId = \app\common\models\refund\RefundApply::uniacid()
            ->where('order_id', $this->order_id)
            ->where('id','!=', $this->id)
            ->where('status', '>=',RefundApply::WAIT_CHECK)
            ->where('status', '<', RefundApply::COMPLETE)->pluck('id')->toArray();

        if ($errorRefundId) {
            \app\common\models\refund\RefundApply::whereIn('id', $errorRefundId)->update([
                'status' => RefundApply::CLOSE,
                'reject_time' => time(),
                'reason' => '关闭之前未完成的记录',
                'remark' => '订单同时存在多条的售后记录',
            ]);
            RefundGoodsLog::whereIn('refund_id', $errorRefundId)->delete();
        }

    }

    /**
     * @param $eventClass
     * @param mixed ...$parameter
     */
    protected function afterEvent($eventClass, ...$parameter)
    {
        event(new $eventClass(...$parameter));
    }

}