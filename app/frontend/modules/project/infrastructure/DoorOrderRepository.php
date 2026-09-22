<?php

namespace app\frontend\modules\project\infrastructure;

use app\common\exceptions\AppException;


use app\common\models\Address;
use app\common\models\Order;
use app\common\models\project\ProjectReport;
use app\common\modules\pcnotice\Template;
use app\frontend\modules\order\services\OrderService as BaseOrderService;
use app\frontend\modules\project\repositories\DoorOrderRepositoryInterface;
use app\common\models\project\ProjectDoor;
use app\frontend\modules\project\models\Project;
use Illuminate\Support\Facades\DB;
use Yunshop\Supplier\common\models\SupplierCharge;
use Yunshop\Supplier\common\models\SupplierDoor;
use Yunshop\Supplier\common\models\SupplierService;
use Yunshop\Supplier\common\models\SupplierServiceFee;
class DoorOrderRepository extends BaseRepository implements DoorOrderRepositoryInterface
{


    protected $request_data;

    const serviceData = [
        [
            "id" => 1,
            "name" => "办公家具"
        ],
        [
            "id" => 2,
            "name" => "医养家具"
        ],
        [
            "id" => 3,
            "name" => "酒店公寓"
        ],
        [
            "id" => 4,
            "name" => "政法家具"
        ],
        [
            "id" => 5,
            "name" => "实验室家具"
        ],
        [
            "id" => 6,
            "name" => "教育家具"
        ],
    ];


    const serviceTypeData = [
        [
            "id" => 1,
            "name" => "测量"
        ],
        [
            "id" => 2,
            "name" => "绘制CAD"
        ],
        [
            "id" => 3,
            "name" => "绘制效果图"
        ],
        [
            "id" => 4,
            "name" => "方案讲解"
        ],
        [
            "id" => 5,
            "name" => "项目负责人"
        ]
    ];


    public function reqDoor(array $request_data): bool
    {

        try {
            $project = Project::find($request_data['project_id']);
            if (!$project) {
                throw new AppException("项目不存在");
            }

            if($request_data['number']>4){
                throw new AppException("协助人数不得超过4人");
            }

            if($request_data['service_day']>5){
                throw new AppException("服务天数不得超过5天");
            }

            $this->request_data = $request_data;
            //检查项目是否报备
            $projectReport = ProjectReport::where('project_id', $request_data['project_id'])->first();
            if (!$projectReport) {
                throw new AppException("项目未报备");
            }
            if ($project->report_status != 2) {
                throw new AppException("项目报备状态未审核");
            }

            $request_data['member_id'] = \YunShop::app()->getMemberId();
            $scene_img = request()->input('scene_img');

            $build_img = request()->input('build_img');
            $remark = request()->input('remark');
            $request_data['scene_img'] = $scene_img ? serialize($scene_img) : serialize([]);
            $service_type = [];
            if($request_data['service_type'] && is_array($request_data['service_type'])){
                $service_type = $request_data['service_type'];
                $request_data['service_type'] = implode(",",$request_data['service_type']);
            }
            $request_data['build_img'] = $build_img ? serialize($build_img) : serialize([]);
            $request_data['remark'] = $remark;
            $request_data['upgrade'] = request()->input('upgrade');
            $model = new ProjectDoor;
            $model->setRawAttributes($request_data);
            //字段检测
            $validator = $model->validator($model->getAttributes());
            if ($validator->fails()) {//检测失败
                throw new AppException($validator->messages());
            } else {
                //数据保存
                if ($model->save()) {
                    //获取服务费
                    $service_fees = SupplierServiceFee::whereIn('id',$service_type)->get();
                    $total_fee = $service_fees->sum(function ($fee) use ($request_data) {
                        return $fee->price * $request_data['number'] * $request_data['service_day'];
                    });
                    //检查
                    $brandOrder = Order::where('project_id', $request_data['project_id'])->where('order_type', 3)->whereIn('status', [1, 2, 3])->first();
                    if ($brandOrder) {
                        //如果有支付了品牌使用费
                        $is_pay =1;
                        $this->generateOrder($request_data['project_id'], $projectReport, $total_fee, 5, 4, $model->id,$total_fee,$is_pay);
                    } else {

                        if ($request_data['upgrade'] == 1) {
                            $brandOrder = Order::where('project_id', $request_data['project_id'])->where('order_type', 3)->where('status', 0)->first();
                            //如果没有待支付的品牌订单
                            if (!$brandOrder) {
                                //如果没有支付品牌
                                //获取品牌使用费
                                $charges = SupplierCharge::where('supplier_id', $projectReport->supplier_id)
                                    ->select('supplier_id', 'brand_fee', 'bid_document_fee')
                                    ->first();
                                //品牌订单
                                $this->generateOrder($request_data['project_id'], $projectReport, $charges->brand_fee ?: 0, 3, 0, 0,$charges->brand_fee ?: 0,);

                            }
                        }

                        $this->generateOrder($request_data['project_id'], $projectReport, $total_fee?:0, 5, 4, $model->id,$total_fee);
                    }
                    return true;
                } else {
                    throw new AppException("申请上门失败");
                }
            }
        } catch (\Exception $e) {
            throw new AppException($e->getMessage());
        }

    }

    public function getProjectService(int $project_id): array
    {
        $project = Project::find($project_id);
        if (!$project) {
            throw new AppException("项目不存在");
        }
        //检查项目是否报备
        $projectReport = ProjectReport::where('project_id', $project_id)->first();
        if (!$projectReport) {
            throw new AppException("项目未报备");
        }
        if ($project->report_status != 2) {
            throw new AppException("项目报备状态未审核");
        }

        $brandOrder = Order::where('project_id', $project_id)->where('order_type', 3)->whereIn('status', [1, 2, 3])->first();
        if (!$brandOrder) {
            //获取品牌使用费
            $charges = SupplierCharge::where('supplier_id', $projectReport->supplier_id)
                ->select('supplier_id', 'brand_fee', 'bid_document_fee')
                ->first();
            return [
                'status' => -1,//未升级
                'brandFee' => $charges->brand_fee ?: 0,
                'doorFee' => 0,
            ];
        } else {
            return [
                'status' => 1, //已升级
                'brandFee' => 0,
                'doorFee' => 0
            ];
        }
    }


    public function getProjectList(): array
    {
        $memberId = \YunShop::app()->getMemberId();

        $list = DB::table('yz_my_project as p')
            ->leftJoin('yz_project_report as r', 'p.id', '=', 'r.project_id')
            ->where('p.member_id', $memberId)
            ->where('p.report_status', 2)
            ->whereNull('p.deleted_at')
            ->orderBy('r.approved_time', 'desc')
            ->select('p.id', 'p.name', 'r.id as report_id', 'r.supplier_id') // 明确列出 report 字段
            ->get();

        // 重组结果为 report 子数组结构
        return $list->map(function ($item) {
            return [
                'id'     => $item['id'],
                'name'   => $item['name'],
                'report' => [
                    'id'            => $item['report_id'],
                    'supplier_id' => $item['supplier_id'],

                ],
            ];
        })->toArray();
    }



    protected function generateOrder($project_id, $projectReport, $price, $order_type, $status, $door_id,$goods_price,$is_pay=0)
    {
        $time = time();
        $brandOrderData['uniacid'] = \YunShop::app()->uniacid;
        $brandOrderData['uid'] = \YunShop::app()->getMemberId();
        $brandOrderData['order_sn'] = BaseOrderService::createOrderSN();
        $brandOrderData['create_time'] = $time;
        $brandOrderData['project_id'] = $project_id;
        $brandOrderData['report_id'] = $projectReport->id;
        $brandOrderData['supp_id'] = $projectReport->supplier_id;
        $brandOrderData['created_at'] = $time;
        $brandOrderData['updated_at'] = $time;
        $brandOrderData['order_type'] = $order_type;
        $brandOrderData['goods_price'] = $goods_price;

        $brandOrderData['price'] = $price;
        $brandOrderData['door_id'] = $door_id;
        $brandOrderData['status'] = $status;
        if($is_pay == 1){
            $brandOrderData['discount_price'] = $goods_price;
        }
        $order_id = Order::insertGetId($brandOrderData);

        $option['related_id'] = $order_id;
        app('notification')->send(
            $brandOrderData['uid'], // 用户ID
            Template::ORDER,
            Template::ORDER_TYPE[$order_type],
            getNoticeTitle($order_type,"SUBMIT"),
            ['order_no' => $brandOrderData['order_sn'],'appointment_time'=>$this->request_data['door_time']],
            $option

        );
    }


    private function getWhere($status){
        $arr = [
            1=>4,
            2=>0,
            3=>3,
            -1=>-1
        ];
        return $arr[$status];
    }

    public function getList(array $search): array
    {
        $member_id = \YunShop::app()->getMemberId();
        $query = app('OrderManager')->make('Order')->where('order_type', 5)->where('uid', $member_id)->with(['projectDoor'=>function($query){
            $query->select('id','service_id','service_type','project_area','province_id','city_id','district_id');
        },'project'=>function($query){
            $query->withTrashed()->select("id","name");
        }]);
        $query->where('is_member_deleted', 0);
        if (isset($search['status']) && ($search['status'] !== '' || $search['status'] === 0)) {

            if ($search['status'] !== "all") {
                $query->where('status', $this->getWhere($search['status']));
            }
        }
        if($search['service_id']){
           $query->whereHas('projectDoor',function ($query) use ($search){
               $query->where('service_id',$search['service_id']);
           });
        }

        if($search['id']){
            $query->where('id', $search['id']);
        }

        if($search['name']){
            if(is_numeric($search['name'])) { // 检查是否为数字
                $query->where('id', $search['name']);
            } else {
                $query->whereHas('project', function ($query) use($search) {
                    $query->where('name', 'like', '%' . $search['name'] . '%'); // 模糊匹配名称
                });
            }
        }



        // 按创建时间范围搜索
        if (!empty($search['start_date']) && !empty($search['end_date'])) {
            $query->whereBetween('create_time', [$search['start_date'], $search['end_date']]);
        } elseif (!empty($search['start_date'])) {
            $query->where('create_time', '>=', $search['start_date']);
        } elseif (!empty($search['end_date'])) {
            $query->where('create_time', '<=', $search['end_date']);
        }

        $list = $query->orderBy('create_time','desc')->paginate(self::PAGE_SIZE);

        $provinceIds = $list->pluck('projectDoor.province_id')->unique()->filter()->toArray();
        $cityIds = $list->pluck('projectDoor.city_id')->unique()->filter()->toArray();
        $districtIds = $list->pluck('projectDoor.district_id')->unique()->filter()->toArray();

        $arr1 = array_merge($provinceIds, $cityIds);
        $merageArr = array_merge($arr1,$districtIds);
        $addresses = Address::whereIn('id', $merageArr)
            ->pluck('areaname', 'id');
        $service_ids = $list->pluck('projectDoor.service_id')->unique()->filter()->toArray();

        $supplier_service = SupplierService::withTrashed()->whereIn('id',$service_ids)->with(['service'])->get();

        $supplier_service_map = $supplier_service->keyBy('id');

        $service_type_ids = $list->pluck('projectDoor.service_type')
            ->flatMap(function ($type) {
                return explode(',', $type); // 拆分字符串
            })
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $supplier_service_fee = SupplierServiceFee::withTrashed()->whereIn('id', $service_type_ids)->with(['service'])->get();

        $service_fee_map = $supplier_service_fee->keyBy('id');

        $list->transform(function ($item) use ($addresses,$supplier_service_map,$service_fee_map) {
            $province_name = $addresses[$item->projectDoor->province_id] ?? '';
            $city_name = $addresses[$item->projectDoor->city_id] ?? '';
            $district_name = $addresses[$item->projectDoor->district_id] ?? '';
            $item->information = $item->information?yz_tomedia($item->information):"";
            $item->addressDetail = $province_name.$city_name.$district_name.$item->projectDoor->address_detail;
            $item->service_name = optional($supplier_service_map[$item->projectDoor->service_id]->service ?? null)->name ?? '';
            $service_type_ids = explode(',', $item->projectDoor->service_type);
            $order_price = $item->goods_price;
            if($item->extra_enable == 1){
                $order_price+=$item->extra_amount;
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


    public function getAfterSales(array $search)
    {
        $member_id = \YunShop::app()->getMemberId();

        $query = app('OrderManager')->make('Order')->where('order_type', 5)->where('refund_id','>',0)->where('uid', $member_id)->with(['project'=>function($query){
            $query->withTrashed()->select("id","name");
        },'hasOneRefundApply']);

        if($search['refund_status']){
           $query->where('refund_status',$search['refund_status']);
        }

        if($search['name']){
            $query->whereHas('project',function ($query) use ($search){
                $query->where('name','like','%'.$search['name'].'%');
            });
        }

        $list = $query->orderBy('create_time','desc')->paginate(self::PAGE_SIZE);
        $list->transform(function ($item) {

             return [
                 'id' => $item->id,
                 'refund_id'=>$item->hasOneRefundApply->id,
                 "project_name"=>$item->project->name,
                 'refund_price'=>$item->hasOneRefundApply->price,
                 'status_name' =>$item->hasOneRefundApply->status_name,
                 'create_time'=>date("Y-m-d H:i:s",$item->hasOneRefundApply->created_at),
             ];

        });


        return $list;



    }


    public function acceptanceCheck(int $id):bool
    {

        try {
            $order = Order::find($id);
            if(!$order){
                throw new AppException('未找到订单');

            }
            $order->status = Order::WAIT_PAY; //已确认
            $order->product_time = time();
            $order->save();
            return true;
        }catch (\Exception $e)
        {
            throw new AppException($e->getMessage());
        }

    }

    public function getDetail(int $id):array
    {
        $detail = app('OrderManager')->make('Order')->where('id',$id)->with(['projectDoor','project'=>function($query){
            $query->select("id","name");
        }])->first();
        if(!$detail){
            throw new AppException("未找到订单");
        }
        $address = Address::whereIn('id', [$detail->projectDoor->province_id,$detail->projectDoor->city_id,$detail->projectDoor->district_id])
            ->pluck('areaname')->toArray();
        $supplier_service = SupplierService::withTrashed()->where('id',$detail->projectDoor->service_id)->with(['service'])->first();
        $detail->addressDetail = $address[0].$address[1].$address[2].$detail->projectDoor->address_detail;
        $detail->service_name = $supplier_service->service->name;
        $supplier_service_fee = SupplierServiceFee::withTrashed()->whereIn('id', explode(",",$detail->projectDoor->service_type))->with(['service'])->get();
        $detail->service_fee_list = $supplier_service_fee
            ->pluck('service.name')  // 提取 service 的 name
            ->filter()               // 过滤掉 null 值（如果有些没有关联）
            ->values()               // 重建索引
            ->toArray();             // 转为普通数组
        $detail->information = $detail->information?yz_tomedia($detail->information):"";
        $brandOrder = Order::where('project_id',$detail->project_id)->where('order_type',3)->whereIn('status',[1,2,3])->first();
        $detail->is_discount =$brandOrder?1:0;
        $detail->service_base_price = $detail->goods_price;
        $detail->discount_price = $brandOrder?$detail->goods_price:0;
        $detail->extra_amount = $detail->extra_enable == 1?$detail->extra_amount:0;
        $detail->price =  $detail->goods_price - $detail->discount_price + $detail->extra_amount;
        return $detail->toArray();

    }

    public function addAmount($id,$amount,$extra_enable):bool
    {

        try{
            $order = Order::find($id);
            $order->extra_enable = $extra_enable;
            $order->extra_amount = $amount;
            $order->save();
            return true;
        }catch (\Exception $e){
            throw new AppException($e->getMessage());
        }


    }

    public function getServiceFee(int $supplier_id):array
    {
        $service_menu = SupplierService::where('supplier_id',$supplier_id)->with(['service'])->get();

        $service_type_data = SupplierServiceFee::where('supplier_id',$supplier_id)->with(['service'])->get();

        return [
          "service_menu"=>$service_menu,
          "service_type_data"=>$service_type_data,
        ];

    }

    //获取上门订单费用
    public function getDoorFee(int $order_id):array
    {
      $order = Order::with(['projectDoor'])->find($order_id);
      if(!$order){
          throw new AppException("未找到上门订单");
      }
      $data = [];
      //检查项目是否已经服务升级
      $data['project_name'] = Project::where('id',$order->projectDoor->project_id)->value("name");
      $brandOrder = Order::where('project_id', $order->projectDoor->project_id)->where('order_type', 3)->whereIn('status', [1, 2, 3])->first();
      $data['is_upgrade'] = $brandOrder?1:0;
      $data['base_price'] = $order->goods_price;
      $data['discount_price'] = $brandOrder?$order->goods_price:0;
      $data['extra_amount'] = $order->extra_amount?:0;

      return $data;
    }






}