<?php

namespace app\frontend\modules\project\services\order;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\frontend\models\OrderGoods;
use app\common\models\Order;
use Carbon\Carbon;

/**
 * 订单图纸确认服务
 * 负责：确认图纸、撤销确认、批量确认
 */
class OrderConfirmService
{
    /**
     * 确认单个图纸
     */
    public function confirmDraw(int $id): bool
    {
        try {
            $order_goods = OrderGoods::where('id', $id)->first();
            $order_goods->status = 1;
            $order_goods->save();

            $count = OrderGoods::where('order_id', $order_goods->order_id)->where('status', 0)->count();
            if ($count == 0) {
                $order = Order::find($order_goods->order_id);
                Order::whereIn('parent_id', $order->parent_id)->update(['status' => Order::WAIT_PAY]);
                Order::where('id', $order->parent_id)->update(['status' => Order::WAIT_PAY]);
            }
            return true;
        } catch (\Exception $e) {
            throw new AppException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 批量确认图纸
     */
    public function batchConfirm(array $ids): bool
    {
        if (!$ids) {
            throw new AppException("请选择商品");
        }

        $close_order_day = \Setting::get('shop.trade.close_order_days');
        if (!$close_order_day) {
            $close_order_day = 15;
        }

        $time_type = (int)\Setting::get('shop.trade.close_order_time_type');
        if ($time_type) {
            $expire_time = Carbon::now()->addMinutes($close_order_day)->timestamp;
        } else {
            $expire_time = Carbon::now()->addDays($close_order_day)->timestamp;
        }

        try {
            // 1. 获取所有选中的订单商品
            $selectedGoods = OrderGoods::whereIn('id', $ids)->get();

            // 2. 获取涉及的订单IDs
            $orderIds = $selectedGoods->pluck('order_id')->unique()->toArray();

            if (empty($orderIds)) {
                return false;
            }

            // 3. 查询主订单 ID
            $main_parent_id = Order::where('id', $orderIds[0])->value('parent_id');

            // 4. 验证所有选中的订单商品是否都已上传图纸
            $expectedCount = count($ids);
            $actualCount = OrderGoods::whereIn('id', $ids)
                ->where('confirm_status', 1)
                ->count();

            if ($actualCount !== $expectedCount) {
                throw new ShopException('存在未上传图纸的订单商品');
            }

            // 5. 找出所有需要额外处理的父商品
            $parentGoodsIdsToUpdate = [];
            $parentGoodsChildCount = [];
            $parentGoodsConfirmedCount = [];

            foreach ($selectedGoods as $goods) {
                if ($goods->parent_id > 0) {
                    $parentId = $goods->parent_id;

                    if (!isset($parentGoodsChildCount[$parentId])) {
                        $parentGoodsChildCount[$parentId] = 0;
                    }
                    $parentGoodsChildCount[$parentId]++;

                    if (!isset($parentGoodsConfirmedCount[$parentId])) {
                        $parentGoodsConfirmedCount[$parentId] = 0;
                    }
                    $parentGoodsConfirmedCount[$parentId]++;
                }
            }

            // 6. 获取每个父商品的总子商品数量和已确认的子商品数量
            foreach ($parentGoodsChildCount as $parentGoodsId => $selectedChildCount) {
                $allChildren = OrderGoods::where('parent_id', $parentGoodsId)->get();
                $totalChildCount = $allChildren->count();

                $alreadyConfirmedCount = OrderGoods::where('parent_id', $parentGoodsId)
                    ->where('confirm_status', 2)
                    ->count();

                $currentBatchConfirmed = $parentGoodsConfirmedCount[$parentGoodsId] ?? 0;
                $totalConfirmedCount = $alreadyConfirmedCount + $currentBatchConfirmed;

                if ($totalChildCount > 0 && $totalConfirmedCount == $totalChildCount) {
                    $parentGoodsIdsToUpdate[] = $parentGoodsId;
                }
            }

            // 7. 更新本次选中的商品为已确认
            OrderGoods::whereIn('id', $ids)->where('confirm_status', 1)->update([
                'confirm_status' => 2,
                'confirm_time' => time(),
            ]);

            // 8. 更新所有符合条件的父商品状态
            if (!empty($parentGoodsIdsToUpdate)) {
                OrderGoods::whereIn('id', $parentGoodsIdsToUpdate)
                    ->where('confirm_status', 1)
                    ->update([
                        'confirm_status' => 2,
                        'confirm_time' => time(),
                    ]);
            }

            // 9. 对每一个子订单，检查其是否所有商品都确认了
            foreach ($orderIds as $orderId) {
                $total = OrderGoods::where('order_id', $orderId)->count();
                $confirmed = OrderGoods::where('order_id', $orderId)
                    ->where('confirm_status', 2)
                    ->count();

                if ($total > 0 && $total == $confirmed) {
                    Order::where('id', $orderId)->update([
                        'status' => 0,
                        'confirm_status' => 1,
                        'confirm_time' => time(),
                        'pay_expire_time' => $expire_time,
                    ]);
                }
            }

            // 10. 判断主订单下所有子订单是否都已确认
            $childOrderIds = Order::where('parent_id', $main_parent_id)->pluck('id')->toArray();
            $childCount = count($childOrderIds);
            $confirmedCount = Order::whereIn('id', $childOrderIds)
                ->where('confirm_status', 1)
                ->count();

            if ($childCount > 0 && $childCount == $confirmedCount) {
                Order::where('id', $main_parent_id)->update([
                    'status' => 0,
                    'confirm_status' => 1,
                    'confirm_time' => time(),
                    'pay_expire_time' => $expire_time,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            throw new ShopException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }

    /**
     * 撤销确认图纸
     */
    public function cancelConfirm(int $order_goods_id): bool
    {
        try {
            $orderGoods = OrderGoods::find($order_goods_id);
            if (!$orderGoods) {
                throw new ShopException("订单商品不存在");
            }

            $orderId = $orderGoods->order_id;
            $order = Order::where('id', $orderId)->first();
            if ($order->status >= 1 && $order->status != 4) {
                throw new ShopException("该订单已经支付，无法撤销");
            }

            if ($orderGoods->confirm_status !== 2) {
                throw new ShopException("该订单商品未处于已确认状态，无法撤销");
            }

            // 撤销该商品确认状态
            $orderGoods->confirm_status = 1;
            $orderGoods->confirm_time = 0;
            $orderGoods->save();

            // 1. 如果是子商品，撤销对应的父商品确认状态
            if ($orderGoods->parent_id > 0) {
                $parentGoods = OrderGoods::find($orderGoods->parent_id);
                if ($parentGoods && $parentGoods->confirm_status === 2) {
                    $parentGoods->confirm_status = 1;
                    $parentGoods->confirm_time = 0;
                    $parentGoods->save();
                }
            }
            // 2. 如果是父商品，撤销所有子商品的确认状态
            elseif ($orderGoods->parent_id == 0 && $orderGoods->product_type == 5) {
                OrderGoods::where('parent_id', $orderGoods->id)
                    ->where('confirm_status', 2)
                    ->update([
                        'confirm_status' => 1,
                        'confirm_time' => 0
                    ]);
            }

            // 撤销该订单的状态
            Order::where('id', $orderId)->update([
                'confirm_status' => 0,
                'confirm_time' => 0,
                'status' => Order::WAIT_CONFIRM
            ]);

            // 获取主订单 ID
            $parentId = Order::where('id', $orderId)->value('parent_id');
            if ($parentId) {
                Order::where('id', $parentId)->update([
                    'confirm_status' => 0,
                    'confirm_time' => null,
                    'status' => Order::WAIT_CONFIRM
                ]);
            }

            return true;
        } catch (\Exception $e) {
            throw new ShopException(config("app.debug") ? $e->getMessage() : "异常失败");
        }
    }
}
