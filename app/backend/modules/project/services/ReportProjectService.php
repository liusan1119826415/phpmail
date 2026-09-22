<?php
namespace app\backend\modules\project\services;
use app\backend\modules\charts\models\Supplier;
use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\project\Project;
use app\common\models\project\ProjectProgress;
use app\common\models\project\ProjectReport;
use app\common\models\project\PurchasingModes;
use app\common\services\Session;
use Yunshop\Supplier\common\models\SupplierCharge;
use Yunshop\Supplier\common\models\SupplierCredential;
class ReportProjectService
{


    public function getPurchasingModel()
    {
        $purchasingModes = PurchasingModes::where('parent_id', 0)
            ->with('hasManyChildren:id,name,parent_id')
            ->get();

        $formattedData = $purchasingModes->flatMap(function ($parent) {
            return $parent->hasManyChildren->map(function ($child) use ($parent) {
                return [
                    'id' => $child->id,  // 使用子级的 id
                    'name' => $parent->name . '-' . $child->name
                ];
            });
        });

        // 加入没有子项的父级数据
        $formattedData = $formattedData->merge($purchasingModes->filter(function ($parent) {
            return $parent->hasManyChildren->isEmpty();
        })->map(function ($parent) {
            return [
                'id' => $parent->id,
                'name' => $parent->name
            ];
        }));
        return $formattedData;
    }

    public function getProvinceData($key,$id=null)
    {
        $uniqueProvincesAndCities = ProjectReport::select($key)
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

       $query = ProjectReport::withTrashed()->with(['project'=>function($query){
           $query->withTrashed()->select("id","name","report_status");
       },'member'=>function($query){
           $query->select("uid","nickname","mobile");
       },'order'=>function($query){
           $query->select("id","status");
       }])->where('is_cancel',0);

       $search = request()->input('search');
       if($search['purchasing_models']){
           $query->where('purchase_mode',$search['purchasing_models']);
       }
       if($search['company_type']){
           $query->whereRaw('FIND_IN_SET(?,company_type)', $search['company_type']);
       }

       if($search['type'] !="all" && $search['type']){
           $query->whereHas('project',function ($query) use($search){
                $query->where('report_status',$search['type']);
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

        // 批量查询 PurchasingModes 和 ProjectProgress，避免在 transform 中的 N+1 查询
        $purchaseModes = PurchasingModes::whereIn('id', $list->pluck('purchase_mode')->flatten()->unique())
            ->pluck('name', 'id') // 获取 id 和 name 键值对
            ->toArray();

        // 获取所有的 project_progress 和 company_type 字段，并拆解成 ID 数组
        $projectProgressIds = $list->pluck('project_progress')
            ->filter()  // 过滤掉空值
            ->flatMap(fn($item) => explode(',', $item))  // 拆分每个字段中的 ID
            ->unique()  // 去重
            ->toArray();

        $companyTypeIds = $list->pluck('company_type')
            ->filter()  // 过滤掉空值
            ->flatMap(fn($item) => explode(',', $item))  // 拆分每个字段中的 ID
            ->unique()  // 去重
            ->toArray();

        // 合并并去重 projectProgressIds 和 companyTypeIds
        $allIds = array_unique(array_merge($projectProgressIds, $companyTypeIds));

        // 查询对应的名称
        $projectProgressNames = ProjectProgress::whereIn('id', $allIds)
            ->pluck('name', 'id')
            ->toArray();

        // 处理分页数据
        $list->transform(function ($item) use ($addresses, $purchaseModes, $projectProgressNames) {
            // 获取省市区信息并拼接地址
            $province = $addresses->get($item->province_id)->areaname ?? '';
            $city = $addresses->get($item->city_id)->areaname ?? '';
            $district = $addresses->get($item->district_id)->areaname ?? '';
            $item->addressDetail = $province . $city . $district . $item->address_detail;

            // 获取采购方式名称
            $purchasingIds = explode(",", $item->purchase_mode);
            $purchasingNames = array_map(fn($id) => $purchaseModes[$id] ?? '', $purchasingIds);
            $item->purchasing = $purchasingNames[0];



            // 获取是否需要制作标书、考察工厂
            $item->need_bid_document_name = $item->need_bid_document == 1 ? "需要制作标书" : "不需要制作标书";
            $item->need_factory_inspection_name = $item->need_factory_inspection == 1 ? "需要考察工厂" : "不需要考察工厂";

            // 获取项目进度名称
            $projectProgressIds = $item->project_progress ? explode(",", $item->project_progress) : [];
            $progressNames = array_map(fn($id) => $projectProgressNames[$id] ?? '', $projectProgressIds);

            $item->project_progress_name = rtrim(implode(",", $progressNames), ",");

            //企业类别
            $companyTypeIds = $item->company_type ? explode(",", $item->company_type) : [];
            $companyTypeNames = array_map(fn($id) => $projectProgressNames[$id] ?? '', $companyTypeIds);
            $item->company_type_name = rtrim(implode(",", $companyTypeNames), ",");
            $item->member->nickname = $this->formatPhoneNumber($item->member->nickname);
            $item->project_file = $item->project_file
                ? array_map('yz_tomedia', unserialize($item->project_file))
                : [];




            if(!empty($item->deleted_at)){
                $name = "已关闭";
            }elseif($item->project->report_status == 1){
                $name = "待确认";
            }elseif($item->project->report_status == 2){
                $name = "已确认";
            }elseif($item->project->report_status == 3){
                $name = "未通过";
            }


            //品牌使用费是否支付
            if(!$item->deleted_at){
                if($item->order){
                    if(in_array($item->order->status,[1,2,3])){
                        $item->pay_status_name = "已支付品牌使用费";
                        $item->pay_status = 1;
                    }elseif($item->order->status == 0){
                        $item->pay_status_name = "未支付品牌使用费";
                        $item->pay_status = 0;
                    }elseif($item->order->status == -1){
                        $item->pay_status_name = "已关闭";
                        $item->pay_status = -1;
                    }
                }else{
                    $item->pay_status_name = "未选择品牌服务";
                    $item->pay_status = 0;
                }
            }


            $item->report_status_name = $name;
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
     * 报备项目详情
     *
     */
    public function getDetail($id)
    {

        $data = ProjectReport::withTrashed()->where('id',$id)->with(['member'=>function($query){
          $query->select("uid","mobile","nickname","avatar");
        },'project'=>function($query){
            $query->select("id","report_status");
        },'order'=>function($query){
            $query->select("id","status");
        }])->first();

        $address = Address::whereIn('id',[$data->province_id,$data->city_id,$data->district_id])->pluck("areaname")->toArray();
        $data->projectAddressDetail = $address[0].$address[1].$address[2].$data->address_detail;
        $data->member->avatar = yz_tomedia($data->member->avatar);
        $purchasingModes = $this->getPurchasingModel()->where('id',$data->purchase_mode)->first();
        $data->purchasingModesName = $purchasingModes['name'];
        $data->project_progress = implode(",",ProjectProgress::whereIn('id',explode(',',$data->project_progress))->pluck("name")->toArray());
        $data->project_file = $data->project_file
            ? array_map('yz_tomedia', unserialize($data->project_file))
            : [];
        $data->company_type_name = implode(",",ProjectProgress::whereIn('id',explode(',',$data->company_type))->pluck("name")->toArray());
        //品牌使用费
        $charges = SupplierCharge::where('supplier_id', $data->supplier_id)
            ->select('supplier_id', 'brand_fee', 'bid_document_fee')
            ->first();
        $data->brand_fee = $charges->brand_fee?:0;
        if($data->order){
            if(in_array($data->order->status,[1,2,3])){
                $data->pay_status_name = "已支付";
                $data->pay_status = 1;
            }elseif($data->order->status == 0){
                $data->pay_status_name = "未支付";
                $data->pay_status = 0;
            }elseif($data->order->status == -1){
                $data->pay_status_name = "已关闭";
                $data->pay_status = -1;
            }
        }else{
            $data->pay_status_name = "未支付";
            $data->pay_status = 0;
        }


        if(!empty($data->deleted_at)){
            $name = "已关闭";
        }elseif($data->project->report_status == 1){
            $name = "待确认";
        }elseif($data->project->report_status == 2){
            $name = "已确认";
        }elseif($data->project->report_status == 3){
            $name = "未通过";
        }

        $data->report_status_name = $name;
        return $data;
    }

    /**
     * 报备审核
     */
    public function apply($id,$status)
    {
      $projectReport = ProjectReport::where('id',$id)->first();
      if(!$projectReport){
          throw new ShopException('未找到项目报备');
      }

      $reject_reason = request()->input('reject_reason');

        try {
            $project = Project::where('id',$projectReport->project_id)->first();

            if($project->report_status  == $status){
                $status_name = $status == 2?"已确认":"已拒绝";
                throw new ShopException('当前状态已经是'.$status_name);
            }
            if($status == 2){
                //审核通过
                $project->bid_status = 1;
                $project->report_status = 2;
                $project->save();
                $projectReport->approved_time = time();
                $projectReport->save();
            }elseif($status == 3){

                $project->report_status = 3;
                $project->save();
                $projectReport->reject_reason = $reject_reason;
                $projectReport->reject_time = time();
                $projectReport->save();
            }elseif($status == 5){
                //撤销审核通过
                $project->bid_status = 0;
                $project->report_status = 1;
                $project->save();
                $projectReport->approved_time = 0;
                $projectReport->save();
            }elseif($status == 6){
               //撤销驳回
                $project->report_status = 1;
                $project->save();
                $projectReport->reject_reason = "";
                $projectReport->reject_time = 0;
                $projectReport->save();
            }
        }catch (\Exception $e){
          throw new ShopException($e->getMessage());
        }

    }




}