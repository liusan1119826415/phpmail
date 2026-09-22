<?php

namespace app\frontend\modules\project\services\project;

use app\common\models\Address;
use app\frontend\models\OrderGoods;
use app\frontend\modules\project\models\Floors;
use app\frontend\modules\project\models\Project;
use app\frontend\modules\project\services\traits\HasMemberCartQuery;
use Illuminate\Support\Facades\Redis;

/**
 * 项目查询服务
 * 负责：项目列表、回收站列表、省市数据、楼层查询、我的项目
 */
class ProjectQueryService
{
    use HasMemberCartQuery;
    const PAGE_SIZE = 6;

    /**
     * 项目列表（V1）
     */
    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = Project::select("id", "name", "price", "activate", "status", "report_status", "contact_name", "order_status", "order_id", "created_at", "updated_at")
            ->where('member_id', $member_id);

        if (!empty($search['name'])) {
            $query->where('name', 'like', '%' . $search['name'] . '%');
        }
        if ($search['is_recycle']) {
            $query->withTrashed()->whereNotNull('deleted_at');
        }
        if (isset($search['order_status']) && ($search['order_status'] !== '' || $search['order_status'] === 0)) {
            if ($search['order_status'] !== "all") {
                $query->where('order_status', $search['order_status']);
            }
        }

        $search['start_date'] = $search['start_date'] ? strtotime($search['start_date']) : "";
        $search['end_date'] = $search['end_date'] ? strtotime(date('Y-m-d', strtotime($search['end_date'])) . ' 23:59:59') : null;
        if (!empty($search['start_date']) && !empty($search['end_date'])) {
            $query->whereBetween('created_at', [$search['start_date'], $search['end_date']]);
        } elseif (!empty($search['start_date'])) {
            $query->where('created_at', '>=', $search['start_date']);
        } elseif (!empty($search['end_date'])) {
            $query->where('created_at', '<=', $search['end_date']);
        }

        if (isset($search['status']) && ($search['status'] !== '' || $search['status'] === 0)) {
            if ($search['status'] !== "all") {
                $query->where('order_status', $search['status']);
            }
        }
        $search['order'] = $search['order'] ? $search['order'] : "id";
        $search['sort'] = $search['sort'] == "asc" ? "asc" : "desc";

        $data = $query->orderBy('activate', 'desc')->orderBy($search['order'], $search['sort'])->paginate(self::PAGE_SIZE);

        $this->enrichListData($data);
        return $data->toArray();
    }

    /**
     * 项目列表（V2，含状态统计）
     */
    public function getListV2(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $baseQuery = Project::select("id", "name", "price", "activate", "status", "report_status", "contact_name", "order_status", "order_id", "created_at", "updated_at")
            ->where('member_id', $member_id)
            ->with('order');

        if (!empty($search['name'])) {
            $baseQuery->where('name', 'like', '%' . $search['name'] . '%');
        }
        if (!empty($search['is_recycle'])) {
            $baseQuery->withTrashed()->whereNotNull('deleted_at');
        }

        $start_date = !empty($search['start_date']) ? strtotime($search['start_date']) : null;
        $end_date = !empty($search['end_date']) ? strtotime(date('Y-m-d', strtotime($search['end_date'])) . ' 23:59:59') : null;
        if ($start_date && $end_date) {
            $baseQuery->whereBetween('created_at', [$start_date, $end_date]);
        } elseif ($start_date) {
            $baseQuery->where('created_at', '>=', $start_date);
        } elseif ($end_date) {
            $baseQuery->where('created_at', '<=', $end_date);
        }

        $counts = $this->getStatusCounts($baseQuery);

        if (!empty($search['project_status']) && $search['project_status'] !== 'total') {
            $this->applyProjectStatusFilter($baseQuery, $search['project_status']);
        }

        $orderField = $search['order'] ?? 'id';
        $orderSort = ($search['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $data = $baseQuery->orderBy('activate', 'desc')
            ->orderBy($orderField, $orderSort)
            ->paginate(self::PAGE_SIZE);

        $this->enrichListData($data);

        return [
            'data'   => $data->toArray(),
            'counts' => $counts,
        ];
    }

    /**
     * 获取楼层列表
     */
    public function getFloors(int $project_id): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $project = Project::getProject($project_id, $member_id);
        if (!$project) {
            throw new \app\common\exceptions\ShopException("项目不存在");
        }
        $list = Floors::select("id", "name")->where('project_id', $project_id)->get();
        return $list ? $list->toArray() : [];
    }

    /**
     * 获取省市数据
     */
    public function getProvinceCity(int $id): array
    {
        $id = $id ?: 0;
        $provinceData = Redis::get("data:{$id}:province");
        if ($provinceData) {
            return json_decode($provinceData, true);
        }

        $data = Address::select("id", "areaname")->where('parentid', $id)->get()->toArray();
        $baseCacheTime = 24 * 60 * 60;
        $randomFactor = rand(10, 99);
        $cacheTime = (int)($baseCacheTime * $randomFactor);
        Redis::setex("data:{$id}:province", $cacheTime, json_encode($data));
        return $data;
    }

    /**
     * 获取我的项目列表
     */
    public function getMyProject(): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $list = Project::select("id", "name", "activate", "status")->where("member_id", $member_id)->orderBy('created_at', 'desc')->get();
        return $list ? $list->toArray() : [];
    }

    // ==================== 私有辅助方法 ====================

    /**
     * 为列表数据补充金额和状态信息
     */
    private function enrichListData($data)
    {
        $ids = $data->pluck('id')->toArray();

        $memberCarts = $this->getMemberCartTotal($ids);

        $order_ids = $data->pluck('order_id')->toArray();
        $orderGoods = OrderGoods::whereIn('order_main_id', $order_ids)
            ->selectRaw('order_main_id, SUM(goods_price) as total_price')
            ->groupBy('order_main_id')
            ->get()
            ->keyBy('order_main_id');

        $data->transform(function ($item) use ($memberCarts, $orderGoods) {
            $item->no_goods = isset($memberCarts[$item->id]) ? 1 : 0;
            if ($item->order_status == 0) {
                $item->price = $memberCarts[$item->id]['total_price'] ?? 0;
            } else {
                $item->price = $orderGoods[$item->order_id]->total_price ?? 0;
            }
            if ($item->order_status == 0) {
                $item->status_name = '未下单';
            } else {
                $item->status_name = $item->order->status_name ?? "未下单";
            }
            $item->status = $item->order->new_status ?? 0;
            return $item;
        });
    }

    private function applyProjectStatusFilter($query, string $statusKey)
    {
        switch ($statusKey) {
            case 'wait_pay':
                $query->whereHas('order', function ($oq) {
                    $oq->where(function ($q) {
                        $q->where('status', 0)->where('orderStatus', 0)
                            ->orWhere('status', 1)->where('orderStatus', 2);
                    });
                });
                break;
            case 'wait_delivery':
                $query->whereHas('order', function ($oq) {
                    $oq->where('status', 1)->where('orderStatus', 3);
                });
                break;
            case 'wait_accept':
                $query->whereHas('order', function ($oq) {
                    $oq->where('status', 2);
                });
                break;
            case 'completed':
                $query->whereHas('order', function ($oq) {
                    $oq->where('status', 3);
                });
                break;
        }
    }

    private function getStatusCounts($query): array
    {
        $total = (clone $query)->count();
        $waitPay = (clone $query)->whereHas('order', function ($oq) {
            $oq->where(function ($q) {
                $q->where('status', 0)->where('orderStatus', 0)
                    ->orWhere('status', 1)->where('orderStatus', 2);
            });
        })->count();
        $waitDelivery = (clone $query)->whereHas('order', function ($oq) {
            $oq->where('status', 1)->where('orderStatus', 3);
        })->count();
        $waitAccept = (clone $query)->whereHas('order', function ($oq) {
            $oq->where('status', 2);
        })->count();
        $completed = (clone $query)->whereHas('order', function ($oq) {
            $oq->where('status', 3);
        })->count();

        return [
            'total'         => $total,
            'wait_pay'      => $waitPay,
            'wait_delivery' => $waitDelivery,
            'wait_accept'   => $waitAccept,
            'completed'     => $completed,
        ];
    }
}
