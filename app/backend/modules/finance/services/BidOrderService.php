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

class BidOrderService extends CommonService
{

    private function getStatus($status)
    {
        switch ($status) {
            case 1:
                return ['status' => 0];
            case 2:
                return ['status' => 3];
            case 3:
                return ['status' => -1];


            default:
                return []; // 返回空数组表示不加条件
        }
    }


    public function getList($search)
    {

        $query = VueOrder::with(['project' => function ($query) {
            $query->select("id", "name");
        }])->whereIn('order_type', [2, 3, 4]);
        if (!empty($search['status'])) {
            if ($search['status'] == 2) {
                $query->whereIn('status', [1, 2, 3]);
            } else {
                $conditions = $this->getStatus($search['status']);
                if (!empty($conditions)) {
                    $query->where($conditions); // 数组形式添加多个 where 条件
                }
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

        if ($search['order_type']) {
            $query->where('order_type', $search['order_type']);

        }

        $list = $query->orderBy('create_time', 'desc')->paginate(15);
        $list->transform(function ($order) {

            $order->project_name = $order->project->name;
            $order->paid_amount = 0;
            if (in_array($order->status, [1, 2, 3])) {
                $order->paid_amount = $order->price;
            }
            return $order;
        });

        return $list;


    }


    public function detail($id)
    {
        $order = VueOrder::with(['belongsToMember' => function ($query) {
            $query->select("uid", "avatar", "nickname", "mobile");
        }, 'supplier'])->find($id);


        $orderPay = OrderPay::where('order_id', $order->id)->where('supplier_id',$order->supp_id)
            ->where('income_type',$order->order_type)->where('pay_form',self::PLAT_TO_BRAND)->where('income',self::PAY_COME)->first();
        $supplier_d = [
            'order_id' => $order->id,
            'public_info' => [
                'company_name' => $order->supplier->company_name ? $order->supplier->company_name : $order->supplier->store_name,
                'bank_account' => $order->supplier->bank_account,
                'bank_of_accounts' => $order->supplier->bank_of_accounts,
                'opening_branch' => $order->supplier->opening_branch,
            ],
            'order_sn' => $order->order_sn,

            'order_type' => $order->order_type,
            'price' => $order->price,
            'project_name' => $order->project->name,
            'paid_amount' => $orderPay->status == 1 ? $order->price : 0,
            'status' => $orderPay ? $orderPay->status : 0,
            'supplier_id'=>$order->supplier->id,
        ];

        if ($order->order_type == 2) {
            $order_type_name = "标书制作费";
        } elseif ($order->order_type == 3) {
            $order_type_name = "品牌使用费";
        } elseif ($order->order_type == 4) {
            $order_type_name = "项目保证金";
        }

        $data = [
            'member' => [
                'avatar' => $order->belongsToMember->avatar,
                'nickname' => $order->belongsToMember->nickname,
                'mobile' => $order->belongsToMember->mobile,
            ],
            'order_sn' => $order->order_sn,
            'order_id' => $order->id,
            'order_total_price' => $order->price,

            'status_name' => $order->status_name,
            'new_status' => $this->getNewStatus($order->status),
            'order_type_name' => $order_type_name,
            'order_time' => [
                'create_time' => $order->create_time->toDateTimeString(),
                'pay_time' => $order->pay_time->toDateTimeString(),

                'finish_time' => $order->finish_time->toDateTimeString(),

            ],

            'price' => $order->price,
            'unpaid_amount' => $orderPay->status == 1? $order->price : 0,

            'supplier_data' => $supplier_d,

        ];
        return $data;
    }


    private function getNewStatus($status)
    {
        if ($status == 0) {
            return 1;
        } elseif (in_array($status, [1, 2])) {
            return 2;
        } elseif ($status == 3) {
            return 3;
        }
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
            $data['income_type'] = $order->order_type;
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
            //查询订单信息，并通知厂家收款
            $data['order'] = Order::find($order_id);
            $data['supplier_id'] = $data['order']->supp_id;
            $data['income_type'] = $data['order']->order_type;
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

        $order = Order::find($order_id);
        $orderPay = OrderPay::with(['supplier'])->where('order_id', $order_id)->where('supplier_id',$order->supp_id)
            ->where('income_type',$order->order_type)->where('pay_form',self::PLAT_TO_BRAND)->where('income',self::PAY_COME)->first();
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