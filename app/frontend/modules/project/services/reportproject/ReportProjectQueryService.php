<?php

namespace app\frontend\modules\project\services\reportproject;

use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\Order;
use app\frontend\models\OrderGoods;
use app\frontend\modules\project\models\Follow;
use app\frontend\modules\project\models\Project;
use app\frontend\modules\project\services\traits\HasMemberCartQuery;
use app\common\models\project\ProjectReport;
use app\common\models\project\ProjectProgress;
use app\frontend\modules\project\infrastructure\BaseRepository;
use Illuminate\Support\Facades\DB;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierCharge;
use Yunshop\Supplier\supplier\services\ReportProjectService;

class ReportProjectQueryService
{
    use HasMemberCartQuery;

    // 订单类型
    const ORDER_TYPE_BRAND = 3;  // 品牌使用费

    // 推荐品牌数量限制
    const RECOMMEND_BRAND_LIMIT = 3;

    // 错误提示
    const MSG_REPORT_NOT_FOUND = '报备不存在';

    /**
     * 报备列表
     */
    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = Project::select("id", "name", "price", "activate", "status", "report_status", "contact_name", "order_status", "order_id", "created_at", "updated_at")
            ->where('member_id', $member_id)
            ->with(['report' => function ($query) {
                $query->select("id", "project_id", "is_cancel");
            }]);

        if (!empty($search['name'])) {
            $query->where('name', 'like', '%' . $search['name'] . '%');
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

        if (isset($search['report_status']) && ($search['report_status'] !== '' || $search['report_status'] === 0)) {
            if ($search['report_status'] !== "all") {
                $query->where('report_status', $search['report_status']);
            }
        }

        if (!empty($search['purchase']) && $search['purchase'] != "all") {
            $query->whereHas('report', function ($query) use ($search) {
                $query->where('purchase_mode', $search['purchase']);
            });
        }

        $search['order'] = $search['order'] ? $search['order'] : "id";
        $search['sort'] = $search['sort'] == "asc" ? "asc" : "desc";
        $data = $query->orderBy($search['order'], $search['sort'])->paginate(BaseRepository::PAGE_SIZE);

        $ids = $data->pluck('id')->toArray();

        $memberCarts = $this->getMemberCartTotal($ids);

        $order_ids = $data->pluck('order_id')->toArray();

        $orderGoods = OrderGoods::whereIn('order_main_id', $order_ids)
            ->selectRaw('order_main_id, SUM(goods_price) as total_price')
            ->groupBy('order_main_id')
            ->get()
            ->keyBy('order_main_id');

        $data->transform(function ($item) use ($memberCarts, $orderGoods) {
            if ($item->order_status == 0) {
                $item->price = $memberCarts[$item->id]['total_price'] ?? 0;
            } else {
                $item->price = $orderGoods[$item->order_id]->total_price ?? 0;
            }
            return $item;
        });
        return $data->toArray();
    }

    /**
     * 报备详情
     */
    public function detail(int $id): array
    {
        $detail = ProjectReport::with(['Supplier' => function ($query) {
            $query->select("id", "logo", "store_name", "province_id", "city_id", "district_id", "introduction", "address", "is_bid");
        }])->where('project_id', $id)->first();
        if (!$detail) {
            throw new ShopException(self::MSG_REPORT_NOT_FOUND);
        }
        $charges = SupplierCharge::where('supplier_id', $detail->supplier_id)
            ->select('supplier_id', 'brand_fee', 'bid_document_fee')
            ->first();
        $address = Address::whereIn('id', [$detail->Supplier->province_id, $detail->Supplier->city_id, $detail->Supplier->district_id])->pluck('areaname')->toArray();
        $detail->Supplier->logo = yz_tomedia($detail->Supplier->logo);
        $detail->Supplier->is_bid = $detail->Supplier->is_bid;
        $detail->Supplier->province_name = $address[0] ? mb_substr($address[0], 0, -1, "UTF-8") : "";
        $detail->Supplier->city_name = $address[1] ? mb_substr($address[1], 0, -1, "UTF-8") : "";

        $detail->Supplier->bid_fee = $charges ? $charges->bid_document_fee : 0;
        $detail->Supplier->brand_fee = $charges ? $charges->brand_fee : 0;
        $detail->Supplier->bond_fee = $charges ? $charges->brand_fee : 0;
        $detail->project_progress = $detail->project_progress ? array_map('intval', explode(",", $detail->project_progress)) : [];
        $detail->company_type = $detail->company_type ? array_map('intval', explode(",", $detail->company_type)) : [];
        $detail->project_file = unserialize($detail->project_file);
        $detail->company_type_data = ProjectProgress::getCompanyType(2);
        $detail->Supplier->address_detail = $address[0] . $address[1] . $address[2] . $detail->Supplier->address;
        $detail->projects_visit = BaseRepository::projects_visit;
        $brandOrder = Order::where('project_id', $id)->where('order_type', self::ORDER_TYPE_BRAND)->whereIn('status', [1, 2, 3])->first();
        $detail->is_upgrade = $brandOrder ? 1 : 0;

        return $detail->toArray();
    }

    /**
     * 获取搜索数据
     */
    public function getSearchData(): array
    {
        $obj = new ReportProjectService();
        return $obj->getPurchasingModel()->toArray();
    }

    /**
     * 获取推荐品牌
     */
    public function getRecommendBrand(int $project_id): array
    {
        $supplierStats = DB::table('yz_member_cart as c')
            ->join('yz_goods as g', 'g.id', '=', 'c.goods_id')
            ->join('yz_goods_option as o', 'o.id', '=', 'c.option_id')
            ->where('c.project_id', $project_id)
            ->whereNull('g.deleted_at')
            ->whereNull('c.deleted_at')
            ->selectRaw('ims_g.supp_id, SUM(ims_o.product_price) as total_price, COUNT(ims_c.id) as goods_count')
            ->groupBy('g.supp_id')
            ->orderByDesc('total_price')
            ->orderByDesc('goods_count')
            ->take(self::RECOMMEND_BRAND_LIMIT)
            ->get();

        $supplierIds = $supplierStats->pluck('supp_id')->toArray();
        $suppliers = Supplier::whereIn('id', $supplierIds)->where('bid_enable', 1)
            ->select('id', 'logo', 'store_name', 'province_id', 'city_id', 'introduction', 'is_bid')
            ->get()
            ->keyBy('id');

        $toalPrice = $supplierStats->sum('total_price');

        $memberId = \YunShop::app()->getMemberId();
        $follow_list = Follow::getMyFollow($memberId)->with('belongsToSupplier')->get();
        $followSupplierIds = $follow_list->pluck('supplier_id')->toArray();

        $allSupplierIds = array_unique(array_merge($supplierIds, $followSupplierIds));
        $supplierCharges = SupplierCharge::whereIn('supplier_id', $allSupplierIds)
            ->select('supplier_id', 'brand_fee', 'bid_document_fee')
            ->get()
            ->keyBy('supplier_id');

        $provinceCityIds = $supplierStats->pluck('supplierGoods.province_id')
            ->merge($supplierStats->pluck('supplierGoods.city_id'))
            ->merge($follow_list->pluck('belongsToSupplier.province_id'))
            ->merge($follow_list->pluck('belongsToSupplier.city_id'))
            ->unique()
            ->toArray();

        $addresses = Address::whereIn('id', $provinceCityIds)->pluck('areaname', 'id')->toArray();

        $supplierIds = $suppliers->pluck('id')->toArray();
        $filteredSupplierStats = $supplierStats->filter(function ($item) use ($supplierIds) {
            return in_array($item['supp_id'], $supplierIds);
        });

        $recommendBrands = $filteredSupplierStats->map(function ($item) use ($supplierCharges, $addresses, $toalPrice, $suppliers) {
            $charges = $supplierCharges[$item['supp_id']] ?? null;
            $supplier = $suppliers[$item['supp_id']] ?? null;

            if (!$supplier) {
                return null;
            }

            return [
                'id' => $item['supp_id'],
                'logo' => yz_tomedia($supplier->logo ?? ''),
                'store_name' => $supplier->store_name ?? '',
                'province_name' => isset($addresses[$supplier->province_id]) ? mb_substr($addresses[$supplier->province_id], 0, -1, "UTF-8") : '',
                'city_name' => isset($addresses[$supplier->city_id]) ? mb_substr($addresses[$supplier->city_id], 0, -1, "UTF-8") : '',
                'introduction' => $supplier->introduction ?? '',
                'percentage' => $toalPrice > 0 ? round(($item['total_price'] / $toalPrice) * 100, 2) . '%' : '0%',
                'bid_fee' => $charges->bid_document_fee ?? 0,
                'brand_fee' => $charges->brand_fee ?? 0,
                'is_bid' => $supplier->is_bid ?? 0,
            ];
        })->filter()->values();

        $followBrands = $follow_list->map(function ($item) use ($supplierCharges, $addresses) {
            $charges = $supplierCharges[$item->supplier_id] ?? null;

            return [
                'id' => $item->belongsToSupplier->id,
                'logo' => yz_tomedia($item->belongsToSupplier->logo),
                'store_name' => $item->belongsToSupplier->store_name,
                'province_name' => $addresses[$item->belongsToSupplier->province_id] ? mb_substr($addresses[$item->belongsToSupplier->province_id], 0, -1, "UTF-8") : '',
                'city_name' => $addresses[$item->belongsToSupplier->city_id] ? mb_substr($addresses[$item->belongsToSupplier->city_id], 0, -1, "UTF-8") : '',
                'introduction' => $item->belongsToSupplier->introduction,
                'bid_fee' => $charges ? $charges->bid_document_fee : 0,
                'brand_fee' => $charges ? $charges->brand_fee : 0,
                'is_bid' => $item->belongsToSupplier->is_bid,
            ];
        });

        $ReportProjectService = new ReportProjectService;
        $purchasingModes = $ReportProjectService->getPurchasingModel();

        $project = Project::find($project_id);

        return [
            'recommendBrand' => $recommendBrands->toArray(),
            'followBrandList' => $followBrands->toArray(),
            'purchasingModes' => $purchasingModes,
            'projectProgress' => ProjectProgress::getCompanyType(1),
            'companyType' => ProjectProgress::getCompanyType(2),
            'projectDetail' => [
                'name' => $project->name ?? '',
                'address_detail' => $project->address_detail ?? '',
                'province_id' => $project->province_id ?? 0,
                'city_id' => $project->city_id ?? 0,
                'district_id' => $project->district_id ?? 0,
            ],
        ];
    }

    /**
     * 搜索品牌
     */
    public function searchBrand(string $name): array
    {
        $list = Supplier::select("id", "store_name", "logo", "province_id", "city_id", "introduction")
            ->where('store_name', 'like', '%' . $name . '%')
            ->where('status', 1)
            ->get();

        $supplierIds = $list->pluck('id')->toArray();

        $supplierCharges = SupplierCharge::whereIn('supplier_id', $supplierIds)
            ->select('supplier_id', 'brand_fee', 'bid_document_fee')
            ->get()
            ->keyBy('supplier_id');

        $provinceCityIds = $list->pluck('province_id')->merge($list->pluck('city_id'))->unique()->toArray();
        $addresses = Address::whereIn('id', $provinceCityIds)->pluck('areaname', 'id')->toArray();

        $result = $list->map(function ($item) use ($supplierCharges, $addresses) {
            $charges = $supplierCharges[$item->id] ?? null;

            return [
                'id' => $item->id,
                'logo' => yz_tomedia($item->logo),
                'store_name' => $item->store_name,
                'province_name' => $addresses[$item->province_id] ? mb_substr($addresses[$item->province_id], 0, -1, "UTF-8") : '',
                'city_name' => $addresses[$item->city_id] ? mb_substr($addresses[$item->city_id], 0, -1, "UTF-8") : '',
                'introduction' => $item->introduction,
                'bid_fee' => $charges ? $charges->bid_document_fee : 0,
                'brand_fee' => $charges ? $charges->brand_fee : 0,
            ];
        });

        return $result->toArray();
    }
}
