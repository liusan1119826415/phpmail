<?php

namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\AppException;

use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\Order;
use app\common\modules\pcnotice\Template;
use app\common\services\upload\UploadService;
use app\frontend\models\OrderGoods;
use app\frontend\modules\project\models\Follow;
use app\frontend\modules\project\models\Project;
use app\frontend\modules\project\repositories\ReportProjectRepositoryInterface;
use app\common\models\project\ProjectReport;
use \app\common\models\MemberCart;
use app\frontend\modules\goods\models\Goods;
use app\common\models\project\PurchasingModes;
use app\common\models\project\ProjectProgress;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierCharge;
use Yunshop\Supplier\supplier\services\ReportProjectService;
use Illuminate\Support\Facades\DB;
class ReportProjectRepository extends BaseRepository implements ReportProjectRepositoryInterface
{

    public function __construct(Project $model)
    {
        parent::__construct($model);
    }


    public function applyFor(array $request_data): bool
    {

        $project = Project::find($request_data['project_id']);
        if (!$project) {
            throw new AppException("项目不存在");
        }


        $model = ProjectReport::where('project_id', $request_data['project_id'])->first();
        if($model){
            if($model->supplier_id != $request_data['supplier_id']){
                $model->delete();
                $model = new ProjectReport;
            }
            $project->report_status = 0;
            $project->save();
            $notific = Template::REPORT['SUBMIT_EDIT'];
        }
        if (!$model) {
            $model = new ProjectReport;
            $notific = Template::REPORT['SUBMIT_SUCCESS'];
        }
        $member_id = \YunShop::app()->getMemberId();
        $request_data['member_id'] = $member_id;
        $request_data['project_progress'] = $request_data['project_progress'] ? implode(",",$request_data['project_progress']) : "";
        $request_data['is_cancel'] = 0;
        $request_data['company_type'] = $request_data['company_type'] ? implode(",",$request_data['company_type']) : "";
        $request_data['project_file'] = !empty($request_data['project_file'])?serialize($request_data['project_file']):serialize([]);

        $model->setRawAttributes($request_data);
        //字段检测
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new AppException($validator->messages());
        } else {
            //数据保存
            if ($model->save()) {

                $project->report_status =1;
                $project->save();
                $option['related_id'] = $project->id;
                //发送报备信息
                app('notification')->send(
                    $member_id, // 用户ID
                    Template::AUDIT,
                    Template::REPORT_NAME,
                    $notific,
                    ['report_name' => $request_data['name']],
                    $option

                );
                return true;
            } else {
                throw new AppException("申请报备失败");
            }
        }
    }


    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = Project::select("id", "name", "price","activate", "status", "report_status", "contact_name","order_status","order_id", "created_at", "updated_at")->where('member_id', $member_id)->with(['report'=>function($query){
            $query->select("id","project_id","is_cancel");
        }]);
        // 按项目名称模糊搜索
        if (!empty($search['name'])) {
            $query->where('name', 'like', '%' . $search['name'] . '%');
        }

        $search['start_date'] = $search['start_date'] ? strtotime($search['start_date']) : "";
        $search['end_date'] = $search['end_date'] ? strtotime($search['end_date']) : "";
        // 按创建时间范围搜索
        if (!empty($search['start_date']) && !empty($search['end_date'])) {
            $query->whereBetween('created_at', [$search['start_date'], $search['end_date']]);
        } elseif (!empty($search['start_date'])) {
            $query->where('created_at', '>=', $search['start_date']);
        } elseif (!empty($search['end_date'])) {
            $query->where('created_at', '<=', $search['end_date']);
        }

        //报备状态
        if (isset($search['report_status']) && ($search['report_status'] !== '' || $search['report_status'] === 0)) {
            if ($search['report_status'] !== "all") {
                $query->where('report_status', $search['report_status']);
            }
        }

        //按采购类型
        if (!empty($search['purchase']) && $search['purchase'] != "all") {
            $query->whereHas('report', function ($query) use ($search) {
              //  $query->where('purchase_mode', $search['purchase']);
                $query->where('purchase_mode', $search['purchase']);
            });
        }
        $search['order'] = $search['order']?$search['order']:"id";
        $search['sort'] = $search['sort'] == "asc"?"asc":"desc";
        $data = $query->orderBy($search['order'], $search['sort'])->paginate(self::PAGE_SIZE);

        $ids = $data->pluck('id')->toArray();

        $memberCarts = DB::table('yz_member_cart as c')
            ->join('yz_goods_option as o', 'o.id', '=', 'c.option_id')
            ->whereIn('c.project_id', $ids)
            ->whereNull('c.deleted_at') // 购物车软删除判断
            ->selectRaw('
        ims_c.project_id,
        SUM(ims_o.product_price * ims_c.total) as total_price
    ')
            ->groupBy('c.project_id')
            ->get()
            ->keyBy('project_id');


        $order_ids  = $data->pluck('order_id')->toArray();


        $orderGoods = OrderGoods::whereIn('order_main_id', $order_ids)
            ->selectRaw('
          order_main_id,
        SUM(goods_price) as total_price
            ')
            ->groupBy('order_main_id')
            ->get()
            ->keyBy('order_main_id');
        $data->transform(function ($item) use ($memberCarts,$orderGoods) {
            if($item->order_status == 0){
                $item->price = $memberCarts[$item->id]['total_price'] ?? 0;
            }else{
                $item->price = $orderGoods[$item->order_id]->total_price ?? 0;
            }


            // 你还可以继续用 $addresses 做处理

            return $item;
        });
        return $data->toArray();
    }

    public function detail(int $id): array
    {
        $detail = ProjectReport::with(['Supplier'=>function($query){
            $query->select("id", "logo", "store_name", "province_id", "city_id","district_id","introduction","address");
        }])->where('project_id',$id)->first();
        if (!$detail) {
            throw new ShopException('报备不存在');
        }
        $charges = SupplierCharge::where('supplier_id', $detail->supplier_id)
            ->select('supplier_id', 'brand_fee', 'bid_document_fee')
            ->first();
        $address = Address::whereIn('id', [$detail->Supplier->province_id, $detail->Supplier->city_id, $detail->Supplier->district_id])->pluck('areaname')->toArray();
        $detail->Supplier->logo = yz_tomedia($detail->Supplier->logo);
        $detail->Supplier->is_bid = $detail->Supplier->is_bid;
        $detail->Supplier->province_name = $address[0]?mb_substr($address[0], 0, -1, "UTF-8"):"";
        $detail->Supplier->city_name = $address[1]?mb_substr($address[1], 0, -1, "UTF-8"):"";
        $detail->bid_fee = $charges ? $charges->bid_document_fee : 0;
        $detail->brand_fee = $charges ? $charges->brand_fee : 0;
        $detail->bond_fee = $charges ? $charges->brand_fee : 0;
        $detail->project_progress = $detail->project_progress ? array_map('intval', explode(",", $detail->project_progress)) : [];
        $detail->company_type = $detail->company_type ? array_map('intval', explode(",", $detail->company_type)) : [];
        $detail->project_file = unserialize($detail->project_file);
        $detail->company_type_data = ProjectProgress::getCompanyType(2);
        $detail->Supplier->address_detail =$address[0].$address[1].$address[2].$detail->Supplier->address;
        $detail->projects_visit = self::projects_visit;
        $brandOrder = Order::where('project_id', $id)->where('order_type', 3)->whereIn('status', [1, 2, 3])->first();
        $detail->is_upgrade = $brandOrder?1:0;

        return $detail->toArray();
    }

    public function getSearchData():array
    {
        $obj = new \Yunshop\Supplier\supplier\services\ReportProjectService();
        $data = $obj->getPurchasingModel()->toArray();
        return $data;
    }

    public function getRecommendBrand(int $project_id): array
    {
        // 1. 获取项目相关商品的 `goods_id`
        /*$goodsIds = MemberCart::where('project_id', $project_id)
            ->pluck('goods_id')
            ->toArray();

        // 2. 统计每个供应商的商品数量及占比
        $supplierStats = Goods::with(['supplierGoods' => function ($query) {
            $query->select("id", "logo", "store_name", "province_id", "city_id", "introduction")
                ->where('status', 1);
        }])
            ->whereIn('id', $goodsIds)
            ->selectRaw('supp_id, SUM(price) as total_price, COUNT(id) as goods_count')
            ->groupBy('supp_id')
            ->orderByDesc('total_price')
            ->orderByDesc('goods_count')
            ->take(3)
            ->get();*/

        $supplierStats = DB::table('yz_member_cart as c')
            ->join('yz_goods as g', 'g.id', '=', 'c.goods_id')
            ->join('yz_goods_option as o', 'o.id', '=', 'c.option_id')
            ->where('c.project_id', $project_id)
            ->whereNull('g.deleted_at')     // 可选：商品软删除判断
            ->whereNull('c.deleted_at')     // 可选：购物车软删除判断
            ->selectRaw('
        ims_g.supp_id,
        SUM(ims_o.product_price) as total_price,
        COUNT(ims_c.id) as goods_count
    ')
            ->groupBy('g.supp_id')
            ->orderByDesc('total_price')
            ->orderByDesc('goods_count')
            ->take(3)
            ->get();



        $supplierIds = $supplierStats->pluck('supp_id')->toArray();
        $suppliers = Supplier::whereIn('id', $supplierIds)
            ->select('id', 'logo', 'store_name', 'province_id', 'city_id', 'introduction','is_bid')
            ->get()
            ->keyBy('id');




      //  $totalGoods = $supplierStats->sum('goods_count');
        $totalGoods = 0;
        $toalPrice = $supplierStats->sum('total_price');

        $supplierIds = $supplierStats->pluck('supp_id')->toArray();

        // 3. 批量查询所有 `SupplierCharge` 记录
        $memberId = \YunShop::app()->getMemberId();
        $follow_list = Follow::getMyFollow($memberId)->with('belongsToSupplier')->get();
        $followSupplierIds = $follow_list->pluck('supplier_id')->toArray();

        // 合并供应商 ID，减少数据库查询
        $allSupplierIds = array_unique(array_merge($supplierIds, $followSupplierIds));
        $supplierCharges = SupplierCharge::whereIn('supplier_id', $allSupplierIds)
            ->select('supplier_id', 'brand_fee', 'bid_document_fee')
            ->get()
            ->keyBy('supplier_id');



        // 4. 批量查询 `Address`，避免 N+1 查询
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

        $recommendBrands =$supplierStats?$supplierStats->map(function ($item) use ($totalGoods, $supplierCharges, $addresses,$toalPrice,$suppliers) {

            $charges = $supplierCharges[$item['supp_id']] ?? null;
            $supplier = $suppliers[$item['supp_id']] ?? null;

            return [
                'id' => $item['supp_id'],
                'logo' => $supplier ? yz_tomedia($supplier->logo) : '',
                'store_name' => $supplier->store_name ?? '',
                'province_name' => isset($addresses[$supplier->province_id]) ? mb_substr($addresses[$supplier->province_id], 0, -1, "UTF-8") : '',
                'city_name' => isset($addresses[$supplier->city_id]) ? mb_substr($addresses[$supplier->city_id], 0, -1, "UTF-8") : '',
                'introduction' => $supplier->introduction ?? '',
                'percentage' => $toalPrice > 0 ? round(($item['total_price'] / $toalPrice) * 100, 2) . '%' : '0%',
                'bid_fee' => $charges->bid_document_fee ?? 0,
                'brand_fee' => $charges->brand_fee ?? 0,
                'is_bid'=>$supplier->is_bid
            ];
        }):[];

        // 6. 处理收藏品牌
        $followBrands = $follow_list->map(function ($item) use ($supplierCharges, $addresses) {
            $charges = $supplierCharges[$item->supplier_id] ?? null;

            return [
                'id' => $item->belongsToSupplier->id,
                'logo' => yz_tomedia($item->belongsToSupplier->logo),
                'store_name' => $item->belongsToSupplier->store_name,
                'province_name' => $addresses[$item->belongsToSupplier->province_id] ?mb_substr($addresses[$item->belongsToSupplier->province_id], 0, -1, "UTF-8"): '',
                'city_name' => $addresses[$item->belongsToSupplier->city_id] ?mb_substr($addresses[$item->belongsToSupplier->city_id], 0, -1, "UTF-8"): '',
                'introduction' => $item->belongsToSupplier->introduction,
                'bid_fee' => $charges ? $charges->bid_document_fee : 0,
                'brand_fee' => $charges ? $charges->brand_fee : 0,
                'is_bid'=>$item->belongsToSupplier->is_bid
            ];
        });

        // 7. 获取采购模式、项目进度和企业类别
        $ReportProjectService = new ReportProjectService;
        $purchasingModes = $ReportProjectService->getPurchasingModel();

        return [
            'recommendBrand' => $recommendBrands->toArray(),
            'followBrandList' => $followBrands->toArray(),
            'purchasingModes' => $purchasingModes,
            'projectProgress' => ProjectProgress::getCompanyType(1),
            'companyType' => ProjectProgress::getCompanyType(2),
        ];
    }


    public function searchBrand(string $name): array
    {
        // 先查询所有符合条件的 Supplier
        $list = Supplier::select("id", "store_name", "logo", "province_id", "city_id", "introduction")
            ->where('store_name', 'like', '%'.$name.'%')
            ->where('status', 1)
            ->get(); // 保持为 Collection，不要 `toArray()`

        // 获取所有 Supplier ID
        $supplierIds = $list->pluck('id')->toArray();

        // 批量查询 SupplierCharge 记录
        $supplierCharges = SupplierCharge::whereIn('supplier_id', $supplierIds)
            ->select('supplier_id', 'brand_fee', 'bid_document_fee')
            ->get()
            ->keyBy('supplier_id'); // 以 supplier_id 作为 key

        // 批量查询所有省份和城市名称，避免 N+1 查询
        $provinceCityIds = $list->pluck('province_id')->merge($list->pluck('city_id'))->unique()->toArray();
        $addresses = Address::whereIn('id', $provinceCityIds)->pluck('areaname', 'id')->toArray();

        // 遍历处理结果
        $result = $list->map(function ($item) use ($supplierCharges, $addresses) {
            $charges = $supplierCharges[$item->id] ?? null;

            return [
                'id' => $item->id,
                'logo' => yz_tomedia($item->logo),
                'store_name' => $item->store_name,
                'province_name' => $addresses[$item->province_id] ?mb_substr($addresses[$item->province_id],0, -1, "UTF-8"):'',
                'city_name' => $addresses[$item->city_id] ?mb_substr($addresses[$item->city_id],0, -1, "UTF-8"):'',
                'introduction' => $item->introduction,
                'bid_fee' => $charges ? $charges->bid_document_fee : 0,
                'brand_fee' => $charges ? $charges->brand_fee : 0
            ];
        });

        return $result->toArray();
    }

    //修改报备
    public function edit(array $request_data): bool
    {
        $project = Project::find($request_data['project_id']);
        if (!$project) {
            throw new AppException("项目不存在");
        }



        $ProjectReport = ProjectReport::where('id', $request_data['id'])->first();

        if($request_data['supplier_id'] != $ProjectReport->supplier_id){

            //如果更换了品牌
            $ProjectReport->delete();
            $ProjectReport = new ProjectReport();

        }

        unset($request_data['id']);
        $request_data['project_progress'] = $request_data['project_progress'] ? implode($request_data['project_progress']) : "";

        $request_data['company_type'] = $request_data['company_type'] ? implode($request_data['company_type']) : "";

        $request_data['purchase_mode'] = $request_data['purchase_mode']?implode(",",$request_data['purchase_mode']):"";

        $ProjectReport->setRawAttributes($request_data);
        //字段检测
        $validator = $ProjectReport->validator($ProjectReport->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new AppException($validator->messages());
        } else {
            //数据保存
            if ($ProjectReport->save()) {
                //显示信息并跳转
                return true;
            } else {
                throw new AppException("修改报备失败");
            }
        }
    }

    //取消报备
    public function cancel(int $id): bool
    {

        $projectReport = ProjectReport::where('id',$id)->first();

        if(!$projectReport){
            throw new ShopException("报备信息不存在");
        }

        try {

            ProjectReport::where('id',$id)->update(
              [
                  'is_cancel'=>1
              ]
            );
            $project = Project::find($projectReport->project_id);
            $project->report_status = 0;
            $project->save();

            return true;
        }catch (\Exception $e){
            throw new AppException($e->getMessage());
        }

    }

    //上传资料
    public function upload(): array
    {
        $file = request()->file('file');

//        $file_result = [];
//        foreach ($uploadedFiles as $file){
            $uploadService = new UploadService();
            try {
                $upload_res = $uploadService->upload($file, 'files');

            } catch (ShopException $exception) {
                throw new ShopException($exception->getMessage());
            }
//        }
        return $upload_res;
    }


}