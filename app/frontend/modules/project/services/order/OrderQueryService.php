<?php

namespace app\frontend\modules\project\services\order;

use app\common\models\Address;
use app\common\models\kefu\ServiceUser;
use app\frontend\models\OrderGoods;
use app\common\models\Order;
use app\frontend\modules\project\services\order\OrderDetailService;
use Carbon\Carbon;

/**
 * 订单查询服务
 * 负责：订单列表、售后订单列表、回收站订单、售后订单详情
 */
class OrderQueryService
{
    const PAGE_SIZE = 6;

    /**
     * 订单状态映射
     */
    public function getStatusWhere($status): array
    {
        switch ($status) {
            case 1: return ['status' => 4];
            case 2: return ['status' => 0, 'orderStatus' => 0];
            case 3: return ['status' => 1, 'orderStatus' => 1];
            case 4: return ['status' => 1, 'orderStatus' => 2];
            case 5: return ['status' => 1, 'orderStatus' => 3];
            case 6: return ['status' => 2];
            case 7: return ['status' => 3];
            case 8: return ['status' => -1];
            default: return [];
        }
    }

    /**
     * 获取订单列表
     */
    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = app('OrderManager')->make('Order')->uniacid()->select("*")->with(['project' => function ($query) {
            $query->withTrashed()->select("id", "name");
        }, 'supplier' => function ($query) {
            $query->select("id", "store_name");
        }, 'hasOneRefundApply' => function ($query) {
            $query->select("id", "refund_sn", "price", "status", "apply_price", "reason", "content", "refund_time", "created_at");
        }])->where('uid', $member_id)->where('order_type', 1)->where('parent_id', 0);

        if ($search['is_member_deleted']) {
            $query->where('is_member_deleted', $search['is_member_deleted']);
        } else {
            $query->where('is_member_deleted', 0);
        }

        $search['start_date'] = $search['start_date'] ? strtotime($search['start_date']) : "";
        $search['end_date'] = $search['end_date'] ? strtotime(date('Y-m-d', strtotime($search['end_date'])) . ' 23:59:59') : null;

        if (!empty($search['start_date']) && !empty($search['end_date'])) {
            $query->whereBetween('create_time', [$search['start_date'], $search['end_date']]);
        } elseif (!empty($search['start_date'])) {
            $query->where('create_time', '>=', $search['start_date']);
        } elseif (!empty($search['end_date'])) {
            $query->where('create_time', '<=', $search['end_date']);
        }

        if ($search['name']) {
            $query->whereHas('project', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search['name'] . '%');
            });
        }

        if (isset($search['status']) && ($search['status'] !== '' || $search['status'] === 0)) {
            if ($search['status'] !== "all") {
                $query->where($this->getStatusWhere($search['status']));
            }
        }

        $data = $query->orderBy('create_time', 'desc')->paginate(self::PAGE_SIZE);

        $orderIds = $data->pluck('id');

        $confirmedCounts = OrderGoods::whereIn('order_main_id', $orderIds)
            ->where('confirm_status', 1)
            ->where('upload_time', '>', 0)
            ->groupBy('order_main_id')
            ->selectRaw('order_main_id, count(*) as count')
            ->pluck('count', 'order_main_id');

        $data->transform(function ($item) use ($confirmedCounts) {
            $item->confirm_num = $confirmedCounts[$item->id] ?? 0;
            if ($item->status == 0 && $item->orderStatus == 0 && $item->confirm_status == 1 && $item->payment_stage == 1) {
                $item->close_time = $item->pay_expire_time - time() > 0 ? $item->pay_expire_time - time() : 0;
            }

            if ($item->new_status == 3) {
                $max_lead_time = 0;
                if ($item->orderMainGoods->isNotEmpty()) {
                    $max_lead_time = $item->orderMainGoods->max(function ($goods) {
                        return isset($goods->goods->lead_time) ? (int)$goods->goods->lead_time : 0;
                    });
                }

                if ($item->first_pay_time && $max_lead_time > 0) {
                    $expected_finish_date = $item->first_pay_time->copy()->addDays($max_lead_time);
                    $now = Carbon::now();
                    $remaining_days = $now->diffInDays($expected_finish_date, false);
                    $item->expected_finish_date = $expected_finish_date->format('Y-m-d H:i:s');
                    $item->remaining_days_text = $remaining_days;
                } else {
                    $item->expected_finish_date = null;
                    $item->remaining_days_text = null;
                }
            }
            return $item;
        });

        return $data->toArray();
    }

    /**
     * 获取售后订单列表
     */
    public function getAfterSalesOrder(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = app('OrderManager')->make('Order')->uniacid()->select("*")->with(['project' => function ($query) {
            $query->select("id", "name");
        }, 'hasOneRefundApply' => function ($query) {
            $query->select("id", "refund_sn", "price", "status", "apply_price", "reason", "content", "refund_time", "created_at");
        }])->where('uid', $member_id)->where('order_type', 1)->where('parent_id', 0);

        $query->where('is_member_deleted', 0)->where('refund_status', '!=', 0);

        if (isset($search['refund_status']) && ($search['refund_status'] !== '' || $search['refund_status'] === 0)) {
            if ($search['refund_status'] !== "all") {
                $query->where('refund_status', $search['refund_status']);
            }
        }

        if ($search['name']) {
            $query->whereHas('project', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search['name'] . '%');
            });
        }

        $data = $query->orderBy('create_time', 'desc')->paginate(self::PAGE_SIZE)->toArray();

        foreach ($data['data'] as &$item) {
            if (!empty($item['has_one_refund_apply']['created_at'])) {
                $item['has_one_refund_apply']['created_at'] = date('Y-m-d H:i', $item['has_one_refund_apply']['created_at']);
            }
        }

        return $data;
    }

    /**
     * 获取回收站订单
     */
    public function getRecycleOrder(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = app('OrderManager')->make('Order')->uniacid()->select("*")->with(['project' => function ($query) {
            $query->select("id", "name");
        }])->where('uid', $member_id)->where('parent_id', 0);

        $query->where('is_member_deleted', 1);

        $search['start_date'] = $search['start_date'] ? strtotime($search['start_date']) : "";
        $search['end_date'] = $search['end_date'] ? strtotime($search['end_date']) : "";

        if (!empty($search['start_date']) && !empty($search['end_date'])) {
            $query->whereBetween('create_time', [$search['start_date'], $search['end_date']]);
        } elseif (!empty($search['start_date'])) {
            $query->where('create_time', '>=', $search['start_date']);
        } elseif (!empty($search['end_date'])) {
            $query->where('create_time', '<=', $search['end_date']);
        }

        if ($search['name']) {
            $query->whereHas('project', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search['name'] . '%');
            });
        }

        if ($search['status'] == 1) {
            $query->where('order_type', 1);
        } elseif ($search['status'] == 2) {
            $query->where('order_type', 5);
        } elseif ($search['status'] == 3) {
            $query->whereIn('order_type', [2, 3, 4]);
        }

        $data = $query->paginate(self::PAGE_SIZE);
        return $data->toArray();
    }

    /**
     * 售后订单详情
     */
    public function getAfterSalesOrderDetail(int $order_id): array
    {
        $order = Order::select("id", "order_sn", "project_id", "goods_total", "goods_price", "create_time", "pay_time")->with(['project' => function ($query) {
            $query->select("id", "name", "province_id", "city_id", "district_id", "address_detail");
        }])->where('id', $order_id)->first();

        $orderIds = Order::where('parent_id', $order_id)->pluck("id")->toArray();

        $orderGoods = \app\common\models\OrderGoods::whereIn('order_id', $orderIds)->where('refund_id', '>', 0)->with(['order' => function ($query) {
            $query->select("id", "order_sn", "supp_id")->with(['supplier' => function ($query) {
                $query->select("id", "store_name");
            }]);
        }, 'hasOneRefund' => function ($query) {
            $query->select("id", "refund_sn", "apply_price", "price", "refund_type", "status", "create_time");
        }])->get();

        $address = Address::whereIn('id', [$order->project->province_id, $order->project->city_id, $order->project->district_id])->pluck("areaname")->toArray();
        $order->project->project_address_detail = $address[0] . $address[1] . $address[2] . $order->project->address_detail;

        $orderGoods = $orderGoods->map(function ($item) {
            $item->material_color = $this->getMaterialColor($item->toArray());
            $item->order->supplier->service_link = ServiceUser::getDistributeService($item->goods_option_id, $item->goods_id, $item->goodsOption->mirror_enable, $item->is_mirrored);
            return $item;
        });

        $groupedOrderGoods = $orderGoods->groupBy(function ($item) {
            return $item->order->supplier->id;
        })->map(function ($items, $supplierId) {
            return [
                'id' => $supplierId,
                'name' => $items->first()->order->supplier->store_name,
                'service_link' => ServiceUser::getDistributeService(0),
                "refund_sn" => $items->first()->hasOneRefund->refund_sn,
                'data' => $items->values(),
            ];
        })->values();

        $order->orderGoods = $groupedOrderGoods;
        return $order->toArray();
    }

    /**
     * 获取订单详情（委托给 OrderDetailService）
     */
    public function detail(int $order_id): array
    {
        return OrderDetailService::detail($order_id);
    }

    /**
     * 获取材质颜色（从 BaseRepository 复制）
     */
    protected function getMaterialColor($item)
    {
        // 复用 BaseRepository 中的实现
        $baseRepo = app(\app\frontend\modules\project\infrastructure\BaseRepository::class);
        return $baseRepo->getMaterialColor($item);
    }
}
