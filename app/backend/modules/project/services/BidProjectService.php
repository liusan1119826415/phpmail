<?php
namespace app\backend\modules\project\services;
use app\backend\modules\charts\models\Supplier;
use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\Order;
use app\common\models\project\Project;
use app\common\models\project\ProjectFactoryInspection;
use app\common\models\project\ProjectProgress;
use app\common\models\project\ProjectBid;
use app\common\models\project\PurchasingModes;
use app\common\services\Session;
use Yunshop\Supplier\common\models\SupplierCredential;
class BidProjectService
{




    public function getProvinceData($key,$id=null)
    {
        $uniqueProvincesAndCities = ProjectBid::select($key)
            ->distinct()
            ->get();
        $query = Address::whereIn('id', $uniqueProvincesAndCities->pluck($key)->toArray());
        if($key == "city"){
            $query->where('parent_id',$id);
        }
        $province = $query->get();
        return $province;
    }

    public function index()
    {

       $query = ProjectBid::query()->with(['project'=>function($query){
           $query->withTrashed()->select("id","name","bid_status");
       },'member'=>function($query){
           $query->select("uid","nickname","mobile");
       }])->where('is_revoke',0);

       $search = request()->input('search');
       if($search['bid_type']){
           $query->where('bid_type',$search['bid_type']);
       }
       if($search['company_type']){
           $query->whereRaw('FIND_IN_SET(?,company_type)', $search['company_type']);
       }

       if($search['type'] !="all" && $search['type']){
           $query->whereHas('project',function ($query) use($search){
                $query->where('bid_status',$search['type']);
           });
       }

        if($search['province_id']){
            $query->where('province_id', $search['province_id']);
        }

        if($search['city_id']){
            $query->where('city_id', $search['city_id']);
        }
       if($search['name'] && $search['search_type']){
              if($search['search_type'] == 1){
                  $query->where('name','like','%'.$search['name'].'%');
              }
              if($search['search_type'] == 2){
                  $query->whereHas('project',function ($query) use($search){
                     // $query->where('')
                  });
              }
       }

       //搜索日期
        if($search['start_time']&& $search['end_time'] && $search['time_type']){
            $arr = [$search['start_time'],$search['end_time']];
            if($search['time_type'] == 1){
                $query->whereBetween('created_at',$arr);
            }elseif($search['time_type'] == 2){
                $query->whereBetween('approved_time',$arr)->whereHas('project',function ($query) use($search){
                    $query->where('report_status',3);
                });
            }elseif($search['time_type'] == 3){
                $query->whereBetween('approved_time',$arr)->whereHas('project',function ($query) use($search){
                    $query->where('report_status',2);
                });
            }elseif($search['time_type'] == 4){
                $query->whereBetween('approved_time',$arr)->whereHas('project',function ($query) use($search){
                    $query->where('report_status',4);
                });
            }
        }

       $list = $query->orderBy('created_at','desc')->paginate(20);

        // 获取所有的 province_id, city_id, district_id，避免多次查询
        $provinceIds = $list->pluck('province_id')->unique()->toArray();
        $cityIds = $list->pluck('city_id')->unique()->toArray();
        $districtIds = $list->pluck('district_id')->unique()->toArray();

        // 批量查询 Address 表，避免 N+1 查询
        $addresses = Address::whereIn('id', array_merge($provinceIds, $cityIds, $districtIds))
            ->get()
            ->keyBy('id');  // 使用 keyBy 将查询结果按 id 索引，便于后续匹配



        $companyTypeIds = $list->pluck('company_type')
            ->filter()  // 过滤掉空值
            ->flatMap(fn($item) => explode(',', $item))  // 拆分每个字段中的 ID
            ->unique()  // 去重
            ->toArray();

        // 合并并去重 projectProgressIds 和 companyTypeIds
        $allIds = $companyTypeIds;

        // 查询对应的名称
        $projectProgressNames = ProjectProgress::whereIn('id', $allIds)
            ->pluck('name', 'id')
            ->toArray();

        // 处理分页数据
        $list->transform(function ($item) use ($addresses, $projectProgressNames) {
            $item->bid_files = unserialize($item->bid_files);
            // 获取省市区信息并拼接地址
            $province = $addresses->get($item->province_id)->areaname ?? '';
            $city = $addresses->get($item->city_id)->areaname ?? '';
            $district = $addresses->get($item->district_id)->areaname ?? '';
            $item->addressDetail = $province . $city . $district . $item->address_detail;

            // 获取投标方式
            $item->bid_type_name = $item->bid_type == 1?"工厂直投":"授权投标";

            // 获取是否需要制作标书、考察工厂
            $item->need_bid_document_name = $item->need_bid_document == 1 ? "需要制作标书" : "不需要制作标书";


            //企业类别
            $companyTypeIds = $item->company_type ? explode(",", $item->company_type) : [];
            $companyTypeNames = array_map(fn($id) => $projectProgressNames[$id] ?? '', $companyTypeIds);
            $item->company_type_name = rtrim(implode(",", $companyTypeNames), ",");
            $item->member->nickname = $this->formatPhoneNumber($item->member->nickname);
            if($item->project->bid_status == 1){
                $name = "已报备";
            }elseif($item->project->bid_status == 2){
                $name = "申请中";
            }elseif($item->project->bid_status == 3){
                $name = "未通过";
            }elseif($item->project->bid_status == 4){
                $name = "投标中";
            }elseif($item->project->bid_status == 5){
                $name = "投标结束";
            }
            $item->bid_status_name = $name;
            return $item;
        });

        return $list;
    }


    /**
     * 格式化手机号
     */
    private function formatPhoneNumber($phone)
    {
        // 正则替换手机号，将中间部分替换为星号
        return preg_replace('/(\d{2})\d{5}(\d{2})/', '$1*******$2', $phone);
    }

    /**
     *
     * 获取投标详情
     */

    public function getDetail($id)
    {
       $data = ProjectBid::where('id',$id)->with(['member'=>function($query){
           $query->select("uid","mobile","nickname","avatar");
       },'project'])->first();

        $address = Address::whereIn('id',[$data->province_id,$data->city_id,$data->district_id])->pluck("areaname")->toArray();
        $data->projectAddressDetail = $address[0].$address[1].$address[2].$data->address_detail;

        $data->company_type_name = implode(",",ProjectProgress::whereIn('id',explode(',',$data->company_type))->pluck("name")->toArray());

        $data->project_file = $data->project_file
            ? array_map('yz_tomedia', unserialize($data->project_file))
            : [];
        $data->authorized_person_idcard = yz_tomedia($data->authorized_person_idcard);
        $orders = Order::where('bid_id',$data->id)->where('status','!=','-1')->get();
        // 遍历订单，按类型提取状态
        if($orders){
            foreach ($orders as $order) {
                switch ($order->order_type) {
                    case 2:
                        $data->bid_document_status = in_array($order->status,[1,2,3])?1:0;
                        break;
                    case 3:
                        $data->brand_use_status = in_array($order->status,[1,2,3])?1:0;
                        break;
                    case 4:
                        $data->project_deposit_status = in_array($order->status,[1,2,3])?1:0;
                        break;
                }
            }
        }else{
            $data->bid_document_status = 0;
            $data->brand_use_status = 0;
            $data->project_deposit_status = 0;
        }

        $data->bid_files = $data->bid_files?unserialize($data->bid_files):[];






       return $data;
       
    }

    /*public function apply($id,$status)
    {
        $projectBid = ProjectBid::where('id',$id)->first();
        if(!$projectBid){
            throw new ShopException('未找到投标项目');
        }
        //判断当前投标的项目是否已经支付了项目保证金已经投标金额
        $orderTypes = Order::where('bid_id',$projectBid->id)->where('status',0)->pluck('order_type')->toArray();
        if(in_array(2,$orderTypes)){
            throw new ShopException('当前投标有投标标书制作费订单未支付');
        }
        if(in_array(3,$orderTypes)){
            throw new ShopException('当前投标有品牌使用费订单未支付');
        }

        if(in_array(4,$orderTypes)){
            throw new ShopException('当前投标有项目保证金订单未支付');
        }
        try {
            $project = Project::where('id',$projectBid->project_id)->first();
            if($project->bid_status  == $status){
                throw new ShopException('当前已经是这个状态');
            }
            if($status == 4){
                //审核通过
                $project->bid_status = 4;
                $project->save();
                $projectBid->approved_time = time();
                $projectBid->save();
            }elseif($status == 3){
                $project->bid_status = 3;
                $project->save();
            }
        }catch (\Exception $e){
            throw new ShopException($e->getMessage());
        }

    }*/



    public function apply($id, $status)
    {
        $projectBid = ProjectBid::where('id', $id)->first();
        if (!$projectBid) {
            throw new ShopException('未找到投标项目');
        }

        // 若为撤销申请(5) 或 撤销驳回(6)，不校验未支付订单
        if (!in_array($status, [5, 6, 3])) {
            $orderTypes = Order::where('bid_id', $projectBid->id)->where('status', 0)->pluck('order_type')->toArray();
            if (in_array(2, $orderTypes)) {
                throw new ShopException('当前投标有投标标书制作费订单未支付');
            }
            if (in_array(3, $orderTypes)) {
                throw new ShopException('当前投标有品牌使用费订单未支付');
            }
            if (in_array(4, $orderTypes)) {
                throw new ShopException('当前投标有项目保证金订单未支付');
            }
        }

        try {
            $project = Project::where('id', $projectBid->project_id)->first();
            if ($project->bid_status == $status) {
                throw new ShopException('当前已经是这个状态');
            }

            if ($status == 4) {
                // 审核通过
                $project->bid_status = 4;
                $project->save();
                $projectBid->approved_time = time();
                $projectBid->save();
            } elseif ($status == 3) {
                // 驳回申请
                $project->bid_status = 3;
                $project->save();
            } elseif ($status == 5) {
                // 撤销审核通过
                $project->bid_status = 2;
                $project->save();
                $projectBid->approved_time = 0;
                $projectBid->save();
            } elseif ($status == 6) {
                $project->bid_status = 2;
                $project->save();
            } else {
                throw new ShopException('不支持的状态操作');
            }

        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }
    }






}