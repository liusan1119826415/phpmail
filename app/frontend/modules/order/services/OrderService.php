<?php

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/2/24
 * Time: 下午4:35
 */

namespace app\frontend\modules\order\services;

use app\backend\modules\goods\models\GoodsTradeSet;
use app\backend\modules\order\services\OrderPackageService;
use app\common\events\order\AfterOrderPackageSentEvent;
use app\common\events\order\BeforeOrderPackageEvent;
use app\common\events\order\BeforeOrderSendEvent;
use app\common\exceptions\AppException;
use app\common\facades\Setting;
use app\common\models\DispatchType;
use app\common\models\Order;

use app\common\models\order\Express;
use app\common\models\order\OrderGoodsChangePriceLog;
use app\common\models\order\OrderPackage;
use app\common\models\refund\RefundApply;
use app\common\modules\orderGoods\OrderGoodsCollection;


use \app\common\models\MemberCart;
use app\common\repositories\ExpressCompany;
use app\common\services\CreateRandomNumber;
use app\frontend\models\OrderGoods;
use app\frontend\modules\order\services\behavior\BidOrderSend;
use app\frontend\modules\order\services\behavior\OrderCancelPay;
use app\frontend\modules\order\services\behavior\OrderCancelSend;
use app\frontend\modules\order\services\behavior\OrderChangePrice;
use app\frontend\modules\order\services\behavior\OrderClose;
use app\frontend\modules\order\services\behavior\OrderDelete;
use app\frontend\modules\order\services\behavior\OrderForceClose;
use app\frontend\modules\order\services\behavior\OrderOperation;
use app\frontend\modules\order\services\behavior\OrderPay;
use app\frontend\modules\order\services\behavior\OrderReceive;
use app\frontend\modules\order\services\behavior\OrderRestore;
use app\frontend\modules\order\services\behavior\OrderSend;
use app\frontend\modules\orderGoods\models\PreOrderGoods;
use app\frontend\modules\orderGoods\models\PreOrderGoodsCollection;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use app\frontend\modules\refund\services\RefundOperationService;

class OrderService
{

    public static $order_prefix = "SN";

    public static $order_sn_create_func;

    /**
     * 获取订单商品对象数组
     * @param Collection $memberCarts
     * @return OrderGoodsCollection
     * @throws \Exception
     */
    public static function getOrderGoods(Collection $memberCarts)
    {
        if ($memberCarts->isEmpty()) {
            throw new AppException("购物车记录为空");
        }
        $result = $memberCarts->map(function ($memberCart) {
            if (!($memberCart instanceof MemberCart)) {
                throw new \Exception("请传入" . MemberCart::class . "的实例");
            }
            /**
             * @var $memberCart MemberCart
             */

            $data = [
                'goods_id' => (int)$memberCart->goods_id,
                'goods_option_id' => (int)$memberCart->option_id,
                'total' => (int)$memberCart->total,
                'components'=>$memberCart->components,
                'is_customized'=>(int)$memberCart->is_customized,
                'project_id'=>$memberCart->project_id,
                'type'=>$memberCart->type,
                'floor_id'=>$memberCart->floor_id,
                'space_id'=>$memberCart->space_id,
                'structure'=>$memberCart->goodsOption->structure,
                'productType'=>$memberCart->goods->productType,

            ];
            $orderGoods = app('OrderManager')->make('PreOrderGoods', $data);
            /**
             * @var PreOrderGoods $orderGoods
             */
            $orderGoods->setRelation('goods', $memberCart->goods);
            $orderGoods->setRelation('goodsOption', $memberCart->goodsOption);
            return $orderGoods;
        });

        return new PreOrderGoodsCollection($result);
    }

    private static function defaultOrderSnCreatedFunc()
    {
        if (!isset(self::$order_sn_create_func)) {
            self::$order_sn_create_func = function ($order_prefix) {
                return CreateRandomNumber::sn($order_prefix);
            };
        }
    }

    /**
     * 获取订单号
     * @return string
     */
    public static function createOrderSN()
    {
//        //集合总数
//        $count =  Redis::sCard('order_sn');
//
//        if ($count) {
//            //随机返回集合中的一个元素
//            $orderSN = Redis::sPop('order_sn');
//            if ($orderSN) {
//                return $orderSN;
//            }
//        }

        self::defaultOrderSnCreatedFunc();
        $orderSN = call_user_func(self::$order_sn_create_func, self::$order_prefix);
//        $orderSN = CreateRandomNumber::sn(self::$order_prefix);
        while (1) {
            if (!Order::where('order_sn', $orderSN)->first()) {
                break;
            }
            $orderSN = call_user_func(self::$order_sn_create_func, self::$order_prefix);
//            $orderSN = CreateRandomNumber::sn(self::$order_prefix);
        }
        return $orderSN;


//        $orderSN = createNo('SN', true);
//        while (1) {
//            if (!Order::where('order_sn', $orderSN)->first()) {
//                break;
//            }
//            $orderSN = createNo('SN', true);
//        }
//        return $orderSN;
    }

    /**
     * 获取支付流水号
     * @return string
     */
    public static function createPaySN()
    {

        $paySN = CreateRandomNumber::sn('PN');
        while (1) {
            if (!\app\common\models\OrderPay::where('pay_sn', $paySN)->first()) {
                break;
            }
            $paySN = CreateRandomNumber::sn('PN');
        }
        return $paySN;

//        $paySN = createNo('PN', true);
//        while (1) {
//            if (!\app\common\models\OrderPay::where('pay_sn', $paySN)->first()) {
//                break;
//            }
//            $paySN = createNo('PN', true);
//        }
//        return $paySN;
    }

    /**
     * 订单操作类
     * @param OrderOperation $orderOperation
     * @return string
     * @throws AppException
     */
    private static function OrderOperate(OrderOperation $orderOperation)
    {

        if (!isset($orderOperation)) {
            throw new AppException('未找到该订单');
        }

        DB::transaction(function () use ($orderOperation) {
            $orderOperation->handle();
        });
    }

    /**
     * 取消付款
     * @param $param
     * @return string
     * @throws AppException
     */
    public static function orderCancelPay($param)
    {
        $orderOperation = OrderCancelPay::find($param['order_id']);

        return self::OrderOperate($orderOperation);
    }

    /**
     * 取消发货
     * @param $param
     * @return string
     * @throws AppException
     */
    public static function orderCancelSend($param)
    {
        $orderOperation = OrderCancelSend::find($param['order_id']);

        //取消订单逻辑 因为多包裹发货需要清楚快递信息
        $where[] = ['order_id', '=', $param['order_id']];
        //清楚商品标记包裹
        OrderGoods::where($where)->update(['order_express_id' => null]);
        $where[] = ['deleted_at', '=', 0];
        //清除快递信息
        $data = Express::where($where)->delete();
        // 清除包裹
        OrderPackage::where('order_id',$param['order_id'])->delete();
        //修改订单部分发货状态
        //Order::where('id',$param['order_id'])->update(['is_all_send_goods'=>0]);
        $orderOperation->is_all_send_goods = 0;
        return self::OrderOperate($orderOperation);
    }

    /**
     * 关闭订单
     * @param $param
     * @return string
     * @throws AppException
     */
    /*public static function orderClose($param)
    {
        $orderOperation = OrderClose::find($param['order_id']);


        if (!empty(array_except($param,['order_id']))) {
            $orderOperation->params = $param;
        }

        return self::OrderOperate($orderOperation);
    }*/

    /**
     * 关闭订单（支持主订单及其子订单）
     * @param $param
     * @return string
     * @throws AppException
     */
    public static function orderClose($param)
    {
        $mainOrderId = $param['order_id'];
        $orderOperation = OrderClose::find($mainOrderId);

        if (!$orderOperation) {
            throw new AppException("未找到主订单");
        }

        if (!empty(array_except($param, ['order_id']))) {
            $orderOperation->params = $param;
        }

        // 先关闭主订单
        $result = self::OrderOperate($orderOperation);

        //关闭子订单
        $orderParentOperation = OrderClose::where('parent_id',$mainOrderId)->get();
        $orderParentOperation->map(function ($orderOperation){
            self::OrderOperate($orderOperation);
        });

        return $result;
    }


    /**
     * 恢复订单（支持主订单及其子订单）
     * @param $param
     * @return string
     * @throws AppException
     */
    public static function orderRestore($mainOrderId)
    {

        $orderOperation = OrderRestore::find($mainOrderId);

        if (!$orderOperation) {
            throw new AppException("未找到主订单");
        }


        // 先关闭主订单
        $result = self::OrderOperate($orderOperation);

        return $result;
    }






    /**
     * 强制关闭订单
     * @param $param
     * @return string
     * @throws AppException
     */
    public static function orderForceClose($param)
    {
        $orderOperation = OrderForceClose::find($param['order_id']);

        if (!empty(array_except($param,['order_id']))) {
            $orderOperation->params = $param;
        }

        return self::OrderOperate($orderOperation);
    }

    /**
     * 用户删除(隐藏)订单
     * @param $param
     * @return string
     * @throws AppException
     */
    public static function orderDelete($param)
    {
        $orderOperation = OrderDelete::find($param['order_id']);

        return self::OrderOperate($orderOperation);
    }

    /**
     * 根据流水号合并支付
     * @param array $param
     * @throws AppException
     */
    public static function ordersPay(array $param)
    {
        \Log::info('---------订单支付ordersPay(order_pay_id:' . $param['order_pay_id'] . ')--------', $param);
        /**
         * @var \app\frontend\models\OrderPay $orderPay
         */
        $orderPay = \app\frontend\models\OrderPay::find($param['order_pay_id']);
        if (!isset($orderPay)) {
            throw new AppException('支付流水记录不存在');
        }

        if (isset($param['pay_type_id'])) {
            if ($orderPay->pay_type_id != $param['pay_type_id']) {
                //\Log::error("---------支付回调与与支付请求的订单支付方式不匹配(order_pay_id:{$orderPay->id},orderPay->payTypeId:{$orderPay->pay_type_id} != param[pay_type_id]:{$param['pay_type_id']})--------", []);
                $orderPay->pay_type_id = $param['pay_type_id'];

            }
        }
        $orderPay->pay();

        \Log::info('---------订单支付成功ordersPay(order_pay_id:' . $orderPay->id . ')--------', []);

    }

    /**
     * 后台支付订单
     * @param array $param
     * @return string
     * @throws AppException
     */

    public static function orderPay(array $param)
    {
        /**
         * @var OrderOperation $orderOperation
         */
        $orderOperation = OrderPay::find($param['order_id']);
        $order = Order::find($param['order_id']);
        if (isset($param['pay_type_id'])) {
            $orderOperation->pay_type_id = $param['pay_type_id'];
        }
        $orderOperation->order_pay_id = (int)$param['order_pay_id'];

        $result = self::OrderOperate($orderOperation);



        //是虚拟商品或有标识直接完成 或者投标订单也直接完成
        if ($orderOperation->isVirtual() || in_array($order->order_type,[3,4,5])) {
            // 虚拟物品付款后直接完成
            $orderOperation->dispatch_type_id = 0;
            $orderOperation->save();
            self::orderSend(['order_id' => $orderOperation->id]);
            $result = self::orderReceive(['order_id' => $orderOperation->id]);
        } elseif (isset($orderOperation->hasOneDispatchType) && in_array($orderOperation->dispatch_type_id, $orderOperation->hasOneDispatchType->paidCompleted())) {
            //兼容配送方式支付成功就直接完成的订单
            self::orderSend(['order_id' => $orderOperation->id]);
            $result = self::orderReceive(['order_id' => $orderOperation->id]);
        } elseif (isset($orderOperation->hasOneDispatchType) && !$orderOperation->hasOneDispatchType->needSend()) {
            // 不需要发货的物品直接改为待收货
            self::orderSend(['order_id' => $orderOperation->id]);
        }

        return $result;
    }

    /**
     * 收货
     * @param $param
     * @return string
     * @throws AppException
     */
    public static function orderReceive($param)
    {
        $orderOperation = OrderReceive::find($param['order_id']);
        //新增逻辑部分发货 没有全发货无法确认收货
        if ($orderOperation['is_all_send_goods'] == 1) {
            $notSendNum = $orderOperation->orderGoods->sum(function ($orderGoods) {

                $not_number = $orderGoods->getRefundTotal() + $orderGoods->expressPackage->sum('total');

                return max($orderGoods->total - $not_number,0);
            });
            if ($notSendNum) {
                throw new AppException('订单部分发货无法确认收货');
            }

            //如果是部分发货进行了部分退款，已退款的商品不需要再继续发货
            $orderOperation->is_all_send_goods = 2;
        }

        return self::OrderOperate($orderOperation);
    }


    /**
     * 多包裹继续发货
     * @param $param
     * @throws AppException
     */
    public static function addOrderExpress($param)
    {
        event(new BeforeOrderPackageEvent(Order::find($param['order_id']), $param));

        DB::transaction(function ()use($param){
            /**
             * 根据$param中的订单id和订单商品ids保存快递信息
             * 如果订单已发货就return
             * 未发货继续执行下面的订单发货
             */
            //部分发货 快递单号必填
            if (empty($param['express_sn'])) {
                throw new AppException('请输入快递单号');
            }


            $orderPackageService = new OrderPackageService();

            $orderGoodsCollection = $orderPackageService->canSendGoods($param['order_id']);

            $data = $orderPackageService->unifyFormat($param, $orderGoodsCollection);

            //是多包裹发货，验证包裹发货信息
            if ($data['need_trigger_package_event'] && $data['order_package']) {
                $orderPackageService->validateNum($orderGoodsCollection, collect($data['order_package']));
            }

            //存快递信息
            $db_express_model = new Express();
            $db_express_model->order_id = $param['order_id'];
            $db_express_model->express_code = $param['express_code'] ?: '';
            //当code获取不到物流，并且 有传过来物流名称则使用传过来的（主要针对供应链）
            $express_company_name = array_get(ExpressCompany::create()->where('value', $param['express_code'])->first(), 'name', '其他快递');
            if ($express_company_name == "其他快递" && !empty($param['express_company_name'])) $express_company_name = $param['express_company_name'];

            $db_express_model->express_company_name = $express_company_name;
            $db_express_model->express_sn = $param['express_sn'] ?: '';
            $db_express_model->save();

            if ($data['order_package']) {
                //保存包裹发货记录
                OrderPackageService::saveOneOrderPackage((int)$data['order_id'],(int)$db_express_model->id,$data['order_package']);


                //调用该方法的肯定是多包裹发货，所以触发多包裹事件
                event(new AfterOrderPackageSentEvent(Order::find($data['order_id'])));

                $currentPackage = collect($data['order_package']);
                $num = $orderGoodsCollection->sum(function ($orderGoods) use ($currentPackage) {
                    $package = $currentPackage->firstWhere('order_goods_id', $orderGoods->id);
                    if ($package) {
                        return max($orderGoods->total - $package['total'],0);
                    }
                    return $orderGoods->total;
                });

                //$num > 0 还有未发货商品
                $is_all_send_goods = $num > 0 ? 1 : 2;

                Order::where('id', $param['order_id'])->update(['is_all_send_goods' => $is_all_send_goods]);
            }

            return true;
        });
    }

    /**
     * 发货
     * @param $param
     * @return string
     * @throws AppException
     */
    public static function orderSend($param)
    {
        // \Log::info('---param---', $param);
        $orderOperation = OrderSend::find($param['order_id']);

        $orderOperation->params = $param;
        // \Log::info('----1orderOperation--', $orderOperation);
        return self::OrderOperate($orderOperation);
    }






    /**
     * 改变订单价格
     * @param $param
     * @return string
     * @throws AppException
     */
    public static function changeOrderPrice($param)
    {
        $order = OrderChangePrice::find($param['order_id']);
        /**
         * @var $order OrderChangePrice
         */
        if (!isset($order)) {
            throw new AppException('(ID:' . $order->id . ')未找到订单');
        }
        $orderGoodsChangePriceLogs = self::getOrderGoodsChangePriceLogs($param);

        $order->setOrderGoodsChangePriceLogs($orderGoodsChangePriceLogs);//todo
        $order->setOrderChangePriceLog();
        $order->setClerkId($param['clerk_id']);
        $order->setClerkType($param['clerk_type']);
        $order->setDispatchChangePrice($param['dispatch_price']);

        return self::OrderOperate($order);
    }

    /**
     * 订单改价记录
     * {@inheritdoc}
     */
    private static function getOrderGoodsChangePriceLogs($param)
    {
        return collect($param['order_goods'])->map(function ($orderGoodsParams) use ($param) {

            $orderGoodsChangePriceLog = new OrderGoodsChangePriceLog($orderGoodsParams);
            if (!isset($orderGoodsChangePriceLog->belongsToOrderGoods)) {
                throw new AppException('(ID:' . $orderGoodsChangePriceLog->order_goods_id . ')未找到订单商品记录');

            }
            if ($orderGoodsChangePriceLog->belongsToOrderGoods->order_id != $param['order_id']) {
                throw new AppException('(ID:' . $orderGoodsChangePriceLog->order_goods_id . ',' . $param['order_id'] . ')未找到与商品对应的订单');
            }
            //todo 如果不清空,可能会在push时 保存未被更新的订单商品数据,此处需要重新设计
            $orderGoodsChangePriceLog->setRelations([]);
            return $orderGoodsChangePriceLog;
        });
    }

    public static function autoSend($accountId)
    {
        Setting::$uniqueAccountId =  \YunShop::app()->uniacid = $accountId;
        if ($minutes = (int)Setting::get('shop.trade.send')) {
            \app\backend\modules\order\models\Order::waitSend()->normal()
                ->with('hasManyOrderGoods')
                ->withCount('hasManyOrderGoods as order_goods_count')
                ->whereIn('plugin_id', [0,92]) //只发自营商品
                ->where('is_virtual', 0)  //非虚拟商品
                ->where('pay_time', '<', (int)Carbon::now()->subMinutes($minutes)->timestamp)
                ->chunk(1000, function ($orders) {
                    if (!$orders->isEmpty()) {
                        foreach ($orders as $order) {
                            if ($order->order_goods_count == 1) {
                                $goods_trade = GoodsTradeSet::where('goods_id', $order->hasManyOrderGoods->first()->goods_id)->first();
                                if ($goods_trade && $goods_trade->auto_send) {
                                    $auto_send_day = $goods_trade->auto_send_day;
                                    if ($auto_send_day > 1) {
                                        $auto_send_day -= 1;
                                        $auto_send_day = $order->pay_time->addDays($auto_send_day)->format('Y-m-d');
                                    } else {
                                        $auto_send_day = $order->pay_time->format('Y-m-d');
                                    }
                                    $auto_send_day .= " {$goods_trade->auto_send_time}:00";
                                    $auto_send_timestamp = strtotime($auto_send_day);
                                    if ($auto_send_timestamp < $order->pay_time->timestamp) {
                                        $auto_send_timestamp = Carbon::createFromTimestamp($auto_send_timestamp)->addDays(1)->timestamp;
                                    }
                                    if ($auto_send_timestamp > time()) {
                                        continue;
                                    }
                                }
                            }
                            try {
                                $param = [
                                    "dispatch_type_id" => 1,
                                    "order_id" => $order->id,
                                ];
                                OrderService::orderSend($param);
                            } catch (\Exception $e) {
                                \Log::error("订单:{$order->id}自动发货失败", $e->getMessage());

                            }
                        }
                    }
                });
        } else {
            \app\backend\modules\order\models\Order::waitSend()->normal()
                ->with('hasManyOrderGoods')
                ->withCount('hasManyOrderGoods as order_goods_count')
                ->whereIn('plugin_id', [0,92]) //只发自营商品
                ->chunk(1000, function ($orders) {
                    if (!$orders->isEmpty()) {
                        foreach ($orders as $order) {
                            if ($order->order_goods_count != 1) {
                                continue;
                            }
                            $goods_trade = GoodsTradeSet::where('goods_id', $order->hasManyOrderGoods->first()->goods_id)->first();
                            if (!$goods_trade || !$goods_trade->auto_send) {
                                continue;
                            }
                            $auto_send_day = $goods_trade->auto_send_day;
                            if ($auto_send_day > 1) {
                                $auto_send_day -= 1;
                                $auto_send_day = $order->pay_time->addDays($auto_send_day)->format('Y-m-d');
                            } else {
                                $auto_send_day = $order->pay_time->format('Y-m-d');
                            }
                            $auto_send_day .= " {$goods_trade->auto_send_time}:00";
                            $auto_send_timestamp = strtotime($auto_send_day);
                            if ($auto_send_timestamp < $order->pay_time->timestamp) {
                                $auto_send_timestamp = Carbon::createFromTimestamp($auto_send_timestamp)->addDays(1)->timestamp;
                            }
                            if ($auto_send_timestamp > time()) {
                                continue;
                            }

                            try {
                                $param = [
                                    "dispatch_type_id" => 1,
                                    "order_id" => $order->id,
                                ];
                                OrderService::orderSend($param);
                            } catch (\Exception $e) {
                                \Log::error("订单:{$order->id}自动发货失败", $e->getMessage());

                            }
                        }
                    }
                });
        }
    }

    /**
     * 自动收货
     * {@inheritdoc}
     */
    public static function autoReceive($uniacid)
    {
        \YunShop::app()->uniacid = $uniacid;
        \Setting::$uniqueAccountId = $uniacid;
        $time_type = (int)\Setting::get('shop.trade.receive_time_type');
        $time = (int)\Setting::get('shop.trade.receive');
        if (!$time) {
            return;
        }
        $dispatch_type_id = [
            DispatchType::SELF_DELIVERY, DispatchType::HOTEL_CHECK_IN, DispatchType::DELIVERY_STATION_SEND,
            DispatchType::DRIVER_DELIVERY, DispatchType::PACKAGE_DELIVER, DispatchType::PACKAGE_DELIVERY
        ];
        \app\backend\modules\order\models\Order::waitReceive()->where('auto_receipt', 0)
            ->whereNotIn('dispatch_type_id', $dispatch_type_id)
            ->where(function ($q) use ($time, $time_type) {
                if ($time_type) {
                    $q->where('send_time', '<', (int)Carbon::now()->subMinutes($time)->timestamp);
                } else {
                    $q->where('send_time', '<', (int)Carbon::now()->subDays($time)->timestamp);
                }
            })
            ->normal()
            ->chunk(1000, function ($orders) {
                if (!$orders->isEmpty()) {
                    $orders->each(function ($order) {
                        try {
                            OrderService::orderReceive(['order_id' => $order->id]);
                        } catch (\Exception $e) {
                            //    \Log::error("订单:{$order->id}自动收货失败", $e->getMessage());
                        }
                    });
                }
            });
    }

    /*
     * 售后类型为换货,且状态为重新收货/商家发货的订单也可以自动收货
     */
    public static function autoReceiveExchangeRefund($uniacid)
    {
        \YunShop::app()->uniacid = $uniacid;
        \Setting::$uniqueAccountId = $uniacid;
        $time_type = (int)\Setting::get('shop.trade.receive_time_type');
        $time = (int)\Setting::get('shop.trade.receive');
        if (!$time) {
            return;
        }
        $dispatch_type_id = [
            DispatchType::SELF_DELIVERY, DispatchType::HOTEL_CHECK_IN, DispatchType::DELIVERY_STATION_SEND,
            DispatchType::DRIVER_DELIVERY, DispatchType::PACKAGE_DELIVER, DispatchType::PACKAGE_DELIVERY
        ];
        \app\backend\modules\order\models\Order::waitReceive()->where('auto_receipt', 0)
            ->whereNotIn('dispatch_type_id', $dispatch_type_id)
            ->where(function ($q) use ($time, $time_type) {
                if ($time_type) {
                    $q->where('send_time', '<', (int)Carbon::now()->subMinutes($time)->timestamp);
                } else {
                    $q->where('send_time', '<', (int)Carbon::now()->subDays($time)->timestamp);
                }
            })
            ->where('is_pending', 0)
            ->WhereHas('hasOneRefundApply', function ($q) {
                $q->where('status', 4)->where('refund_type', 2);
            })
            ->chunk(1000, function ($orders) {
                if (!$orders->isEmpty()) {
                    $orders->each(function ($order) {
                        try {
                            RefundOperationService::refundExchangeComplete(['refund_id' => $order->refund_id]);
                        } catch (\Exception $e) {
//                                \Log::debug("订单:{$order->id}自动收货失败", $e->getMessage());
                        }
                    });
                }
            });
    }

    /**
     * 自动关闭订单
     * {@inheritdoc}
     */
    public static function autoClose($uniacid)
    {
        \YunShop::app()->uniacid = $uniacid;
        \Setting::$uniqueAccountId = $uniacid;
        $time_type = (int)\Setting::get('shop.trade.close_order_time_type');
        $time = (int)\Setting::get('shop.trade.close_order_days');
        if (!$time) {
            return;
        }
        $orders = \app\backend\modules\order\models\Order::waitPay()->whereNotIn('plugin_id', [70,158,161])   //淘京拼CPS(70)  抖音CPS(158)的订单不走自动关闭
            ->where(function ($q) use ($time, $time_type) {
                if ($time_type) {
                    $q->where('create_time', '<', (int)Carbon::now()->subMinutes($time)->timestamp);
                } else {
                    $q->where('create_time', '<', (int)Carbon::now()->subDays($time)->timestamp);
                }
            })->normal()->get();
        if (!$orders->isEmpty()) {
            $orders->each(function ($order) {
                try {
                    OrderService::orderClose(['order_id' => $order->id]);
                } catch (\Exception $e) {
                    \Log::error("订单:{$order->id}自动关闭失败", $e->getMessage());
                }
            });
        }
    }

    /**
     * @param $order
     * @throws AppException
     */
    public static function fixVirtualOrder($order)
    {
        \YunShop::app()->uniacid = $order['uniacid'];
        \Setting::$uniqueAccountId = $order['uniacid'];

        if ($order['status'] == 1) {
            OrderService::orderSend(['order_id' => $order['id']]);
        }
        if ($order['status'] == 2) {
            OrderService::orderReceive(['order_id' => $order['id']]);
        }
    }

    public static function getReceiptGoodsNotice()
    {
        $msg = Setting::get('shop.order.receipt_goods_notice');

        return self::verifyOrderReceipt($msg);
    }

    public static function verifyOrderReceipt($msg)
    {
        $verify_str = str_replace(' ', '', $msg);

        // 如果传入内容都是空格
        if (empty($verify_str)) {
            $msg = '';
        }

        return $msg;
    }
}