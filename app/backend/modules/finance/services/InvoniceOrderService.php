<?php


namespace app\backend\modules\finance\services;


use app\backend\modules\finance\models\OrderInvonice;
use app\backend\modules\order\models\VueOrder;
use app\common\models\OrderAddress;
use app\common\models\project\OrderStage;


class InvoniceOrderService
{


    public function getList($search)
    {

        $query = OrderInvonice::where('is_canceled', 0)->with(['order', 'company', 'title']);
        if (!empty($search['status'])) {
            $query->where('status', $search['status']);

        }


        if ($search['order_id']) {

            $query->where('id', $search['order_id']);
        }

        if ($search['order_sn']) {

            $query->whereHas('order', function ($query) use ($search) {
                $query->where('order_sn', $search['order_sn']);
            });
        }

        if ($search['project_name']) {

            $query->whereHas('order', function ($query) use ($search) {
                $query->whereHas('project', function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search['project_name'] . '%');
                });

            });
        }


        $list = $query->orderBy('created_at', 'desc')->paginate(15);
        $list->transform(function ($order) {

            $order->project_name = $order->order->project->name;
            return $order;

        });

        return $list;


    }


    public function getOrderInvoiceInfo($id)
    {
        $OrderInvonice = OrderInvonice::with(['company', 'title', 'uploads'])->where('id', $id)->first();
        return $OrderInvonice;
    }

    public function detail($id)
    {
        $orderInvonice = OrderInvonice::with(['company', 'title', 'uploads'])->where('id', $id)->first();

        $order = VueOrder::with(['belongsToMember' => function ($query) {
            $query->select("uid", "avatar", "nickname", "mobile");
        }])->find($orderInvonice->order_id);
        $orderStageOne = OrderStage::where('order_id', $order->id)->where('stage', 1)->first();
        $orderStageTwo = OrderStage::where('order_id', $order->id)->where('stage', 2)->first();
        $orderAddress = OrderAddress::where('order_main_id', $orderInvonice->order_id)->first();
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
                'freight_price' => $order->freight_price,
                'install_price' => $order->install_price,
                'receivable_amount' => $order->goods_price + $order->install_price + $order->freight_price,

            ],
            'status_name' => $order->status_name,
            'new_status' => $order->new_status,
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
            'invoice_type_name'=>$orderInvonice->invoice_type == 1?"普通发票":"增值税专用发票",
            'invoice_type'=>$orderInvonice->invoice_type,
            "invoice_content"=>"商品明细",
            "invoice_amount"=>$orderInvonice->invoice_amount,
            "status"=>$orderInvonice->status,
            "invoice_id"=>$orderInvonice->id,
            "status_name"=>$orderInvonice->status ==1?"未开票":"已开票",
            "apply_time"=>$orderInvonice->created_at,


        ];

        if($orderInvonice->invoice_type == 1){
            $data['title_name'] = $orderInvonice->title_name?$orderInvonice->title_name:$orderInvonice->title->title_name;

        }else{
            $data['title_name'] = $orderInvonice->title->title_name;
            $data['tax_number'] = $orderInvonice->company->tax_number;
            $data['bank_name'] = $orderInvonice->company->bank_name;
            $data['bank_account'] = $orderInvonice->company->bank_account;
            $data['company_address'] = $orderInvonice->company->company_address;
            $data['company_phone'] = $orderInvonice->company->company_phone;
        }
        foreach ($orderInvonice->uploads as &$item){
            if($item->invoice_category == 1){
                $item->category_name = "家具";
            }elseif($item->invoice_category == 2){
                $item->category_name = "物流运输";
            }elseif($item->invoice_category == 3){
                $item->category_name = "安装搬运";
            }

        }
        $data['tax_info'] = $orderInvonice->uploads;
        return $data;

    }
}