<?php

namespace app\frontend\modules\project\services\bidproject;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\Order;
use app\common\models\project\ProjectBid;
use app\common\models\project\PurchasingModes;
use app\common\models\project\ProjectProgress;
use app\common\models\MemberCart;
use app\frontend\models\OrderGoods;
use app\frontend\modules\goods\models\Goods;
use app\frontend\modules\project\models\Follow;
use app\frontend\modules\project\models\Project;
use app\frontend\modules\project\services\traits\HasMemberCartQuery;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierCharge;

class BidProjectQueryService
{
    use HasMemberCartQuery;
    const PAGE_SIZE = 6;

    // 报备状态
    const REPORT_STATUS_APPROVED = 2; // 已报备

    // 订单类型 - 品牌使用费
    const ORDER_TYPE_BRAND_FEE = 3;

    // 错误提示
    const MSG_BID_NOT_FOUND = '投标不存在';

    /**
     * 投标项目列表
     */
    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = Project::select("id", "name", "price", "status", "bid_status", "activate", "report_status", "contact_name", "order_status", "order_id", "created_at", "updated_at")->where('member_id', $member_id)->where('report_status', self::REPORT_STATUS_APPROVED);

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

        if (!empty($search['bid_status']) && $search['bid_status'] != "all") {
            $query->where('bid_status', $search['bid_status']);
        }

        if (!empty($search['bid_type'])) {
            $query->whereHas('bid', function ($query) use ($search) {
                $query->where('bid_type', $search['bid_type']);
            });
        }

        $search['order'] = $search['order'] ? $search['order'] : "id";
        $search['sort'] = $search['sort'] == "asc" ? "asc" : "desc";
        $data = $query->orderBy($search['order'], $search['sort'])->paginate(self::PAGE_SIZE);

        $ids = $data->pluck('id')->toArray();

        $memberCarts = $this->getMemberCartTotal($ids);
        $order_ids = $data->pluck('order_id')->toArray();
        $orderGoods = OrderGoods::whereIn('order_main_id', $order_ids)
            ->selectRaw('
          order_main_id,
        SUM(goods_price) as total_price
            ')
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
     * 投标详情
     */
    public function detail(int $id): array
    {
        $detail = ProjectBid::with(['Supplier' => function ($query) {
            $query->select("id", "logo", "store_name", "province_id", "city_id", "district_id", "introduction", "address", "is_bid");
        }])->find($id);
        if (!$detail) {
            $detail = ProjectBid::where('project_id', $id)->with(['Supplier' => function ($query) {
                $query->select("id", "logo", "store_name", "province_id", "city_id", "district_id", "introduction", "address", "is_bid");
            }])->first();
        }

        if (!$detail) {
            throw new ShopException(self::MSG_BID_NOT_FOUND);
        }

        $detail->project_file = $detail->project_file ? unserialize($detail->project_file) : [];

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
        $detail->Supplier->address_detail = $address[0] . $address[1] . $address[2] . $detail->Supplier->address;
        $detail->projects_visit = \app\frontend\modules\project\infrastructure\BaseRepository::projects_visit;
        $detail->company_type = $detail->company_type ? array_map('intval', explode(",", $detail->company_type)) : [];
        $detail->company_type_data = ProjectProgress::getCompanyType(2);
        $brandOrder = Order::where('project_id', $detail->project_id)->where('order_type', self::ORDER_TYPE_BRAND_FEE)->whereIn('status', [1, 2, 3])->first();
        $detail->is_upgrade = $brandOrder ? 1 : 0;

        return $detail->toArray();
    }

    /**
     * 获取推荐品牌数据
     */
    public function getRecommendBrand(int $project_id): array
    {
        // 1. 获取项目相关商品的 goods_id
        $goodsIds = MemberCart::where('project_id', $project_id)
            ->pluck('goods_id')
            ->toArray();

        // 2. 统计每个供应商的商品数量及占比
        $supplierStats = Goods::with(['supplierGoods' => function ($query) {
            $query->select("id", "logo", "store_name", "province_id", "city_id", "introduction")
                ->where('status', 1);
        }])
            ->whereIn('id', $goodsIds)
            ->selectRaw('supp_id, COUNT(id) as goods_count')
            ->groupBy('supp_id')
            ->get()
            ->sortByDesc('goods_count')
            ->take(3);

        $totalGoods = $supplierStats->sum('goods_count');
        $supplierIds = $supplierStats->pluck('supp_id')->toArray();

        // 3. 批量查询供应商费用
        $memberId = \YunShop::app()->getMemberId();
        $follow_list = Follow::getMyFollow($memberId)->with('belongsToSupplier')->get();
        $followSupplierIds = $follow_list->pluck('supplier_id')->toArray();

        $allSupplierIds = array_unique(array_merge($supplierIds, $followSupplierIds));
        $supplierCharges = SupplierCharge::whereIn('supplier_id', $allSupplierIds)
            ->select('supplier_id', 'brand_fee', 'bid_document_fee')
            ->get()
            ->keyBy('supplier_id');

        // 4. 批量查询地址
        $provinceCityIds = $supplierStats->pluck('supplierGoods.province_id')
            ->merge($supplierStats->pluck('supplierGoods.city_id'))
            ->merge($follow_list->pluck('belongsToSupplier.province_id'))
            ->merge($follow_list->pluck('belongsToSupplier.city_id'))
            ->unique()
            ->toArray();

        $addresses = Address::whereIn('id', $provinceCityIds)
            ->pluck('areaname', 'id')
            ->toArray();

        // 5. 处理推荐品牌
        $recommendBrands = $supplierStats->map(function ($item) use ($totalGoods, $supplierCharges, $addresses) {
            $charges = $supplierCharges[$item->supp_id] ?? null;

            return [
                'id' => $item->supp_id,
                'logo' => yz_tomedia($item->supplierGoods->logo),
                'store_name' => $item->supplierGoods->store_name,
                'province_name' => $addresses[$item->supplierGoods->province_id] ?? '',
                'city_name' => $addresses[$item->supplierGoods->city_id] ?? '',
                'introduction' => $item->supplierGoods->introduction,
                'percentage' => $totalGoods > 0 ? round(($item->goods_count / $totalGoods) * 100, 2) . '%' : '0%',
                'bid_fee' => $charges ? $charges->bid_document_fee : 0,
                'brand_fee' => $charges ? $charges->brand_fee : 0,
            ];
        });

        // 6. 处理收藏品牌
        $followBrands = $follow_list->map(function ($item) use ($supplierCharges, $addresses) {
            $charges = $supplierCharges[$item->supplier_id] ?? null;

            return [
                'id' => $item->belongsToSupplier->id,
                'logo' => yz_tomedia($item->belongsToSupplier->logo),
                'store_name' => $item->belongsToSupplier->store_name,
                'province_name' => $addresses[$item->belongsToSupplier->province_id] ?? '',
                'city_name' => $addresses[$item->belongsToSupplier->city_id] ?? '',
                'introduction' => $item->belongsToSupplier->introduction,
                'bid_fee' => $charges ? $charges->bid_document_fee : 0,
                'brand_fee' => $charges ? $charges->brand_fee : 0,
            ];
        });

        // 7. 获取采购模式、项目进度和企业类别
        $purchasingModes = PurchasingModes::where('parent_id', 0)
            ->with('hasManyChildren:id,name,parent_id')
            ->get();

        return [
            'recommendBrand' => $recommendBrands->toArray(),
            'followBrandList' => $followBrands->toArray(),
            'purchasingModes' => $purchasingModes,
            'projectProgress' => ProjectProgress::getCompanyType(1),
            'companyType' => ProjectProgress::getCompanyType(2),
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
                'province_name' => $addresses[$item->province_id] ?? '',
                'city_name' => $addresses[$item->city_id] ?? '',
                'introduction' => $item->introduction,
                'bid_fee' => $charges ? $charges->bid_document_fee : 0,
                'brand_fee' => $charges ? $charges->brand_fee : 0
            ];
        });

        return $result->toArray();
    }
}
