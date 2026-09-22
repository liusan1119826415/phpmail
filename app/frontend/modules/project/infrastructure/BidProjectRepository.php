<?php

namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\AppException;

use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\Order;
use app\common\models\project\ProjectReport;
use app\common\modules\pcnotice\Template;
use app\frontend\models\OrderGoods;
use app\frontend\modules\project\models\Follow;
use app\frontend\modules\project\models\Project;
use app\frontend\modules\project\repositories\BidProjectRepositoryInterface;
use app\common\models\project\ProjectBid;
use \app\common\models\MemberCart;
use app\frontend\modules\goods\models\Goods;
use app\common\models\project\PurchasingModes;
use app\common\models\project\ProjectProgress;
use app\frontend\modules\project\services\BidOrderService;
use app\frontend\modules\project\services\OrderService;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierBidFiles;
use Yunshop\Supplier\common\models\SupplierCharge;
use Illuminate\Support\Facades\DB;
class BidProjectRepository extends BaseRepository implements BidProjectRepositoryInterface
{

    public function __construct(Project $model)
    {
        parent::__construct($model);
    }


    public function applyFor(array $request_data): int
    {

        $project = Project::find($request_data['project_id']);
        if (!$project) {
            throw new AppException("项目不存在");
        }

        if($project->report_status != 2){
            throw new AppException("项目未报备");
        }
       $supplier = Supplier::where('id',$request_data['supplier_id'])->first();
       if(!$supplier){
           throw new AppException("品牌不存在");
       }
        $ProjectReport = ProjectBid::where('project_id', $request_data['project_id'])->first();


        if ($ProjectReport) {
            $bid_id =$ProjectReport->id;
            $model = $ProjectReport;
            $notific = Template::BID['SUBMIT_EDIT'];

        }else{
            $bid_id = 0;
            $model = new ProjectBid;
            $notific = Template::BID['SUBMIT_SUCCESS'];
        }


        if($request_data['bid_type'] == 1){
             //工厂直投
            if(!$request_data['authorized_person_name']){
                throw new AppException("请填写授权人姓名");
            }
            if(!$request_data['authorized_person_phone']){
                throw new AppException("请填写授权人手机");
            }
            if(!$request_data['authorized_person_idcard']){
                throw new AppException("请上传授权人身份证");
            }
        }
        $request_data['project_file'] = !empty($request_data['project_file'])?serialize($request_data['project_file']):serialize([]);
        $request_data['company_type'] = $request_data['company_type'] ? implode($request_data['company_type']) : "";
        $request_data['member_id'] = \YunShop::app()->getMemberId();
        $request_data['amount'] = $request_data['amount']?:0;
        $request_data['is_revoke'] = 0;
        $model->setRawAttributes($request_data);

        //字段检测
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new AppException($validator->messages());
        } else {
            //数据保存
            if ($model->save()) {

                //投标订单业务
                $project->bid_status = 2;
                $project->save();
                if($bid_id){
                    $bid_id = $bid_id;
                }else{
                    $bid_id =  $model->id;
                }

                $data['project_id'] = $model->project_id;
                $data['bid_id'] = $bid_id;
                $data['supplier_id'] = $model->supplier_id;
                $data['bid_type'] = $model->bid_type;
                $data['need_bid_document'] = $model->need_bid_document;
                $data['need_bid_bond'] = $model->need_bid_bond;
                $data['amount'] = $request_data['amount'];
                $orderService = new OrderService($data);
                $orderService->generateOrder();
                $option['related_id'] = $project->id;
                //发送投标信息
                app('notification')->send(
                    $request_data['member_id'], // 用户ID
                    Template::AUDIT,
                    Template::BID_NAME,
                    $notific,
                    ['bid_name' => $request_data['name']],
                    $option

                );
                return 1;
            } else {
                throw new AppException("申请投标失败");
            }
        }
    }


    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = Project::select("id", "name", "price", "status", "bid_status","activate",  "report_status","contact_name","order_status","order_id", "created_at", "updated_at")->where('member_id', $member_id)->where('report_status',2);
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
        if (!empty($search['bid_status']) && $search['bid_status'] != "all") {
            $query->where('bid_status', $search['bid_status']);
        }

        //投标方式
        if (!empty($search['bid_type'])) {
            $query->whereHas('bid', function ($query) use ($search) {
                $query->where('bid_type', $search['bid_type']);
            });
        }
        $search['order'] = $search['order']?$search['order']:"id";
        $search['sort'] = $search['sort'] == "asc"?"asc":"desc";
        $data =$query->orderBy($search['order'], $search['sort'])->paginate(self::PAGE_SIZE);

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


    public function cancelApply(int $id): bool
    {
        try{
            $projectFactoryInspection = ProjectBid::where('project_id',$id)->first();
            $res = Order::where('bid_id',$projectFactoryInspection->id)->whereIn('status',[1,2,3])->get();
            if($res->isNotEmpty()){
                throw new AppException("投标订单已经支付了不能撤销");
            }
            if(!$projectFactoryInspection){
                throw new AppException("未申请投标");
            }
            ProjectBid::where('id',$projectFactoryInspection->id)->update(
                [
                    'is_revoke'=>1
                ]
            );

            $project = Project::where('id',$id)->first();
            $project->bid_status =  1;
            $project->save();
            //关闭投标订单业务
            $updateData['status'] = Order::CLOSE;
            Order::where('bid_id',$projectFactoryInspection->id)->update($updateData);
            return true;
        }catch (\Exception $e){
            throw new AppException($e->getMessage());
        }

    }




    public function detail(int $id): array
    {
        $detail = ProjectBid::find($id);
        if(!$detail){
            $detail = ProjectBid::where('project_id',$id)->first();
        }
        if (!$detail) {
            throw new ShopException('投标不存在');
        }
        $detail->company_type = $detail->company_type ? explode(",",$detail->company_type) : [];

        $detail->project_file = $detail->project_file?unserialize($detail->project_file):[];


        return $detail->toArray();
    }

    public function getRecommendBrand(int $project_id): array
    {
        // 1. 获取项目相关商品的 `goods_id`
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
            ->take(3); // 取前 3 个供应商

        $totalGoods = $supplierStats->sum('goods_count');
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