<?php

namespace app\frontend\modules\project\services\factoryinspection;

use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\project\ProjectReport;
use app\common\models\project\ProjectProgress;
use app\frontend\models\OrderGoods;
use app\frontend\modules\project\infrastructure\BaseRepository;
use app\frontend\modules\project\models\Project;
use app\common\models\project\ProjectFactoryInspection;
use app\frontend\modules\project\services\traits\HasMemberCartQuery;
use Yunshop\Supplier\common\models\Supplier;

class FactoryInspectionQueryService
{
    use HasMemberCartQuery;
    // 报备状态
    const REPORT_STATUS_APPROVED = 2; // 已审核

    // 错误提示
    const MSG_INSPECTION_NOT_FOUND = '考察信息不存在';

    /**
     * 考察列表
     */
    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = Project::select("id", "name", "price", "status", "activate", 'factory_status', "report_status", "contact_name", "created_at", "updated_at")
            ->where('member_id', $member_id)
            ->where('report_status', self::REPORT_STATUS_APPROVED);

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

        // 考察状态
        if (!empty($search['factory_status']) && $search['factory_status'] != "all") {
            $query->where('factory_status', $search['factory_status']);
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
     * 获取申请数据
     */
    public function getApplyData(int $id): array
    {
        $detail = ProjectReport::where('project_id', $id)->first();
        if (!$detail) {
            throw new ShopException(self::MSG_INSPECTION_NOT_FOUND);
        }
        $supplier = Supplier::select("id", "store_name", "logo", "province_id", "city_id", "district_id", "street_id", "address", "introduction")
            ->where('id', $detail->supplier_id)
            ->first();
        $address = Address::whereIn('id', [$supplier->province_id, $supplier->city_id, $supplier->district_id, $supplier->street_id])->pluck('areaname')->toArray();
        $supplier->logo = yz_tomedia($supplier->logo);
        $supplier->addressDetail = $address[0] . $address[1] . $address[2] . $address[3] . $supplier->address;
        $data = [
            'supplier' => $supplier->toArray(),
            'inspection_mode' => BaseRepository::inspection_mode,
            "company_type" => ProjectProgress::getCompanyType(2),
            "projects_visit" => ProjectProgress::getCompanyType(3),
            'travel_mode' => BaseRepository::travel_mode,
            'payment_method' => BaseRepository::payment_method,
        ];
        return $data;
    }

    /**
     * 获取详情数据
     */
    public function getDetail(int $id): array
    {
        $detail = ProjectFactoryInspection::where('project_id', $id)->first();
        if (!$detail) {
            throw new ShopException(self::MSG_INSPECTION_NOT_FOUND);
        }

        $supplier = Supplier::select("id", "store_name", "logo", "province_id", "city_id", "district_id", "street_id", "address", "introduction")
            ->where('id', $detail->supplier_id)
            ->first();
        $address = Address::whereIn('id', [$supplier->province_id, $supplier->city_id, $supplier->district_id, $supplier->street_id])->pluck('areaname')->toArray();
        $supplier->logo = yz_tomedia($supplier->logo);
        $supplier->addressDetail = $address[0] . $address[1] . $address[2] . $address[3] . $supplier->address;
        $detail->company_type = !empty($detail->company_type) ? explode(",", $detail->company_type) : [];
        $detail->projects_visit = !empty($detail->projects_visit) ? explode(",", $detail->projects_visit) : [];
        return $detail->toArray();
    }
}
