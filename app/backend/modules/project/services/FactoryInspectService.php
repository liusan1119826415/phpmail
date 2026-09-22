<?php
namespace app\backend\modules\project\services;
use app\backend\modules\charts\models\Supplier;
use app\common\exceptions\ShopException;
use app\common\models\Address;
use app\common\models\project\Project;
use app\common\models\project\ProjectProgress;
use app\common\models\project\ProjectFactoryInspection;
use app\common\models\project\PurchasingModes;
use app\common\services\Session;
use Carbon\Carbon;
use Yunshop\Supplier\common\models\SupplierCredential;
class FactoryInspectService
{

    const projects_visit = [
        [
            "id" => 1,
            "name" => "展厅"
        ],
        [
            "id" => 2,
            "name" => "车间"
        ],
        [
            "id" => 3,
            "name" => "办公区"
        ],
        [
            "id" => 4,
            "name" => "其它"
        ]
    ];

    const inspection_mode = [
        [
            "id" => 1,
            "name" => "带客考察"
        ],
        [
            "id" => 2,
            "name" => "自行参观"
        ]
    ];

    const travel_mode = [
        [
            "id" => 1,
            "name" => "自驾前往"
        ],
        [
            "id" => 2,
            "name" => "需派车接送"
        ]
    ];

    const payment_method = [
        [
            "id" => 1,
            "name" => "申请人支付"
        ],
        [
            "id" => 2,
            "name" => "客人自付"
        ],
        [
            "id" => 3,
            "name" => "工厂代付"
        ]
    ];
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
        $uniqueProvincesAndCities = Project::select($key)
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

       $query = ProjectFactoryInspection::query()->with(['project'=>function($query){
           $query->select("id","name","factory_status")->with(['report']);
       },'member'=>function($query){
           $query->select("uid","nickname","mobile");
       }]);

       $search = request()->input('search');
       if($search['purchasing_models']){
           $query->where('purchase_mode',$search['purchasing_models']);
       }
       if($search['company_type']){
           $query->whereRaw('FIND_IN_SET(?,company_type)', $search['company_type']);
       }

       if($search['type'] !="all" && $search['type']){
           $query->whereHas('project',function ($query) use($search){
                $query->where('factory_status',$search['type'])->where('factory_status','!=',1);
           });
       }
       if($search['type'] =="all"){
           $query->whereHas('project',function ($query) use($search){
               $query->where('factory_status','!=',1);
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
                    $query->where('factory_status',3);
                });
            }elseif($search['time_type'] == 3){
                $query->whereBetween('approved_time',$arr)->whereHas('project',function ($query) use($search){
                    $query->where('factory_status',2);
                });
            }elseif($search['time_type'] == 4){
                $query->whereBetween('approved_time',$arr)->whereHas('project',function ($query) use($search){
                    $query->where('factory_status',4);
                });
            }
        }

       $list = $query->orderBy('created_at','desc')->paginate(20);

        // 获取所有的 province_id, city_id, district_id，避免多次查询
        $provinceIds = $list->pluck('project.report.province_id')->unique()->toArray();
        $cityIds = $list->pluck('project.report.city_id')->unique()->toArray();
        $districtIds = $list->pluck('project.report.district_id')->unique()->toArray();

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
            $province = $addresses->get($item->project->report->province_id)->areaname ?? '';
            $city = $addresses->get($item->project->report->city_id)->areaname ?? '';
            $district = $addresses->get($item->project->report->district_id)->areaname ?? '';
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
            if($item->project->report_status == 1){
                $name = "未确认报备";
            }elseif($item->project->report_status == 2){
                $name = "已确认报备";
            }elseif($item->project->report_status == 3){
                $name = "未通过报备";
            }elseif($item->project->report_status == 4){
                $name = "已关闭报备";
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

    public function getDetail($id)
    {
        $data = ProjectFactoryInspection::where('id',$id)->with(['member'=>function($query){
            $query->select("uid","mobile","nickname","avatar");
        },'project'=>function($query){
            $query->select("id","factory_status","report_status");
        }])->first();
        $data->company_type_name = implode(",",ProjectProgress::whereIn('id',explode(',',$data->company_type))->pluck("name")->toArray());
        $data->projects_visit = implode(",",collect(self::projects_visit)->whereIn('id',explode(",",$data->projects_visit))->values());

        if ($data->need_hotel_booking == 1) {
            $method = collect(self::payment_method)->firstWhere('id', $data->payment_method);
            $payment_method_name = $method['name'];
            $date3 = Carbon::parse($data->check_in_start_date);
            $date4 = Carbon::parse($data->check_in_start_date);
            $wan = $date3->diffInDays($date4);
            $data->hot_info = $payment_method_name . "，" . $date3->format('Y年m月d日') . "至" . $date4->format('Y年m月d日') . "，" . "共" . $wan . "晚";
            $data->room_info = "标间" . $data->room_count_standard . "间，" . "单间" . $data->room_count_single . "间";

        }

        if ($data->need_restaurant_booking == 1) {
            $meal_start_date = Carbon::parse($data->meal_start_date);
            $meal_type = $data->meal_type == 1 ? "午餐" : "晚餐";
            $data->restaurant = $meal_start_date->format('Y年m月d日') . "，" . $meal_type . "，" . $data->people_number . "人，" . "价位" . $data->price_range . "/人";
        }
        return $data;

    }

    public function apply($id,$status)
    {
        $projectReport = ProjectFactoryInspection::where('id',$id)->first();
        if(!$projectReport){
            throw new ShopException('未找到工厂考察项目');
        }
        $reject_reason = request()->input('reject_reason');
        try {
            $project = Project::where('id',$projectReport->project_id)->first();
            if($project->factory_status  == $status){
                throw new ShopException('当前已经是这个状态');
            }
            if($status == 3){
                //审核通过

                $project->factory_status = 3;
                $project->save();
                $projectReport->approved_time = time();
                $projectReport->save();
            }elseif($status == 4){
                $project->factory_status = 4;
                $project->save();
                $projectReport->reject_reason = $reject_reason;
                $projectReport->reject_time = time();
                $projectReport->save();
            }elseif($status == 5){
               //撤销审核通过
                $project->factory_status = 2;
                $project->save();
                $projectReport->approved_time = 0;
                $projectReport->save();
            }elseif($status == 6){
                $project->factory_status = 2;
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