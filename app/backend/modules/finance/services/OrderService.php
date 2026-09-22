<?php


namespace app\backend\modules\finance\services;


use app\backend\modules\industry\models\CaseLable;
use app\backend\modules\order\models\Order;
use app\backend\modules\order\models\OrderPay;
use app\backend\modules\order\models\VueOrder;
use app\common\exceptions\ShopException;
use app\common\models\Member;
use app\common\models\OrderAddress;
use app\common\models\OrderGoods;
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

class OrderService extends CommonService
{

    private function getStatus($status)
    {
        switch ($status) {
            case 1:
                return ['status' => 4];
            case 2:
                return ['status' => 0, 'orderStatus' => 0];
            case 3:
                return ['status' => 1, 'orderStatus' => 1];
            case 4:
                return ['status' => 1, 'orderStatus' => 2];
            case 5:
                return ['status' => 1, 'orderStatus' => 3];
            case 6:
                return ['status' => 2];
            case 7:
                return ['status' => 3];
            default:
                return []; // 返回空数组表示不加条件
        }
    }


    public function getList($search)
    {

        $query = VueOrder::where('parent_id', 0)->with(['project'=>function($query){
            $query->withTrashed()->select("id","name");
        }])->where('order_type', 1);
        if (!empty($search['status'])) {
            $conditions = $this->getStatus($search['status']);
            if (!empty($conditions)) {
                $query->where($conditions); // 数组形式添加多个 where 条件
            }
        }


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

        if ($search['payment_stage']) {
            $query->where('payment_stage', $search['payment_stage']);

        }

        $list = $query->orderBy('create_time', 'desc')->paginate(15);
        $list->transform(function ($order) {

            $order->order_total_amount = $order->goods_price + $order->freight_price + $order->install_price;
            $order->project_name = $order->project->name;
            //物流费用
            if($order->status>=1 && $order->orderStatus == 3){
                $paid_freight_price = $order->freight_price;
            }else{
                $paid_freight_price = 0;
            }

            //安装费用
            if($order->status>=1 && $order->orderStatus == 3){
                $paid_install_price = $order->install_price;
            }else{
                $paid_install_price = 0;
            }

            $order->received_payment_amount = OrderStage::where('order_id', $order->id)->where('status', 1)->sum('price')+$paid_install_price+$paid_freight_price;

            $order->payment_stage_name = $order->payment_stage == 2 ? "尾款" : "预付款";
            return $order;
        });

        return $list;


    }





    public function getCredentials($id)
    {
        $orderPay = OrderPay::with(['payOrder'])->where('id', $id)->first();

        $order = \app\common\models\Order::where('order_pay_id', $id)->first();
        if (in_array($orderPay->pay_type_id, [1, 2])) {
            $orderPay = $orderPay->toArray();
            $data = [
                "pay_sn" => $orderPay['pay_sn'],
                "amount" => $orderPay['amount'],
                "pay_type_name" => $orderPay['pay_type_name'],
                "status_name" => $orderPay['status_name'],
                "pay_time" => $orderPay['pay_time'],
                'trade_no' => $orderPay['pay_order'][0]['trade_no'],

            ];
        } else if ($orderPay->pay_type_id == 16) {
            $remittanceAuditFlow = RemittanceAuditFlow::first();
            $res = RemittanceRecord::where('order_pay_id', $orderPay->id)->first();
            $processBuilder = RemittanceAuditProcess::where('flow_id', $remittanceAuditFlow->id)->uniacid()->with(['status', 'remittanceRecord' => function ($query) {
                $query->with(['orderPay']);
            }])->where('model_id', $res->id)->first();

            $data = [
                'id' => $processBuilder->id,
                'swift_img' => yz_tomedia($processBuilder->remittanceRecord->report_url),
                'pay_sn' => $processBuilder->remittanceRecord->orderPay->pay_sn,
                'order_sn' => $order->order_sn,
                'status_name' => $processBuilder->status_name
            ];
        }

        return $data;
    }


    public function detail($id)
    {
        $order = VueOrder::with(['belongsToMember' => function ($query) {
            $query->select("uid", "avatar", "nickname", "mobile");
        }])->find($id);


        $orderStageOne = OrderStage::where('order_id', $order->id)->where('stage', 1)->first();
        $orderStageTwo = OrderStage::where('order_id', $order->id)->where('stage', 2)->first();
        $orderAddress = OrderAddress::where('order_main_id', $order->id)->first();
        //
        $payList = OrderPay::whereIn('id', [$orderStageOne->pay_id, $orderStageTwo->pay_id])->get();
        //家具供应商信息
       // $parentOrder = Order::where('parent_id', $order->id)->with(['supplier'])->get();
        $orderIds = Order::where('parent_id', $order->id)->pluck('id')->toArray();
        $parentOrder = OrderStage::whereIn('order_id',$orderIds)->with(['order'=>function($query){
            $query->with(['supplier']);
        }])->get();

        $supplier_data = [];
        foreach ($parentOrder as $item) {

            $orderPay = OrderPay::where('order_id',$item->order_id)->where('income_type',self::INCOME_TYPE_GOODS)->where('pay_form',self::PLAT_TO_BRAND)
                ->where('income',self::PAY_COME)->where('supplier_id',$item->order->supp_id)
                ->where('payment_stage',$item->stage)->where('status',1)->first();
            $received_payment_amount = $orderPay?$item->price:0;
            if($item->status == 0){
                $status_name = "用户待付款";
            }elseif($item->status == 1 && $orderPay && $orderPay->status == 0){
                $status_name = "待工厂确认";
            }elseif($item->status == 1 && $orderPay && $orderPay->status == 1){
                $status_name = "已支付";
            }elseif($item->status == 1 && $orderPay && $orderPay->status == 2){
                $status_name = "工厂未收到款";
            }else{
                $status_name = "未支付给工厂";
            }

            $supplier_d = [
                'id'=>$item->id,
                'order_id' => $item->order_id,
                'public_info' => [
                    'company_name' => $item->order->supplier->company_name ? $item->order->supplier->company_name : $item->order->supplier->store_name,
                    'bank_account' => $item->order->supplier->bank_account,
                    'bank_of_accounts' => $item->order->supplier->bank_of_accounts,
                    'opening_branch' => $item->order->supplier->opening_branch,
                ],
                'order_sn' => $item->order->order_sn,
                'status_name' => $status_name,
                'order_total' => $item->order->goods_total,
                'order_price' => $item->order->goods_price,
                'payment_stage_name' => $item->stage == 1 ? "预付款" : "尾款",
                'price' => $item->price,
                'received_payment_amount' => $received_payment_amount,

            ];

            // 默认按钮
            $supplier_d['button_data'] = [
                [
                    "name" => '商品清单',
                    'value' => 3,
                ]
            ];

            // 判断 stage 1 的情况
            if ($item) {
                if ($item->status == 1 && $item->swift_status == 0) {
                    $supplier_d['button_data'][] = [
                        "name" => '确认付款',
                        'value' => 2,
                    ];

                } elseif ($item->swift_status == 1 && $item->status == 1) {

                    $supplier_d['button_data'][] = [
                        "name" => '查看凭证',
                        'value' => 2,
                    ];
                }
            }




            $supplier_data[] = $supplier_d;
        }

        //物流信息
        $SupplierLogisticPrice = SupplierLogisticPrice::with(['supplier'])->where('order_id', $order->id)->where('lock_status', 1)->first();


        $orderGoods = OrderGoods::whereIn('order_id', $orderIds)
            ->with(['goodsOption'])
            ->get();
        $volume = 0;
        $package = 0;
        foreach ($orderGoods as $item) {
            $volume += $item->goodsOption->volume ?? 0;
            $package += $item->goodsOption->package_number ?? 0;
        }



        $SupplierInstallPrice = SupplierInstallPrice::with(['supplier', 'hasOneInstallOrder'])->where('order_id', $order->id)->where('lock_status', 1)->first();
        $freight_price = $SupplierLogisticPrice->freight_price?:0;
        $install_price = $SupplierInstallPrice->freight_price?:0;

        $data = [
            'member' => [
                'avatar' => $order->belongsToMember->avatar,
                'nickname' => $order->belongsToMember->nickname,
                'mobile' => $order->belongsToMember->mobile,
            ],
            'order_sn' => $order->order_sn,
            'order_id' => $order->id,
            'order_total_price' => $order->goods_price + $order->install_price + $order->freight_price,
            'fee_detail' => [
                'prepaid_amount' => $orderStageOne->price,
                'last_amount' => $orderStageTwo->price,
                'freight_price' => $freight_price,
                'install_price' => $install_price,
                'receivable_amount' => $order->goods_price + $freight_price + $install_price,

            ],
            'status_name' => $order->status_name,
            'new_status' => $order->new_status,
            'choose_logistics'=>$order->choose_logistics,
            'choose_install'=>$order->choose_install,
            'order_time' => [
                'create_time' => $order->create_time->toDateTimeString(),
                'confirm_time' => $order->confirm_time->toDateTimeString(),
                'prepaid_time' => $order->first_pay_time->toDateTimeString(),
                'product_time' => $order->product_time->toDateTimeString(),
                'last_pay_time' => $order->last_pay_time->toDateTimeString(),
                'send_time' => $order->send_time->toDateTimeString(),
                'finish_time' => $order->finish_time->toDateTimeString(),

            ],
            'note' => $order->note,
            'project_name' => $order->project->name,
            'goods_total' => $order->goods_total,
            'receiver_info' => [
                'receiver_name' => $orderAddress->realname,
                'receiver_mobile' => $orderAddress->mobile,
                'receiver_address' => $orderAddress->address,
            ],
            'pay_history' => $payList,
            'supplier_data' => $supplier_data,
            'logistic_data' => [
                'freight_price' => $order->freight_price,
                'supplier_id' => $SupplierLogisticPrice->supplier->id,
                'total_package_num' => $package,
                'total_volume' => $volume,
                'logistics_status' => $order->logistics_status,
                'logistics_status_name' => $order->logistic_status_name,
                'company_name' => $SupplierLogisticPrice->supplier->company_name,
                'bank_account' => $SupplierLogisticPrice->supplier->bank_account,
                'bank_of_accounts' => $SupplierLogisticPrice->supplier->bank_of_accounts,
                'opening_branch' => $SupplierLogisticPrice->supplier->opening_branch,

            ],
            'install_data' => [
                'install_price' => $order->install_price,
                'install_days' => $SupplierInstallPrice->hasOneInstallOrder->install_days,
                'install_status' => $SupplierInstallPrice->hasOneInstallOrder->install_status,
                'supplier_id' => $SupplierInstallPrice->supplier->id,
                'floor' => $SupplierInstallPrice->hasOneInstallOrder->floor,
                'has_elevator' => $SupplierInstallPrice->hasOneInstallOrder->has_elevator == 1 ? "有电梯" : "没有电梯",
                'install_status_name' => $order->install_status_name,
                'company_name' => $SupplierInstallPrice->supplier->company_name,
                'bank_account' => $SupplierInstallPrice->supplier->bank_account,
                'bank_of_accounts' => $SupplierInstallPrice->supplier->bank_of_accounts,
                'opening_branch' => $SupplierInstallPrice->supplier->opening_branch,
            ]


        ];
        return $data;
    }


    public function confirmPay($id)
    {
        try {

            $swift_img = request()->input('swift_img');

            $orderStage = OrderStage::where('id', $id)->first();
            $order = Order::find($orderStage->order_id);

            $orderStage->swift_status = 1;
            $orderStage->save();
            $data['order'] = $order;
            $data['thumb'] = $swift_img;
            $data['income'] = self::PAY_COME;
            $data['supplier_id'] = $data['order']->supp_id;
            $data['income_type'] = self::INCOME_TYPE_GOODS;
            $data['pay_form'] = self::PLAT_TO_BRAND;
            $data['pay_price'] = $orderStage->price;
            $data['payment_stage'] = $orderStage->stage;

            //查询订单信息，并通知厂家收款
            $this->submitSupplierOrderPay($data);
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }


    }

    public function revokePay($id)
    {
        try {

            $orderStage = OrderStage::where('id', $id)->first();
            $orderStage->swift_status = 0;
            $orderStage->save();

            $data['order'] = Order::find($orderStage->order_id);
            $data['supplier_id'] = $data['order']->supp_id;
            $data['income_type'] = self::INCOME_TYPE_GOODS;
            $data['pay_form'] = self::PLAT_TO_BRAND;
            $data['payment_stage'] = $orderStage->stage;
            $data['income'] = self::PAY_COME;

            $this->revokeSupplierOrderPay($data);
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }

    }


    public function getPayVoucher($pay_id)
    {

        $remittanceAuditFlow = RemittanceAuditFlow::first();
        $res = RemittanceRecord::where('order_pay_id', $pay_id)->first();
        $processBuilder = RemittanceAuditProcess::where('flow_id', $remittanceAuditFlow->id)->uniacid()->with(['status', 'remittanceRecord' => function ($query) {
            $query->with(['orderPay']);
        }])->where('model_id', $res->id)->first();

        $order = Order::find($processBuilder->order_id);
        $data = [
            'id' => $processBuilder->id,
            'pay_type_id' => $order->pay_type_id,
            'payment_stage_name' => $processBuilder->remittanceRecord->orderPay->payment_stage == 1 ? "预付款" : "尾款",
            'swift_img' => yz_tomedia($processBuilder->remittanceRecord->report_url),
            'pay_sn' => $processBuilder->remittanceRecord->orderPay->pay_sn,
            'amount' => $processBuilder->remittanceRecord->orderPay->amount,
            'order_sn' => $order->order_sn,
            'status_name' => $processBuilder->status_name
        ];
        return $data;
    }

    public function getVoucher($id)
    {
        try {

           $orderStage = OrderStage::where('id', $id)->where('status', 1)->get();

           $data = $orderStage->map(function ($stage) {
               $order = Order::find($stage->order_id);
               $orderPay = OrderPay::where('order_id',$stage->order_id)->where('income_type',self::INCOME_TYPE_GOODS)->where('pay_form',self::PLAT_TO_BRAND)
                   ->where('income',self::PAY_COME)->where('supplier_id',$order->supp_id)
                   ->where('payment_stage',$stage->stage)->first();

                return [
                    'stage' => $stage->stage,
                    'amount' => $stage->price,
                    'order_id'=>$stage->order_id,
                    'total_price' => $stage->order->goods_price,
                    'id' => $stage->id,
                    'swift_img'=>$orderPay?yz_tomedia($orderPay->thumb):"",
                    "status"=> $orderPay?$orderPay->status:0,
                    'swift_status'=>$stage->swift_status,
                    "note"=>$orderPay->note
                ];
            })->values(); // 返回的是 Collection，也可以 ->toArray()
            return $data;
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }
    }

    public function viewVoucher($order_id)
    {
        $order = Order::find($order_id);
        $orderPay = OrderPay::where('order_id',$order_id)->where('income_type',self::INCOME_TYPE_GOODS)->where('pay_form',self::PLAT_TO_BRAND)
                    ->where('income',self::PAY_COME)->where('supplier_id',$order->supp_id)->first();
        return [
          "shop_name"=>$order->shop_name,
          "total_price"=>$order->goods_price,
          "order_id"=>$orderPay->order_id,
          "thumb" => yz_tomedia($orderPay->thumb),
          "amount"=> $orderPay->amount,
          "status"=> $orderPay->status,
          "stage"=>$orderPay->payment_stage
        ];


    }



    public function confirmLogisticPay($order_id, $supplier_id)
    {
        try {
            $order = Order::find($order_id);
            $swift_img = request()->input('swift_img');
            $supplierLosstic = SupplierLogisticPrice::where('order_id', $order_id)->where('supplier_id', $supplier_id)->first();
            $order->logistics_status = 5;
            $order->save();
            $data['order'] = $order;
            $data['thumb'] = $swift_img;
            $data['income'] = self::PAY_COME;
            $data['supplier_id'] = $supplier_id;
            $data['income_type'] = self::INCOME_TYPE_LOGISTIC;
            $data['pay_form'] = self::PLAT_TO_BRAND;
            $data['pay_price'] = $supplierLosstic->freight_price;
            $data['payment_stage'] = 1;

            $this->submitSupplierOrderPay($data);
            return true;
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }

    }

    public function revokeLogisticPay($order_id, $supplier_id)
    {
        try {
            $order = Order::find($order_id);

            $order->logistics_status = 4;
            $order->save();
            $data['order'] = $order;
            $data['supplier_id'] = $supplier_id;
            $data['income_type'] = self::INCOME_TYPE_LOGISTIC;
            $data['pay_form'] = self::PLAT_TO_BRAND;
            $data['payment_stage'] = 1;
            $data['income'] = self::PAY_COME;
            $this->revokeSupplierOrderPay($data);
            return true;
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }

    }


    public function confirmInstallPay($order_id)
    {
        try {

            $swift_img = request()->input('swift_img');
            $installOrder = InstallOrder::where('order_id', $order_id)->first();
            $installOrder->install_status = 4;
            $installOrder->save();
            $supplierInstall = SupplierInstallPrice::where('order_id',$order_id)->where('lock_status',1)->first();
            $order = Order::find($order_id);
            $data['order'] = $order;
            $data['thumb'] = $swift_img;
            $data['income'] = self::PAY_COME;
            $data['supplier_id'] = $supplierInstall->supplier_id;
            $data['income_type'] = self::INCOME_TYPE_INSTALL;
            $data['pay_form'] = self::PLAT_TO_BRAND;
            $data['pay_price'] = $supplierInstall->freight_price;
            $data['payment_stage'] = 1;

            $this->submitSupplierOrderPay($data);
            return true;
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }

    }

    public function revokeInstallPay($order_id)
    {
        try {
            $order = Order::find($order_id);
            $installOrder = InstallOrder::where('order_id', $order_id)->first();
            $installOrder->install_status = 3;
            $installOrder->save();
            $supplierInstall = SupplierInstallPrice::where('order_id',$order_id)->where('lock_status',1)->first();

            $data['order'] = $order;
            $data['supplier_id'] = $supplierInstall->supplier_id;
            $data['income_type'] = self::INCOME_TYPE_INSTALL;
            $data['pay_form'] = self::PLAT_TO_BRAND;
            $data['payment_stage'] = 1;
            $data['income'] = self::PAY_COME;
            $this->revokeSupplierOrderPay($data);
            return true;
        } catch (\Exception $e) {
            throw new ShopException($e->getMessage());
        }

    }

    public function getSignVoucher($id)
    {
        $order = Order::find($id);
        $order_address = OrderAddress::where('order_main_id', $id)->first();
        $install_order = InstallOrder::where('order_id', $id)->first();

        return [
            "realname" => $order_address->realname . " / " . $order_address->mobile,
            "address" => $order_address->address,
            "goods_total" => $order->goods_total,
            "install_day" => $install_order->install_days,
            "install_sign_certificate" => $order->install_sign_certificate
                ? array_map(function ($item) {
                    return yz_tomedia($item);
                }, unserialize($order->install_sign_certificate))
                : [],

        ];
    }




}