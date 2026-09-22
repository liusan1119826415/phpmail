<?php

namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\AppException;

use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\project\ProjectReport;
use app\common\modules\pcnotice\Template;
use app\frontend\models\OrderGoods;
use app\frontend\modules\project\models\Follow;
use app\frontend\modules\project\models\Project;
use app\common\models\project\ProjectBid;
use \app\common\models\MemberCart;
use app\frontend\modules\goods\models\Goods;
use app\common\models\project\PurchasingModes;
use app\common\models\project\ProjectProgress;
use app\frontend\modules\project\repositories\FactoryInspectionRepositoryInterface;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierCharge;
use app\common\models\project\ProjectFactoryInspection;
use Illuminate\Support\Facades\DB;
class FactoryInspectionRepository extends BaseRepository implements FactoryInspectionRepositoryInterface
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

        if ($project->report_status != 2) {
            throw new AppException("项目未报备");
        }

        $model = ProjectFactoryInspection::where('project_id', $request_data['project_id'])->first();
        if (!$model) {
            $notific = Template::INSPECTION['SUBMIT_SUCCESS'];
            $model = new ProjectFactoryInspection;
        }else{
            $notific = Template::INSPECTION['SUBMIT_EDIT_SUCCESS'];
        }


        $request_data['member_id'] = \YunShop::app()->getMemberId();
        $request_data['payment_method'] = $request_data['payment_method']?:1;
        $request_data['company_type'] = $request_data['company_type'] ? implode(",",$request_data['company_type']) : "";
        $request_data['projects_visit'] = $request_data['projects_visit'] ? implode(",",$request_data['projects_visit']) : "";
        $model->setRawAttributes($request_data);
        //字段检测
        $validator = $model->validator($model->getAttributes());
        if ($validator->fails()) {//检测失败
            throw new AppException($validator->messages());
        } else {
            //数据保存
            if ($model->save()) {
                $project->factory_status = 2;
                $project->save();
                //显示信息并跳转
                //发送工厂消息
                $option['related_id'] = $project->id;
                app('notification')->send(
                    $request_data['member_id'], // 用户ID
                    Template::AUDIT,
                    Template::FACTORY,
                    $notific,
                    ['inspection_name' => $request_data['name']],
                    $option

                );
                return true;
            } else {
                throw new AppException("申请工厂考察失败");
            }
        }
    }


    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = Project::select("id", "name", "price", "status","activate",  'factory_status',"report_status", "contact_name", "created_at", "updated_at")->where('member_id', $member_id)->where('report_status', 2);
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

        //考察状态
        if (!empty($search['factory_status']) && $search['factory_status'] != "all") {
            $query->where('factory_status', $search['factory_status']);
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

    public function getApplyData(int $id): array
    {
        $detail = ProjectReport::where('project_id', $id)->first();
        if (!$detail) {
            throw new ShopException('考察信息不存在');
        }
        $supplier = Supplier::select("id", "store_name", "logo", "province_id", "city_id", "district_id", "street_id", "address", "introduction")
            ->where('id', $detail->supplier_id)
            ->first();
        $address = Address::whereIn('id', [$supplier->province_id, $supplier->city_id, $supplier->district_id, $supplier->street_id])->pluck('areaname')->toArray();
        $supplier->logo = yz_tomedia($supplier->logo);
        $supplier->addressDetail = $address[0] . $address[1] . $address[2] . $address[3] . $supplier->address;
        $data = [
            'supplier' => $supplier->toArray(),
            'inspection_mode' => self::inspection_mode,
            "company_type" => ProjectProgress::getCompanyType(2),
            "projects_visit" => ProjectProgress::getCompanyType(3),
            'travel_mode' => self::travel_mode,
            'payment_method'=>self::payment_method

        ];
        return $data;
    }

    //获取详情数据
    public function getDetail(int $id):array
    {
        $detail = ProjectFactoryInspection::where('project_id',$id)->first();
        if (!$detail) {
            throw new ShopException('考察信息不存在');
        }

        $supplier = Supplier::select("id", "store_name", "logo", "province_id", "city_id", "district_id", "street_id", "address", "introduction")
            ->where('id', $detail->supplier_id)
            ->first();
        $address = Address::whereIn('id', [$supplier->province_id, $supplier->city_id, $supplier->district_id, $supplier->street_id])->pluck('areaname')->toArray();
        $supplier->logo = yz_tomedia($supplier->logo);
        $supplier->addressDetail = $address[0] . $address[1] . $address[2] . $address[3] . $supplier->address;
        $detail->company_type = !empty($detail->company_type)?explode(",",$detail->company_type):[];
        $detail->projects_visit = !empty($detail->projects_visit)?explode(",",$detail->projects_visit):[];
        return $detail->toArray();
    }

    public function delete(int $id): bool
    {
        $factory = ProjectFactoryInspection::where('id',$id)->first();
        if(!$factory){
            throw new AppException("工厂考察信息不存在");
        }
        try {
            $factory->delete();
            return true;
        }catch (\Exception $e){
            throw new AppException($e->getMessage());
        }

    }

    public function cancelApply(int $id): bool
    {
        $projectFactoryInspection = ProjectFactoryInspection::where('project_id',$id)->first();
        if(!$projectFactoryInspection){
            throw new AppException("未申请工厂考察");
        }
        $project = Project::where('id',$id)->first();
        $project->factory_status =  1;
        $project->save();
        return true;
    }


}