<?php


namespace app\backend\modules\finance\services;


use app\backend\modules\industry\models\CaseLable;
use app\backend\modules\order\models\Order;
use app\backend\modules\order\models\OrderPay;
use app\backend\modules\order\models\VueOrder;
use app\common\exceptions\ShopException;
use app\common\models\Member;
use app\common\models\OrderAddress;
use app\common\models\project\InstallOrder;
use app\common\models\project\OrderPackageVolume;
use app\common\models\project\OrderStage;
use app\common\modules\payType\remittance\models\flows\RemittanceAuditFlow;
use app\common\modules\payType\remittance\models\process\RemittanceAuditProcess;
use app\common\services\Session;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierInstallPrice;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Yunshop\Supplier\common\models\SupplierOrderReads;
use Carbon\Carbon;
use app\common\models\RemittanceRecord;
use Yunshop\Supplier\common\models\SupplierPayOrder;
use Yunshop\Supplier\common\models\SupplierService;
use Yunshop\Supplier\common\models\SupplierServiceFee;
class DoorOrderService extends CommonService
{

    private function getStatus($status)
    {
        $arr = [
            1 => 4,
            2 => 2,
            3 => 0,
            4 => 3,
            5 => -1
        ];
        return $arr[$status];
    }


    public function getList($search)
    {

        $query = VueOrder::with(['project'=>function($query){
            $query->select("id","name");
        }, 'projectDoor' => function ($query) {
            $query->select('id', 'service_id', 'service_type', 'project_area', 'province_id', 'city_id', 'district_id');
        }])->where('order_type', 5);



        if ($search['order_id']) {

            $query->where('id', $search['order_id']);
        }

        if ($search['order_sn']) {

            $query->where('order_sn', $search['order_sn']);
        }

        if ($search['project_name']) {

            $query->whereHas('project', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search['project_name'] . '%');
            });
        }


        if ($search['pay_type_id']) {

            $query->where('pay_type_id', $search['pay_type_id']);

        }

        if($search['status']){
            $query->where('status', $this->getStatus($search['status']));
        }


        $list = $query->orderBy('create_time', 'desc')->paginate(15);

        $service_ids = $list->pluck('projectDoor.service_id')->unique()->filter()->toArray();

        $supplier_service = SupplierService::withTrashed()->whereIn('id', $service_ids)->with(['service'])->get();

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
        $list->transform(function ($order) use($supplier_service_map,$service_fee_map) {
            $order->paid_amount = in_array($order->status,[1,2,3])?$order->price:0;
            $order->project_name = $order->project->name;
            $order->service_name = optional($supplier_service_map[$order->projectDoor->service_id]->service ?? null)->name ?? '';
            $service_type_ids = explode(',', $order->projectDoor->service_type);
            $order->service_fee_list = collect($service_type_ids)
                ->map(function ($id) use ($service_fee_map) {
                    return optional($service_fee_map[$id]->service ?? null)->name ?? '';
                })
                ->filter()
                ->values()
                ->toArray();
            $order->order_price = $order->goods_price;
            if($order->extra_enable == 1){
                $order->order_price +=  $order->extra_amount;
            }
            return $order;
        });

        return $list;


    }





    public function detail($id)
    {
        $order = VueOrder::with(['belongsToMember' => function ($query) {
            $query->select("uid", "avatar", "nickname", "mobile");
        },'supplier','projectDoor'])->find($id);

        $total_price = $order->goods_price;
        if($order->extra_enable == 1){
            $total_price+=$order->extra_amount;
        }
        $supplier_service = SupplierService::withTrashed()->where('id',$order->projectDoor->service_id)->with(['service'])->first();

        $supplier_service_fee = SupplierServiceFee::withTrashed()->whereIn('id', explode(",",$order->projectDoor->service_type))->with(['service'])->get();
        $service_fee_list = $supplier_service_fee
            ->pluck('service.name')  // 提取 service 的 name
            ->filter()               // 过滤掉 null 值（如果有些没有关联）
            ->values()               // 重建索引
            ->toArray();             // 转为普通数组
        $orderPay = OrderPay::with(['supplier'])->where('order_id', $order->id)->where('supplier_id',$order->supp_id)
            ->where('income_type',self::INCOME_TYPE_DOOR)->where('pay_form',self::PLAT_TO_BRAND)->where('income',self::PAY_COME)->first();
            $supplier_d = [
                'order_id' => $order->id,
                'public_info' => [
                    'company_name' => $order->supplier->company_name ? $order->supplier->company_name : $order->supplier->store_name,
                    'bank_account' => $order->supplier->bank_account,
                    'bank_of_accounts' => $order->supplier->bank_of_accounts,
                    'opening_branch' => $order->supplier->opening_branch,
                ],
                'order_sn' => $order->order_sn,
                'project_name'=>$order->project->name,
                'service_name'=>$supplier_service->service->name,
                'service_fee_list'=>$service_fee_list,
                'price' => $total_price,
                'paid_amount' => $orderPay->status ==1?$orderPay->amount:0,
                'status'=>$orderPay?$orderPay->status:0,

            ];

        $data = [
            'member' => [
                'avatar' => $order->belongsToMember->avatar,
                'nickname' => $order->belongsToMember->nickname,
                'mobile' => $order->belongsToMember->mobile,
            ],
            'order_sn' => $order->order_sn,
            'order_id' => $order->id,
            'order_total_price' => $total_price,

            'fee_detail' => [
                'service_amount' => $order->goods_price,
                'extra_amount' => $order->extra_enable == 1?$order->extra_amount:0,
                'service_exemption' => $order->discount_price,
                'receivable_amount' => in_array($order->status,[1,2,3])?$order->price:0,

            ],
            'status_name' => $order->status_name,
            'new_status' => $order->new_status,
            'order_time' => [
                'create_time' => $order->create_time->toDateTimeString(),
                'confirm_time' => $order->confirm_time->toDateTimeString(),
                'product_time' => $order->product_time->toDateTimeString(),
                'pay_time' => $order->pay_time->toDateTimeString(),
                'finish_time' => $order->finish_time->toDateTimeString(),

            ],
            'note' => $order->note,
            'project_name' => $order->project->name,

            'supplier_data' => $supplier_d,



        ];
        return $data;
    }


    public function confirmPay($order_id, $swift_img)
    {
        try {


            //查询订单信息，并通知厂家收款
            $order = Order::find($order_id);
            $data['order'] = $order;
            $data['thumb'] = $swift_img;
            $data['income'] = self::PAY_COME;
            $data['supplier_id'] = $data['order']->supp_id;
            $data['income_type'] = self::INCOME_TYPE_DOOR;
            $data['pay_form'] = self::PLAT_TO_BRAND;
            $data['pay_price'] = $order->price;
            $data['payment_stage'] = 1;
            $this->submitSupplierOrderPay($data);
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }


    }

    public function revokePay($order_id)
    {
        try {
            $data['order'] = Order::find($order_id);
            $data['supplier_id'] = $data['order']->supp_id;
            $data['income_type'] = self::INCOME_TYPE_DOOR;
            $data['pay_form'] = self::PLAT_TO_BRAND;
            $data['payment_stage'] = 1;
            $data['income'] = self::PAY_COME;
            $this->revokeSupplierOrderPay($data);
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }

    }


    public function getPayVoucher($order_id)
    {
        //$supplierPayOrder = SupplierPayOrder::with(['supplier'])->where('order_id', $order_id)->first();
        $order = Order::find($order_id);
        $orderPay = OrderPay::with(['supplier'])->where('order_id', $order_id)->where('supplier_id',$order->supp_id)
                    ->where('income_type',self::INCOME_TYPE_DOOR)->where('pay_form',self::PLAT_TO_BRAND)->where('income',self::PAY_COME)->first();
        $status_arr = [
            "待审核",
            "审核通过",
            "驳回"
        ];
        $data = [
            'order_type' => $order->order_type,

            'swift_img' => yz_tomedia($orderPay->thumb),
            'store_name' => $orderPay->supplier->store_name,
            'amount' => $orderPay->amount,
            'order_sn' => $order->order_sn,
            'status'=>$orderPay->status,
            'status_name' => $status_arr[$orderPay->status],
            'note'=>$orderPay->note
        ];
        return $data;
    }






}