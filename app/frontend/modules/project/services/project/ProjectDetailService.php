<?php

namespace app\frontend\modules\project\services\project;

use app\common\exceptions\AppException;
use app\common\models\Address;
use app\common\models\kefu\ServiceUser;
use app\common\models\project\ProjectBid;
use app\common\models\project\ProjectFactoryInspection;
use app\common\models\project\ProjectProgress;
use app\common\models\project\ProjectReport;
use app\common\models\project\PurchasingModes;
use app\frontend\models\Order;
use app\frontend\models\OrderGoods;
use app\frontend\modules\project\infrastructure\BaseRepository;
use app\frontend\modules\project\models\Floors;
use app\frontend\modules\project\models\PptTemplate;
use app\frontend\modules\project\models\Project;
use app\common\models\project\ProjectPdf;
use Carbon\Carbon;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierBidFiles;
use Yunshop\Supplier\common\models\SupplierCharge;
use Yunshop\Supplier\common\models\SupplierColorPlane;
use Yunshop\Supplier\common\models\SupplierService;
use Yunshop\Supplier\common\models\SupplierServiceFee;

/**
 * 项目详情服务
 * 负责：项目详情、报备信息、投标信息、工厂考察、上门订单
 */
class ProjectDetailService
{
    /**
     * 项目详情
     */
    public function detail(int $id): array
    {
        $projectDetail = Project::select("id", "province_id", "activate", "city_id", "district_id", "name", "price", "report_status", "bid_status", "factory_status", "order_status", "order_id", "status", "address_detail", "contact_name", "phone", "created_at")->with(['floors' => function ($query) {
            $query->select("id", "name", "project_id");
        }, 'order' => function ($query) {
            $query->select("id", "status", "first_pay_time", "orderStatus", "order_type");
        }])->find($id);
        if (!$projectDetail) {
            throw new AppException("未找到项目");
        }

        $projectDetail->created_at_text = self::formatCreatedAt($projectDetail->created_at);

        if ($projectDetail->order->new_status == 3) {
            $max_lead_time = 0;
            $orderMainGoods = OrderGoods::where('order_main_id', $projectDetail->order_id)->get();
            if ($orderMainGoods->isNotEmpty()) {
                $max_lead_time = $orderMainGoods->max(function ($goods) {
                    return isset($goods->goods->lead_time) ? (int)$goods->goods->lead_time : 0;
                });
            }

            if ($projectDetail->order->first_pay_time && $max_lead_time > 0) {
                $expected_finish_date = $projectDetail->order->first_pay_time->copy()->addDays($max_lead_time);
                $now = Carbon::now();
                $remaining_days = $now->diffInDays($expected_finish_date, false);

                $projectDetail->order->expected_finish_date = $expected_finish_date->format('Y-m-d H:i:s');
                $projectDetail->order->remaining_days_text = $remaining_days;
            } else {
                $projectDetail->order->expected_finish_date = "";
                $projectDetail->order->remaining_days_text = "";
            }
        }

        $projectDetail->status_data = $this->getProjectStatusText($projectDetail);

        // 获取省市
        $province = Address::whereIn('id', [$projectDetail->province_id, $projectDetail->city_id, $projectDetail->district_id])->pluck("areaname")->toArray();
        $projectDetail->project_address_detail = $province[0] . $province[1] . $province[2] . $projectDetail->address_detail;

        // 平面图
        $plan_data = Floors::select("id", "name", "is_cad", "goods_total", "thumb")->where('project_id', $projectDetail->id)->with(['spaces' => function ($query) {
            $query->select("id", "floor_id", "name");
        }])->orderBy('sort', 'asc')->get()->toArray();

        foreach ($plan_data as $key => $item) {
            $plan_data[$key]['thumb'] = yz_tomedia($item['thumb']);
        }

        $projectDetail->plan_data = $plan_data;

        // 报备信息
        $projectDetail->reportDetail = $this->getReportDetail($projectDetail);

        // ppt方案
        $pptList = ProjectPdf::select("id", "project_name", "template_id", "total_slides", "ppt_formal_url", "thumb_url", "ppt_url", "created_at")->where('project_id', $projectDetail->id)->where('status', 1)->with(['template' => function ($query) {
            $query->select("id", "thumb");
        }])->get();

        $pptList->transform(function ($item) {
            $url = "";
            if ($item->ppt_formal_url) {
                $url = yz_tomedia($item->ppt_formal_url);
            } elseif ($item->thumb_url) {
                $url = $item->thumb_url;
            } elseif ($item->ppt_url) {
                $url = $item->ppt_url;
            }
            $item->ppt_formal_url = $url;
            $item->template->thumb = yz_tomedia($item->template->thumb);
            $item->created_days_ago = $item->created_at->diffInDays(Carbon::now());
            return $item;
        });
        $pptTemplate = PptTemplate::select("id", "name", "thumb", "url")->where('status', 1)->get();
        $pptTemplate->transform(function ($item) {
            $item->thumb = yz_tomedia($item->thumb);
            return $item;
        });
        $projectDetail->ppt = [
            'pptList' => $pptList->toArray(),
            'pptTemplate' => $pptTemplate->toArray()
        ];

        // 投标信息
        $projectDetail->bid = $this->getBidDetail($projectDetail);

        // 工厂考察信息
        $projectDetail->factory_inspection = $this->getFactory($projectDetail);

        // 助手上门获取项目上门订单
        $projectDetail->door_orders = $this->getDoorOrdersByProjectId($projectDetail->id);

        return $projectDetail->toArray();
    }

    /**
     * 根据项目ID获取所有上门订单（格式化后用于项目详情）
     */
    public function getDoorOrdersByProjectId(int $projectId): array
    {
        $orders = app('OrderManager')->make('Order')->where('project_id', $projectId)->where('order_type', 5)
            ->with([
                'project' => function ($query) {
                    $query->select('id', 'name');
                },
                'projectDoor',
                'supplier'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $result = [];

        foreach ($orders as $order) {
            $door = $order->projectDoor;
            if (!$door) {
                continue;
            }

            // 地址拼接
            $address = Address::whereIn('id', [$door->province_id, $door->city_id, $door->district_id, $order->supplier->province_id, $order->supplier->city_id, $order->supplier->district_id])
                ->pluck('areaname')
                ->toArray();
            $fullAddress = ($address[0] ?? '') . ($address[1] ?? '') . ($address[2] ?? '') . $door->address_detail;

            // 服务商信息
            $supplierService = SupplierService::withTrashed()
                ->where('id', $door->service_id)
                ->with(['service'])
                ->first();

            // 服务费用明细（多选）
            $serviceFeeIds = explode(',', $door->service_type ?? '');
            $serviceFees = SupplierServiceFee::withTrashed()
                ->whereIn('id', $serviceFeeIds)
                ->with(['service'])
                ->get();

            $serviceFeeList = [];
            foreach ($serviceFees as $fee) {
                $serviceFeeList[] = $fee->service->name ?? '';
            }
            $goodsPriceTotal = $order->goods_price;

            // 品牌订单折扣（同项目下是否存在 order_type=3 的品牌订单）
            $brandOrder = Order::where('project_id', $order->project_id)
                ->where('order_type', 3)
                ->whereIn('status', [1, 2, 3])
                ->first();
            $discountPrice = $brandOrder ? $goodsPriceTotal : 0;
            $extraAmount = ($door->extra_enable == 1) ? ($door->extra_amount ?? 0) : 0;
            $totalPrice = $goodsPriceTotal - $discountPrice + $extraAmount;

            // 组装单个订单返回结构
            $result[] = [
                'order_id'          => $order->id,
                'order_sn'          => $order->order_sn ?? '',
                'status'            => $order->new_status,
                'status_name'       => $order->status_name,
                'submit_time'          => $order->create_time ? $order->create_time->toDateTimeString() : '',
                'confirm_time'      => $order->confirm_time ? date('Y-m-d H:i:s', $order->confirm_time) : '',
                'accept_time'     => $order->pay_time ? date('Y-m-d H:i:s', $order->pay_time) : '',
                'submit_date'          => $order->create_time ? $order->create_time->toDateString() : '',
                'goods_price'       => $goodsPriceTotal,
                'discount_price'    => $discountPrice,
                'extra_amount'      => $extraAmount,
                'total_price'       => $totalPrice,
                'service_type'      => implode("、", $serviceFeeList),
                'refund_id'         => $order->refund_id,
                'is_pending'        => $order->is_pending,

                // 服务品牌（对应 UI 左侧品牌卡片）
                'brand' => [
                    'logo'          => yz_tomedia($order->supplier->logo ?? ''),
                    'name'          => $order->supplier->store_name,
                    'description'   => $order->supplier->introduction ?? '',
                    'contact_name'  => $order->supplier->realname ?? '',
                    'contact_phone' => $order->supplier->mobile ?? '',
                    'service_link'  => ServiceUser::getDistributeService(0),
                    'email'         => '',
                    'company_address' => ($address[3] ?? '') . ($address[4] ?? '') . ($address[5] ?? '') . $order->supplier->address
                ],

                // 订单详情（地址、上门时间、面积等）
                'detail' => [
                    'order_sn' => $order->order_sn ?? '',
                    'service_name'      => $supplierService->service->name,
                    'door_time'     => $door->door_time ?? '',
                    'project_area'  => $door->project_area,
                    'address'       => $fullAddress,
                    'submit_time'   => $order->create_time ? $order->create_time->toDateTimeString() : '',
                    'remark'        => $door->remark ?? '',
                    'project_name'  => $door->project->name,
                    'service_type'      => implode("、", $serviceFeeList),
                    'service_day'      => $door->service_day ?? '',
                    'number'          => $door->number,
                    'contact_name'    => $door->contact_name,
                    'contact_phone'     => $door->contact_phone,
                    'files'             => [
                        'name' => '资料文件',
                        'url'  => $order->information ? yz_tomedia($order->information) : '',
                    ],
                    'total_price' => $totalPrice,
                    "scene_img"=> unserialize($door->scene_img),
                    "build_img"=> unserialize($door->build_img),
                    "technical_contact" => $order->technical_contact,
                    "technical_phone" => $order->technical_phone,
                ],
            ];
        }

        return $result;
    }

    /**
     * 获取项目状态文本
     */
    private function getProjectStatusText($project): array
    {
        if ($project->report_status == 0) {
            return ['last_status' => 1, 'name' => '未申请报备'];
        } elseif ($project->report_status == 1) {
            return ['last_status' => 2, 'name' => '报备申请中'];
        } elseif ($project->report_status == 3) {
            return ['last_status' => 3, 'name' => '报备申请未通过'];
        } elseif ($project->report_status == 4) {
            return ['last_status' => 4, 'name' => '报备结束'];
        } elseif ($project->report_status == 2) {
            switch ($project->bid_status) {
                case 1:
                    return ['last_status' => 5, 'name' => '已报备成功'];
                case 2:
                    return ['last_status' => 6, 'name' => '投标申请中'];
                case 3:
                    return ['last_status' => 7, 'name' => '投标申请未通过'];
                case 4:
                    return ['last_status' => 8, 'name' => '投标中'];
                case 5:
                    return ['last_status' => 9, 'name' => '投标结束'];
            }
        }
        return ['last_status' => 0, 'name' => '未知状态'];
    }

    /**
     * 获取工厂考察信息
     */
    private function getFactory($projectDetail): array
    {
        $factory = ProjectFactoryInspection::where('project_id', $projectDetail->id)->first();
        if (!$factory) {
            return [];
        }
        $date1 = Carbon::parse($factory->scheduled_inspection_date);
        $date2 = Carbon::parse($factory->scheduled_arrival_date);

        // 获取品牌信息
        $supplier = Supplier::select("id", "store_name", "logo", "province_id", "city_id", "introduction", "address", "district_id", "street_id")->where('id', $factory->supplier_id)->first();
        $addresses = Address::whereIn('id', [$supplier->province_id, $supplier->city_id, $supplier->district_id, $supplier->street_id])->pluck('areaname')->toArray();
        $supplier->province_name = $addresses[0] ? mb_substr($addresses[0], 0, -1, "UTF-8") : "";
        $supplier->city_name = $addresses[1] ? mb_substr($addresses[1], 0, -1, "UTF-8") : "";
        $supplier->logo = yz_tomedia($supplier->logo);
        $supplier->mobile = "17777777777";
        $supplier->addressDetail = $addresses[0] . $addresses[1] . $addresses[2] . $addresses[3] . $supplier->address;

        $baseRepo = app(BaseRepository::class);
        $selected_ids = explode(",", $factory->projects_visit);
        $selected_names = array_column(
            array_filter($baseRepo->projects_visit, fn($item) => in_array($item['id'], $selected_ids)),
            'name'
        );
        $company_type_data = ProjectProgress::whereIn('id', explode(",", $factory->company_type))->pluck('name')->toArray();

        $deltail = [
            "id" => $factory->id,
            "project_name" => $factory->name,
            'project_address_detail' => $projectDetail->address_detail,
            'inspection_mode' => $factory->inspection_mode == 1 ? "带客考察" : "自行参观",
            "scheduled_inspection_date" => $date1->format('Y年m月d日'),
            "scheduled_arrival_date" => $date2->format('Y年m月d日 H:i'),
            "applicant_name" => $factory->applicant_name,
            "applicant_phone" => $factory->applicant_phone,
            "company_name" => $factory->company_name,
            "contact_name" => $factory->contact_name,
            'contact_phone' => $factory->contact_phone,
            "company_type" => implode(",", $company_type_data),
            'apply_time' => $factory->created_at->format('Y-m-d H:i:s'),
            'approved_time' => $factory->approved_time,
            "supplier" => $supplier->toArray(),
            "need_top_leader" => $factory->need_top_leader == 1 ? "是" : "否",
            "play_video" => $factory->play_video == 1 ? "是" : "否",
            "is_led" => $factory->is_led == 1 ? "是" : "否",
            "welcome_word" => $factory->welcome_word,
            "projects_visit" => implode(",", $selected_names),
            "travel_mode" => $factory->travel_mode == 1 ? "自驾前往" : "需派车接送",
            "need_hotel_booking" => $factory->need_hotel_booking == 1 ? "是" : "否",
            "need_restaurant_booking" => $factory->need_restaurant_booking == 1 ? "是" : "否",
            "travel_mode_val" => $factory->travel_mode,
            "need_hotel_booking_val" => $factory->need_hotel_booking,
            "need_restaurant_booking_val" => $factory->need_restaurant_booking,
            "need_top_leader_val" => $factory->need_top_leader,
            "play_video_val" => $factory->play_video,
            "is_led_val" => $factory->is_led
        ];

        if ($factory->travel_mode == 1) {
            $deltail['license_plate'] = $factory->license_plate;
        } else {
            $deltail['wait_place'] = $factory->wait_place;
            $deltail['wait_time'] = $factory->wait_time;
        }

        if ($factory->need_hotel_booking == 1) {
            $method = collect($baseRepo->payment_method)->firstWhere('id', $factory->payment_method);
            $payment_method_name = $method['name'];
            $date3 = Carbon::parse($factory->check_in_start_date);
            $date4 = Carbon::parse($factory->check_in_start_date);
            $wan = $date3->diffInDays($date4);
            $deltail['hot_info'] = $payment_method_name . "，" . $date3->format('Y年m月d日') . "至" . $date4->format('Y年m月d日') . "，" . "共" . $wan . "晚";
            $deltail['room_info'] = "标间" . $factory->room_count_standard . "间，" . "单间" . $factory->room_count_single . "间";
        }
        if ($factory->need_restaurant_booking == 1) {
            $meal_start_date = Carbon::parse($factory->meal_start_date);
            $meal_type = $factory->meal_type == 1 ? "午餐" : "晚餐";
            $deltail['restaurant'] = $meal_start_date->format('Y年m月d日') . "，" . $meal_type . "，" . $factory->people_number . "人，" . "价位" . $factory->price_range . "/人";
        }
        return $deltail;
    }

    /**
     * 获取投标详情
     */
    private function getBidDetail($projectDetail): array
    {
        $ProjectBid = ProjectBid::where('project_id', $projectDetail->id)->first();
        if (!$ProjectBid) {
            return [];
        }

        // 项目地址
        $province = Address::whereIn('id', [$ProjectBid->province_id, $ProjectBid->city_id, $ProjectBid->district_id])->pluck("areaname")->toArray();
        $address_detail = $province[0] . $province[1] . $province[2] . $ProjectBid->address_detail;

        $company_type = ProjectProgress::whereIn('id', explode(",", $ProjectBid->company_type))->pluck("name")->toArray();

        // 获取报备品牌信息
        $supplier = Supplier::select("id", "store_name", "logo", "province_id", "city_id", "introduction")->where('id', $ProjectBid->supplier_id)->first();
        $addresses = Address::whereIn('id', [$supplier->province_id, $supplier->city_id])->pluck('areaname')->toArray();
        $supplier->province_name = $addresses[0] ? mb_substr($addresses[0], 0, -1, "UTF-8") : "";
        $supplier->city_name = $addresses[1] ? mb_substr($addresses[1], 0, -1, "UTF-8") : "";
        $supplier->logo = yz_tomedia($supplier->logo);
        $supplier->report_time = $ProjectBid->approved_time ? date("Y/m/d", $ProjectBid->approved_time) : "";
        $SupplierCharge = SupplierCharge::where('supplier_id', $ProjectBid->supplier_id)->first();

        // 相关费用
        $project_fee = 0;
        $pay_status = 0;
        if ($ProjectBid->bid_type == 1) {
            $amount_paid = Order::where('bid_id', $ProjectBid->id)->whereIn('order_type', [2, 3, 4])->whereIn('status', [1, 2, 3])->sum('price') ?: 0;
            $unpaid_amount = Order::where('bid_id', $ProjectBid->id)->whereIn('order_type', [2, 3, 4])->where('status', 0)->sum('price') ?: 0;
            $project_fee = Order::where('bid_id', $ProjectBid->id)->where('order_type', 4)->sum('price') ?: 0;
            if ($ProjectBid->bid_type == 1 && $ProjectBid->need_bid_bond == 1) {
                if ($ProjectBid->bid_order_status == 1 && $ProjectBid->brand_order_status == 1 && $ProjectBid->project_order_status == 1) {
                    $pay_status = 1;
                }
            }
            if ($ProjectBid->bid_type == 1 && $ProjectBid->need_bid_bond == 0) {
                if ($ProjectBid->bid_order_status == 1 && $ProjectBid->brand_order_status == 1) {
                    $pay_status = 1;
                }
            }
        } else {
            $amount_paid = Order::where('bid_id', $ProjectBid->id)->whereIn('order_type', [2, 3])->whereIn('status', [1, 2, 3])->sum('price') ?: 0;
            $unpaid_amount = Order::where('bid_id', $ProjectBid->id)->whereIn('order_type', [2, 3])->where('status', 0)->sum('price') ?: 0;
            if ($ProjectBid->bid_type == 2 && $ProjectBid->need_bid_document == 1) {
                if ($ProjectBid->bid_order_status == 1 && $ProjectBid->brand_order_status == 1) {
                    $pay_status = 1;
                }
            }
            if ($ProjectBid->bid_type == 2 && $ProjectBid->need_bid_document == 0) {
                if ($ProjectBid->brand_order_status == 1) {
                    $pay_status = 1;
                }
            }
        }

        $report_day = 15;
        $start_date = $ProjectBid->created_at->format('Y/m/d');
        $end_date = $ProjectBid->created_at->addDays($report_day)->format('Y/m/d');

        // 获取对应的投标订单id
        $orders = Order::where('bid_id', $ProjectBid->id)
            ->whereIn('order_type', [2, 3, 4])
            ->where('status', '!=', -1)
            ->get()
            ->keyBy('order_type');

        $bid_doc_order_id = $orders[2]->id ?? null;
        $brand_order_id   = $orders[3]->id ?? null;
        $deposit_order_id = $orders[4]->id ?? null;

        $relatedCosts = [
            'amount_paid' => $amount_paid,
            'bid_fee' => $SupplierCharge->bid_document_fee ?: 0,
            'bid_fee_status' => $ProjectBid->bid_order_status,
            'brand_fee' => $SupplierCharge->brand_fee ?: 0,
            'brand_fee_status' => $ProjectBid->brand_order_status,
            'project_fee' => $project_fee,
            'project_fee_status' => $ProjectBid->project_order_status,
            'unpaid_amount' => $unpaid_amount,
            'bid_doc_order_id' => $bid_doc_order_id,
            'brand_order_id' => $brand_order_id,
            'deposit_order_id' => $deposit_order_id
        ];

        // 获取厂家默认投标版本
        $SupplierBidFiles = SupplierBidFiles::where('supplier_id', $ProjectBid->supplier_id)->first();

        $addresse_map = Address::whereIn('id', [$ProjectBid->consignee_province_id, $ProjectBid->consignee_city_id, $ProjectBid->consignee_district_id])->pluck("areaname")->toArray();

        // 标书收货地址
        $consignee_info = [
            'consignee' => $ProjectBid->consignee,
            'consignee_mobile' => $ProjectBid->consignee_mobile,
            'consignee_address_detail' => implode(" ", [
                $addresse_map[0],
                $addresse_map[1],
                $addresse_map[2],
                $ProjectBid->consignee_address_detail
            ])
        ];

        $balance_payment_info = [
            'account_name' => $ProjectBid->account_name,
            'bank_branch' => $ProjectBid->bank_branch,
            'bank_name' => $ProjectBid->bank_name,
            'contact_bank_mobile' => $ProjectBid->contact_bank_mobile
        ];

        $detail = [
            "id" => $ProjectBid->id,
            'project_name' => $ProjectBid->name,
            'project_address_detail' => $address_detail,
            'project_area' => $ProjectBid->project_area,
            'company_name' => $ProjectBid->company_name,
            'company_address' => $ProjectBid->company_address,
            'contact_name' => $ProjectBid->contact_name . "/" . $ProjectBid->contact_phone,
            'company_type_name' => implode(",", $company_type),
            'apply_time' => $ProjectBid->created_at->format('Y-m-d H:i:s'),
            'approved_time' => $ProjectBid->approved_time ? date("Y-m-d H:i:s", $ProjectBid->approved_time) : "",
            'bid_type_name' => $ProjectBid->bid_type == 1 ? "工厂直投" : "授权投标",
            'bid_type' => $ProjectBid->bid_type,
            'related_costs' => $relatedCosts,
            "supplier" => $supplier,
            'pay_status' => $pay_status,
            "report_period_start" => $start_date,
            "report_period_end" => $end_date,
            'need_bid_bond' => $ProjectBid->need_bid_bond,
            'need_bid_document' => $ProjectBid->need_bid_document,
            "bid_files" => $ProjectBid->bid_files ? unserialize($ProjectBid->bid_files) : [],
            'default_bid_files' => $SupplierBidFiles ? unserialize($SupplierBidFiles->file_path) : [],
            'reject_reason' => $ProjectBid->reject_reason,
            'consignee_info' => $consignee_info,
            'balance_payment_info' => $balance_payment_info
        ];

        if ($ProjectBid->bid_type == 1) {
            $detail['authorized_person_name'] = $ProjectBid->authorized_person_name;
            $detail['authorized_person_phone'] = $ProjectBid->authorized_person_phone;
            $detail['authorized_person_idcard'] = yz_tomedia($ProjectBid->authorized_person_idcard);
        }

        if ($ProjectBid->need_bid_bond == 1) {
            $detail['account_name'] = $ProjectBid->account_name;
            $detail['bank_branch'] = $ProjectBid->bank_branch;
            $detail['bank_name'] = $ProjectBid->bank_name;
            $detail['contact_phone'] = $ProjectBid->contact_phone;
        }

        return $detail;
    }

    /**
     * 获取报备详情
     */
    private function getReportDetail($projectDetail): array
    {
        $ProjectReport = ProjectReport::where('project_id', $projectDetail->id)->first();
        if (!$ProjectReport) {
            return [];
        }

        // 项目地址
        $province = Address::whereIn('id', [$ProjectReport->province_id, $ProjectReport->city_id, $ProjectReport->district_id])->pluck("areaname")->toArray();
        $address_detail = $province[0] . $province[1] . $province[2] . $ProjectReport->address_detail;

        // 采购模式
        $purchasing = PurchasingModes::whereIn('id', explode(",", $ProjectReport->purchase_mode))->pluck("name")->toArray();
        if (count($purchasing) >= 2) {
            $purchase_mode = $purchasing[0] . " - " . $purchasing[1];
        } else {
            $purchase_mode = $purchasing[0];
        }

        // 项目进度
        $project_progress = ProjectProgress::whereIn('id', explode(",", $ProjectReport->project_progress))->pluck("name")->toArray();
        $company_type = ProjectProgress::whereIn('id', explode(",", $ProjectReport->company_type))->pluck("name")->toArray();

        // 获取报备品牌信息
        $supplier = Supplier::select("id", "store_name", "logo", "province_id", "city_id", "introduction", "is_bid")->where('id', $ProjectReport->supplier_id)->first();
        $addresses = Address::whereIn('id', [$supplier->province_id, $supplier->city_id])->pluck('areaname')->toArray();
        $supplier->province_name = $addresses[0] ? mb_substr($addresses[0], 0, -1, "UTF-8") : "";
        $supplier->city_name = $addresses[1] ? mb_substr($addresses[1], 0, -1, "UTF-8") : "";
        $supplier->logo = yz_tomedia($supplier->logo);
        $supplier->report_time = $ProjectReport->approved_time ? date("Y/m/d", $ProjectReport->approved_time) : "";

        $report_day = 60;
        $start_date = "";
        $end_date = "";
        if ($ProjectReport->approved_time) {
            if (!($ProjectReport->approved_time instanceof Carbon)) {
                $approved_time = Carbon::parse($ProjectReport->approved_time);
            } else {
                $approved_time = $ProjectReport->approved_time->copy();
            }
            $start_date = $approved_time->format('Y/m/d');
            $end_date = $approved_time->copy()->addDays($report_day)->format('Y/m/d');
        }
        $service_link = ServiceUser::getDistributeService($supplier->id);

        // 项目文件（序列化存储，需反序列化为数组）
        $project_file = [];
        if ($ProjectReport->project_file) {
            $files = unserialize($ProjectReport->project_file);
            $project_file = is_array($files) ? $files : [];
        }

        return [
            "id" => $ProjectReport->id,
            "budget" => $ProjectReport->budget,
            'project_name' => $ProjectReport->name,
            'project_address_detail' => $address_detail,
            'project_area' => $ProjectReport->project_area,
            'purchase_mode' => $purchase_mode,
            'need_bid_document' => $ProjectReport->need_bid_document == 1 ? "需要" : "不需要",
            'need_factory_inspection' => $ProjectReport->need_factory_inspection == 1 ? "需要" : "不需要",
            'project_progress' => implode(",", $project_progress),
            'company_name' => $ProjectReport->company_name,
            'company_address' => $ProjectReport->company_address,
            'contact_name' => $ProjectReport->contact_name . "/" . $ProjectReport->contact_phone,
            'company_type' => implode(",", $company_type),
            'apply_time' => $ProjectReport->created_at->format('Y-m-d H:i:s'),
            "approved_date" => $ProjectReport->approved_time ? $ProjectReport->approved_time->format('Y/m/d') : $ProjectReport->created_at->format('Y/m/d'),
            'approved_time' => $ProjectReport->approved_time ? $ProjectReport->approved_time->format('Y-m-d H:i:s') : "",
            'supplier' => $supplier->toArray(),
            "report_period_start" => $start_date,
            "report_period_end" => $end_date,
            "reject_reason" => $ProjectReport->reject_reason,
            "is_bid" => $supplier->is_bid,
            'service_link' => $service_link,
            'project_file' => $project_file,
        ];
    }

    /**
     * 格式化创建时间
     */
    private static function formatCreatedAt($createdAt): string
    {
        if (!$createdAt) {
            return '';
        }

        $now = now();
        $diffInHours = $createdAt->diffInHours($now);
        $diffInDays = $createdAt->diffInDays($now);

        if ($diffInHours < 1) {
            return '1小时前';
        } elseif ($diffInDays < 3) {
            return $diffInHours . '小时前';
        } else {
            return $createdAt->format('Y/m/d');
        }
    }
}
