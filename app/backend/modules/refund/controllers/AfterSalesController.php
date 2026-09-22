<?php
/**
 * Created by PhpStorm.
 *
 *
 *
 * Date: 2022/10/19
 * Time: 17:11
 */

namespace app\backend\modules\refund\controllers;


use app\backend\modules\finance\services\CommonService;
use app\backend\modules\order\models\Order;
use app\backend\modules\order\models\OrderPay;
use app\backend\modules\refund\models\OrderRefund;
use app\backend\modules\refund\services\AfterSalesExport;
use app\backend\modules\refund\services\RefundOperationService;
use app\common\components\BaseController;
use app\common\exceptions\AppException;
use app\common\exceptions\ShopException;
use app\common\models\Address;
use Yunshop\Supplier\common\models\SupplierService;
use Yunshop\Supplier\common\models\SupplierServiceFee;
class AfterSalesController extends BaseController
{


    /**
     * @return OrderRefund
     */
    protected function refundModel()
    {
        return new OrderRefund();
    }

    /**
     * @return OrderRefund
     */
    protected function orderRefund()
    {
        return $this->refundModel()->uniacid();
    }


    public function index()
    {
        //测试
        //$refundApply = \app\backend\modules\refund\models\RefundApply::find(30);
        //$payAdapter = new \app\common\modules\refund\RefundPayAdapter($refundApply->order->pay_type_id);
        //$payAdapter->setAttribute('pay_name', $refundApply->order->pay_type_name);
        //$result =  $payAdapter->pay($refundApply->order->hasOneOrderPay->pay_sn, $refundApply->order->hasOneOrderPay->amount, $refundApply->price, $refundApply->refund_sn);
        //dd($result);


        return view('refund.after-sales.list', [
            'data' => []
        ])->render();
    }

    /**
     * @return array
     */
    protected function viewData():array
    {
        return [];
    }

    public function getList()
    {

        $search = request()->input('search');

        request()->merge(['is_platform' => 1]);
        $orderRefundModel = $this->orderRefund()->has('order')->backendSearch($search);

        $count['total_price'] = $orderRefundModel->sum('yz_order_refund.price');

        $page = $orderRefundModel->orderBy('yz_order_refund.id', 'desc')->paginate(15);

        $count['total'] = $page->total();

        $data['count'] = $count;


        $page->map(function ($refund) {
            $refund->order->setAppends(['status_name','pay_type_name','fixed_button']);
        });

        //获取所有地址id
        if($search['is_bid'] == 3){
            $service_ids = $page->pluck('service_id')->unique()->filter()->toArray();

            $supplier_service_map = SupplierService::withTrashed()->whereIn('id',$service_ids)->with(['service'])->get()->keyBy('id');

            $service_type_ids = $page->pluck('service_type')
                ->flatMap(function ($type) {
                    return explode(',', $type); // 拆分字符串
                })
                ->unique()
                ->filter()
                ->values()
                ->toArray();

            $service_fee_map = SupplierServiceFee::withTrashed()->whereIn('id', $service_type_ids)->with(['service'])->get()->keyBy('id');
        }


        $provinceIds = $page->pluck('order.project.province_id')->filter()->unique()->toArray();
        $cityIds = $page->pluck('order.project.city_id')->filter()->unique()->toArray();
        $districtIds = $page->pluck('order.project.district_id')->filter()->unique()->toArray();
        $consignee_province_ids = $page->pluck('consignee_province_id')->filter()->unique()->toArray();
        $consignee_city_ids = $page->pluck('consignee_city_id')->filter()->unique()->toArray();
        $consignee_district_ids = $page->pluck('consignee_district_id')->filter()->unique()->toArray();
        $allIds = array_merge($provinceIds, $cityIds, $districtIds,$consignee_province_ids,$consignee_city_ids,$consignee_district_ids);

        $addressMap = Address::whereIn('id', $allIds)->pluck('areaname', 'id');

        $page->transform(function ($order) use($addressMap,$supplier_service_map,$service_fee_map,$search) {
            if($search['is_bid'] == 3){
                $order->service_name = optional($supplier_service_map[$order->service_id]->service ?? null)->name ?? '';

                $service_type_ids = explode(',', $order->service_type);

                $order->service_fee_list = collect($service_type_ids)
                    ->map(function ($id) use ($service_fee_map) {
                        return optional($service_fee_map[$id]->service ?? null)->name ?? '';
                    })
                    ->filter()
                    ->values()
                    ->toArray();
            }
            if($search['is_bid'] == 1){

                if (!$order->order->pay_time->diffInDays(now()) > 3) {
                   $order->progress_status = -2; //72小时内申请退款
                }
            }

            $order->store_name = $order->order->supplier->store_name;
            $order->refund_num = $order->refundOrderGoods->sum('send_num');
            $order->full_address = implode(' ', array_filter([
                $addressMap[$order->order->project->province_id] ?? '',
                $addressMap[$order->order->project->city_id] ?? '',
                $addressMap[$order->order->project->district_id] ?? '',
                $order->order->project->address_detail
            ]));
            $order->consignee_address = implode(' ', array_filter([
                $addressMap[$order->consignee_province_id] ?? '',
                $addressMap[$order->consignee_city_id] ?? '',
                $addressMap[$order->consignee_district_id] ?? '',
                $order->consignee_address_detail
            ]));
            return $order;
        });

        $data['list'] = $page->toArray();


        return $this->successJson('list', $data);
    }

    public function detail()
    {

        $id = intval(request()->input('id'));

        if (empty($id)) {
            throw new AppException('参数为空');
        }

        $refund = $this->refundModel()->with([
            'hasOneMember' => function ($member) {
                $member->select(['uid', 'avatar', 'nickname', 'realname', 'mobile', 'createtime',
                    'credit1', 'credit2',]);
            },
            'order'=>function($query){
                $query->with(['myOrderAddress','hasOneOrderPay','projectBid','projectDoor']);
            },
            'processLog'
        ])->find($id);

        if($refund->order->order_type == 1){
            if (!$refund->order->pay_time->diffInDays(now()) > 3) {
                //72小时内申请退款
                $refund->hours72 = 1;
            }else{
                //72小时外的申请退款
                $refund->hours72 = 2;
                //获取供应商的审核信息
                $order_parent_ids = Order::where('parent_id',$refund->order->id)->pluck("id")->toArray();
                $supplier_refund_list = OrderRefund::whereIn('order_id',$order_parent_ids)->get();
                $passed_count = $supplier_refund_list->where('status', 1)->count(); // 审核通过
                $rejected_count = $supplier_refund_list->where('status', -1)->count(); // 拒绝
                $total_count = $supplier_refund_list->count(); // 总数

                $supplier_refund_count = [
                    'passed' => $passed_count,
                    'rejected' => $rejected_count,
                    'total' => $total_count,
                ];

                $formattedData = $supplier_refund_list->map(function ($refund) {
                    return [
                        'id' => $refund->id,
                        'refund_sn' => $refund->refund_sn,
                        'store_name'=>$refund->order->supplier->store_name,
                        'order_price'=>$refund->order->goods_price,
                        'payment_stage'=>$refund->order->hasOneOrderPay->payment_stage,

                        'status' => $refund->status,
                        'status_name'=>$refund->status_name,
                        'refund_price' => $refund->price,

                    ];
                });

                $refund->supplier_review_data = [
                    'supplier_refund_count'=>$supplier_refund_count,
                    'supplier_refund_data'=>$formattedData
                ];

                $supplier_pay_data = OrderPay::with(['supplier' => function($query) {
                    $query->select("id", "store_name");
                }])
                    ->whereIn('order_id', $order_parent_ids)
                    ->whereIn('pay_form', [2, 3])
                    ->where('income_type', 1)
                    ->get()
                    ->mapToGroups(function ($payment) {
                        $storeName = $payment->supplier->store_name ?? '未知供应商';
                        return [
                            $storeName => [
                                'store_name' => $storeName,
                                'data' => $payment,
                            ]
                        ];
                    })
                    ->map(function ($payments, $storeName) {
                        return [
                            'store_name' => $storeName,
                            'data' => $payments->pluck('data'), // 提取所有支付数据
                        ];
                    })
                    ->values(); // 移除键名（可选）

                $refund->supplier_pay_data = $supplier_pay_data;

            }
        }else{
            $supplier_pay_data = OrderPay::where('order_id',$refund->order->id)->whereIn('pay_form',[2,3])->where('income_type',$refund->order->order_type)->get();

            $refund->supplier_pay_data = $supplier_pay_data;
        }

        if($refund->order->order_type == 2){
            $addressMap = Address::whereIn('id', [$refund->order->projectBid->consignee_province_id,$refund->order->projectBid->consignee_city_id,$refund->order->projectBid->consignee_district_id])->pluck('areaname')->toArray();
            $refund->bid_address = implode(' ', array_filter([
                $addressMap[0] ?? '',
                $addressMap[1] ?? '',
                $addressMap[2] ?? '',
                $refund->order->projectBid->consignee_address_detail
            ]));
        }

        if($refund->order->order_type == 5){
            $supplier_service = SupplierService::withTrashed()->where('id',$refund->order->projectDoor->service_id)->with(['service'])->first();
            $refund->service_name = $supplier_service->service->name;
            $supplier_service_fee = SupplierServiceFee::withTrashed()->whereIn('id', explode(",",$refund->order->projectDoor->service_type))->with(['service'])->get();
            $refund->service_fee_list = $supplier_service_fee
                ->pluck('service.name')  // 提取 service 的 name
                ->filter()               // 过滤掉 null 值（如果有些没有关联）
                ->values()               // 重建索引
                ->toArray();             // 转为普通数组
        }

        if (!$refund) {
            throw new AppException('售后记录不存在');
        }

        $refund->refund_num = $refund->refundOrderGoods->sum('send_num');


        $refund->order->setAppends(['status_name','pay_type_name','fixed_button']);


        //如当前售后不是订单正在进行中的就不显示售后操作
        $refund->backend_button_models = [];
        if ($refund->order->refund_id && $refund->order->refund_id == $refund->id) {
            $refund->backend_button_models = $refund->getBackendButtonModels();
        }



        $refund->refundSteps =  $refund->getBackendRefundSteps();
        //获取订单支付信息

        $data['refund'] = $refund->toArray();

        return view('refund.after-sales.detail', [
            'data' => json_encode($this->detailViewData($data))
        ])->render();
    }

    /**
     * @param $data
     * @return array
     */
    public function detailViewData($data):array
    {

        return $data;
    }



    public function export()
    {
        $search = request()->input('search');

        $orderRefundModel = $this->orderRefund()->has('order')->backendSearch($search);

        $list = $orderRefundModel->orderBy('yz_order_refund.id', 'desc')->get();


//        $list = $list->map(function ($refund) {
//            $refund->order->setAppends(['status_name','pay_type_name','fixed_button']);
//        });


        if ($list->isEmpty()) {
            throw new ShopException('没有可导出的售后记录');
        }

        foreach ($list as $key => $item) {

            $export_data[] = [
                'order_sn' => $item->order->order_sn,
                'refund_sn' => $item->refund_sn,
                'price' => $item->price,
                'uid' =>  $item->uid,
                'nickname' =>  $this->getNickname($item->hasOneMember->nickname),
                'order_type_name' => $item->order_type_name,
                'refund_type_name' => $item->refund_type_name,
                'status_name' =>  $item->status_name,
                'goods' => $item->refundOrderGoods->toArray(),
                'create_time' => $item->create_time->toDateTimeString(),
                'refund_time' => $item->refund_time ? $item->refund_time->toDateTimeString() : '',
                'reason' => $item->reason,
                'part_refund_name' => $item->part_refund_name,
            ];
        }

        $file_name = date('Ymdhis', time()) . '售后列表导出.xls';

        return \app\exports\ExcelService::customExport(new AfterSalesExport($export_data),$file_name);
    }

    protected function getNickname($nickname)
    {
        if (substr($nickname, 0, strlen('=')) === '=') {
            $nickname = '，' . $nickname;
        }


        //去除微信昵称中会带有emoji和特殊符号（颜文字等）否则执行到该值时，后续数据导致空白丢失
        $nickname = preg_replace_callback('/./u',function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        }, $nickname);

        return $nickname;
    }

    protected function mergeExtraParam()
    {
        $extraParam = [
            'package_deliver' => app('plugins')->isEnabled('package-deliver'),
            'team_dividend' => app('plugins')->isEnabled('team-dividend'),
            'printer' => (app('plugins')->isEnabled('printer') || app('plugins')->isEnabled('more-printer'))
        ];

        return $extraParam;
    }

    public function handpass()
    {


        //支付同意退款
        $id = request()->id;
        $status = request()->status;

        $pay_voucher = request()->supplier_pay_voucher;
        $reject_reason = request()->reject_reason;

        $order_refund = OrderRefund::find($id);
        if(!$order_refund) {
            throw new ShopException("退款信息不存在");
        }
        if($order_refund->progress_status == 0){
            throw new ShopException("供应商还未受理");
        }
        if($order_refund->order->pay_type_id != 16){
            throw new ShopException("你的退款是微信或支付宝，请按自动退款按钮！");
        }
        if($status == 1) {
            if(!$pay_voucher) {
                throw new ShopException("请上传付款凭证");
            }

            $order_refund->status = $status;
            $order_refund->progress_status = 2;
            $order_refund->supplier_pay_voucher = $pay_voucher;
            $order_refund->pass_time = time();
            $pay_id = $this->transPlat($order_refund->order,$pay_voucher,$order_refund->price);
            $order_refund->pay_id = $pay_id;
            $order_refund->refund_time = time();
            RefundOperationService::refundConsensus(['refund_id' => $id]);
        } elseif($status == -1) {
            $order_refund->status = $status;
            $order_refund->reject_reason = $reject_reason;
            $order_refund->reject_time = time();
            $order_refund->progress_status = 2;
        }
        $order_refund->save();
        $order_refund->order->refund_status = 2;
        $order_refund->order->save();
        if($order_refund->order->order_type == 1){
            // 获取主订单ID和所有子订单ID

            // 只有当状态是通过(1)时才检查是否需要更新主订单状态
            if($status == 1) {
                $order_parent_ids = Order::where('parent_id', $order_refund->order->id)->pluck('id')->toArray();

                // 修改所有子订单状态为已完成售后
                OrderRefund::whereIn('order_id', $order_parent_ids)->update(
                  [
                      'progress_status'=>2,
                      'pass_time'=>time()
                  ]
                );

            }
        }
        return $this->successJson('ok');
    }


    private function transPlat($order,$swift_img,$refund_price)
    {

        $data['order'] = $order;
        $data['thumb'] = $swift_img;
        $data['income'] = CommonService::PAY_COME;
        $data['supplier_id'] = 0;

        $data['income_type'] = $order->order_type;
        $data['pay_form'] = CommonService::PLAT_TO_MEMBER;
        $data['pay_price'] = $refund_price;
        $data['payment_stage'] = $order->hasOneOrderPay->payment_stage;
        $commonService = new CommonService();
        //查询订单信息，并通知厂家收款
        $pay_id = $commonService->submitSupplierOrderPay($data);
        return $pay_id;


    }


    public function supplier_detail()
    {
        $id = intval(request()->input('id'));

        if (empty($id)) {
            throw new AppException('参数为空');
        }

        $refund = $this->refundModel()->with([
            'hasOneMember' => function ($member) {
                $member->select(['uid', 'avatar', 'nickname', 'realname', 'mobile', 'createtime',
                    'credit1', 'credit2',]);
            },
            'order'=>function($query){
                $query->with(['address','hasOneOrderPay','projectBid','projectDoor']);
            },
            'processLog'
        ])->find($id);

        if($refund->order->order_type == 2){
            $addressMap = Address::whereIn('id', [$refund->order->projectBid->consignee_province_id,$refund->order->projectBid->consignee_city_id,$refund->order->projectBid->consignee_district_id])->pluck('areaname')->toArray();
            $refund->bid_address = implode(' ', array_filter([
                $addressMap[0] ?? '',
                $addressMap[1] ?? '',
                $addressMap[2] ?? '',
                $refund->order->projectBid->consignee_address_detail
            ]));
        }

        if($refund->order->order_type == 5){
            $supplier_service = SupplierService::withTrashed()->where('id',$refund->order->projectDoor->service_id)->with(['service'])->first();
            $refund->service_name = $supplier_service->service->name;
            $supplier_service_fee = SupplierServiceFee::withTrashed()->whereIn('id', explode(",",$refund->order->projectDoor->service_type))->with(['service'])->get();
            $refund->service_fee_list = $supplier_service_fee
                ->pluck('service.name')  // 提取 service 的 name
                ->filter()               // 过滤掉 null 值（如果有些没有关联）
                ->values()               // 重建索引
                ->toArray();             // 转为普通数组
        }

        if (!$refund) {
            throw new AppException('售后记录不存在');
        }


        $refund->refund_num = $refund->refundOrderGoods->sum('send_num');

        $refund->order->setAppends(['status_name','pay_type_name','fixed_button']);


        //如当前售后不是订单正在进行中的就不显示售后操作
        $refund->backend_button_models = [];
        if ($refund->order->refund_id && $refund->order->refund_id == $refund->id) {
            $refund->backend_button_models = [];
        }



        $refund->refundSteps =  $refund->getBackendRefundSteps();
        //获取订单支付信息
        // $refund->pay_data = OrderPay::where('order_id',$refund->order->id)->whereIn('pay_form',[2,3])->where('status',1)->get();
        $data['refund'] = $refund->toArray();

        return view('refund.after-sales.supplier_detail', ['data' => json_encode($this->detailViewData($data))])->render();
    }


}
