<?php

namespace app\frontend\modules\project\services\doororder;

use app\common\exceptions\AppException;
use app\common\models\Address;
use app\common\models\Order;
use app\common\models\project\ProjectReport;
use app\frontend\modules\project\infrastructure\BaseRepository;
use app\frontend\modules\project\models\Project;
use Illuminate\Support\Facades\DB;
use Yunshop\Supplier\common\models\SupplierCharge;
use Yunshop\Supplier\common\models\SupplierService;
use Yunshop\Supplier\common\models\SupplierServiceFee;

class DoorOrderQueryService
{
    // 订单类型
    const ORDER_TYPE_DOOR = 5;   // 上门服务费订单
    const ORDER_TYPE_BRAND = 3;  // 品牌使用费订单

    // 项目升级状态
    const UPGRADE_STATUS_NO = -1;  // 未升级
    const UPGRADE_STATUS_YES = 1;  // 已升级

    // 状态映射（前端 → 数据库）
    const STATUS_MAP = [
        1 => 4,
        2 => 0,
        3 => 3,
        -1 => -1,
    ];

    // 错误提示
    const MSG_PROJECT_NOT_FOUND = '项目不存在';
    const MSG_PROJECT_NOT_REPORTED = '项目未报备';
    const MSG_REPORT_NOT_APPROVED = '项目报备状态未审核';
    const MSG_ORDER_NOT_FOUND = '未找到订单';
    const MSG_DOOR_ORDER_NOT_FOUND = '未找到上门订单';

    /**
     * 获取项目服务信息
     */
    public function getProjectService(int $project_id): array
    {
        $project = Project::find($project_id);
        if (!$project) {
            throw new AppException(self::MSG_PROJECT_NOT_FOUND);
        }

        $projectReport = ProjectReport::where('project_id', $project_id)->first();
        if (!$projectReport) {
            throw new AppException(self::MSG_PROJECT_NOT_REPORTED);
        }
        if ($project->report_status != 2) {
            throw new AppException(self::MSG_REPORT_NOT_APPROVED);
        }

        $brandOrder = Order::where('project_id', $project_id)
            ->where('order_type', self::ORDER_TYPE_BRAND)
            ->whereIn('status', [1, 2, 3])->first();
        if (!$brandOrder) {
            $charges = SupplierCharge::where('supplier_id', $projectReport->supplier_id)
                ->select('supplier_id', 'brand_fee', 'bid_document_fee')
                ->first();
            return [
                'status' => self::UPGRADE_STATUS_NO,
                'brandFee' => $charges->brand_fee ?: 0,
                'doorFee' => 0,
            ];
        } else {
            return [
                'status' => self::UPGRADE_STATUS_YES,
                'brandFee' => 0,
                'doorFee' => 0,
            ];
        }
    }

    /**
     * 获取项目列表
     */
    public function getProjectList(): array
    {
        $memberId = \YunShop::app()->getMemberId();

        $list = DB::table('yz_my_project as p')
            ->leftJoin('yz_project_report as r', 'p.id', '=', 'r.project_id')
            ->where('p.member_id', $memberId)
            ->where('p.report_status', 2)
            ->whereNull('p.deleted_at')
            ->orderBy('r.approved_time', 'desc')
            ->select('p.id', 'p.name', 'r.id as report_id', 'r.supplier_id')
            ->get();

        $data = $list->map(function ($item) {
            return [
                'id'     => $item['id'],
                'name'   => $item['name'],
                'report' => [
                    'id'          => $item['report_id'],
                    'supplier_id' => $item['supplier_id'],
                ],
            ];
        })->toArray();

        $trade = \Setting::get('shop.trade');
        return [
            'data' => $data,
            'door_fee_day' => $trade['door_fee'] ?: 0,
        ];
    }

    /**
     * 上门订单列表
     */
    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = app('OrderManager')->make('Order')
            ->where('order_type', self::ORDER_TYPE_DOOR)
            ->where('uid', $member_id)
            ->with(['projectDoor' => function ($query) {
                $query->select('id', 'service_id', 'service_type', 'project_area', 'province_id', 'city_id', 'district_id');
            }, 'project' => function ($query) {
                $query->withTrashed()->select("id", "name");
            }]);
        $query->where('is_member_deleted', 0);

        if (isset($search['status']) && ($search['status'] !== '' || $search['status'] === 0)) {
            if ($search['status'] !== "all") {
                $query->where('status', $this->getStatusMapping($search['status']));
            }
        }

        if ($search['service_id']) {
            $query->whereHas('projectDoor', function ($query) use ($search) {
                $query->where('service_id', $search['service_id']);
            });
        }

        if ($search['id']) {
            $query->where('id', $search['id']);
        }

        if ($search['name']) {
            if (is_numeric($search['name'])) {
                $query->where('id', $search['name']);
            } else {
                $query->whereHas('project', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search['name'] . '%');
                });
            }
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

        $list = $query->orderBy('create_time', 'desc')->paginate(BaseRepository::PAGE_SIZE);

        $provinceIds = $list->pluck('projectDoor.province_id')->unique()->filter()->toArray();
        $cityIds = $list->pluck('projectDoor.city_id')->unique()->filter()->toArray();
        $districtIds = $list->pluck('projectDoor.district_id')->unique()->filter()->toArray();

        $arr1 = array_merge($provinceIds, $cityIds);
        $merageArr = array_merge($arr1, $districtIds);
        $addresses = Address::whereIn('id', $merageArr)->pluck('areaname', 'id');
        $service_ids = $list->pluck('projectDoor.service_id')->unique()->filter()->toArray();

        $supplier_service = SupplierService::withTrashed()->whereIn('id', $service_ids)->with(['service'])->get();
        $supplier_service_map = $supplier_service->keyBy('id');

        $service_type_ids = $list->pluck('projectDoor.service_type')
            ->flatMap(function ($type) {
                return explode(',', $type);
            })
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $supplier_service_fee = SupplierServiceFee::withTrashed()->whereIn('id', $service_type_ids)->with(['service'])->get();
        $service_fee_map = $supplier_service_fee->keyBy('id');

        $list->transform(function ($item) use ($addresses, $supplier_service_map, $service_fee_map) {
            $province_name = $addresses[$item->projectDoor->province_id] ?? '';
            $city_name = $addresses[$item->projectDoor->city_id] ?? '';
            $district_name = $addresses[$item->projectDoor->district_id] ?? '';
            $item->information = $item->information ? yz_tomedia($item->information) : "";
            $item->addressDetail = $province_name . $city_name . $district_name . $item->projectDoor->address_detail;
            $item->service_name = optional($supplier_service_map[$item->projectDoor->service_id]->service ?? null)->name ?? '';
            $service_type_ids = explode(',', $item->projectDoor->service_type);
            $order_price = $item->goods_price;
            if ($item->extra_enable == 1) {
                $order_price += $item->extra_amount;
            }
            $item->price = $order_price;
            $item->service_fee_list = collect($service_type_ids)
                ->map(function ($id) use ($service_fee_map) {
                    return optional($service_fee_map[$id]->service ?? null)->name ?? '';
                })
                ->filter()
                ->values()
                ->toArray();
            return $item;
        });

        return $list->toArray();
    }

    /**
     * 售后列表
     */
    public function getAfterSales(array $search)
    {
        $member_id = \YunShop::app()->getMemberId();

        $query = app('OrderManager')->make('Order')
            ->where('order_type', self::ORDER_TYPE_DOOR)
            ->where('refund_id', '>', 0)
            ->where('uid', $member_id)
            ->with(['project' => function ($query) {
                $query->withTrashed()->select("id", "name");
            }, 'hasOneRefundApply']);

        if ($search['refund_status']) {
            $query->where('refund_status', $search['refund_status']);
        }

        if ($search['name']) {
            $query->whereHas('project', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search['name'] . '%');
            });
        }

        $list = $query->orderBy('create_time', 'desc')->paginate(BaseRepository::PAGE_SIZE);
        $list->transform(function ($item) {
            return [
                'id' => $item->id,
                'refund_id' => $item->hasOneRefundApply->id,
                "project_name" => $item->project->name,
                'refund_price' => $item->hasOneRefundApply->price,
                'status_name' => $item->hasOneRefundApply->status_name,
                'create_time' => date("Y-m-d H:i:s", $item->hasOneRefundApply->created_at),
            ];
        });

        return $list;
    }

    /**
     * 上门订单详情
     */
    public function getDetail(int $id): array
    {
        $detail = app('OrderManager')->make('Order')->where('id', $id)->with(['projectDoor', 'project' => function ($query) {
            $query->select("id", "name");
        }])->first();
        if (!$detail) {
            throw new AppException(self::MSG_ORDER_NOT_FOUND);
        }
        $address = Address::whereIn('id', [$detail->projectDoor->province_id, $detail->projectDoor->city_id, $detail->projectDoor->district_id])
            ->pluck('areaname')->toArray();
        $supplier_service = SupplierService::withTrashed()->where('id', $detail->projectDoor->service_id)->with(['service'])->first();
        $detail->addressDetail = $address[0] . $address[1] . $address[2] . $detail->projectDoor->address_detail;
        $detail->service_name = $supplier_service->service->name;
        $supplier_service_fee = SupplierServiceFee::withTrashed()->whereIn('id', explode(",", $detail->projectDoor->service_type))->with(['service'])->get();
        $detail->service_fee_list = $supplier_service_fee
            ->pluck('service.name')
            ->filter()
            ->values()
            ->toArray();
        $detail->information = $detail->information ? yz_tomedia($detail->information) : "";
        $brandOrder = Order::where('project_id', $detail->project_id)
            ->where('order_type', self::ORDER_TYPE_BRAND)
            ->whereIn('status', [1, 2, 3])->first();
        $detail->is_discount = $brandOrder ? 1 : 0;
        $detail->service_base_price = $detail->goods_price;
        $detail->discount_price = $brandOrder ? $detail->goods_price : 0;
        $detail->extra_amount = $detail->extra_enable == 1 ? $detail->extra_amount : 0;
        $detail->price = $detail->goods_price - $detail->discount_price + $detail->extra_amount;
        return $detail->toArray();
    }

    /**
     * 获取服务费列表
     */
    public function getServiceFee(int $supplier_id): array
    {
        $service_menu = SupplierService::where('supplier_id', $supplier_id)->with(['service'])->get();
        $service_type_data = SupplierServiceFee::where('supplier_id', $supplier_id)->with(['service'])->get();

        return [
            "service_menu" => $service_menu,
            "service_type_data" => $service_type_data,
        ];
    }

    /**
     * 获取上门订单费用
     */
    public function getDoorFee(int $order_id): array
    {
        $order = Order::with(['projectDoor'])->find($order_id);
        if (!$order) {
            throw new AppException(self::MSG_DOOR_ORDER_NOT_FOUND);
        }
        $data = [];
        $data['project_name'] = Project::where('id', $order->projectDoor->project_id)->value("name");
        $brandOrder = Order::where('project_id', $order->projectDoor->project_id)
            ->where('order_type', self::ORDER_TYPE_BRAND)
            ->whereIn('status', [1, 2, 3])->first();
        $data['is_upgrade'] = $brandOrder ? 1 : 0;
        $data['base_price'] = $order->goods_price;
        $data['discount_price'] = $brandOrder ? $order->goods_price : 0;
        $data['extra_amount'] = $order->extra_amount ?: 0;

        return $data;
    }

    /**
     * 状态映射（前端 → 数据库）
     */
    protected function getStatusMapping($status): int
    {
        return self::STATUS_MAP[$status] ?? -1;
    }
}
