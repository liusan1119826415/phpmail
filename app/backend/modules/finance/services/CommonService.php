<?php


namespace app\backend\modules\finance\services;


use app\backend\modules\order\models\Order;
use app\common\exceptions\AppException;
use app\common\models\OrderPay;
use Illuminate\Support\Facades\DB;
use Yunshop\Supplier\common\models\SupplierPayOrder;

class CommonService
{
    const IN_COME = 1; // 收入

    const PAY_COME = 2; //支付

    const MEMBER_TO_PLAT = 1;//用户付平台

    const PLAT_TO_BRAND = 2;//平台付厂家

    const BRAND_TO_PLAT = 3; //厂家付平台


    const PLAT_TO_MEMBER = 4; //平台付用户

    const INCOME_TYPE_GOODS = 1; //商品


    const INCOME_TYPE_BID = 2; //投标

    const INCOME_TYPE_BRAND = 3; //品牌

    const INCOME_TYPE_BALANCE = 4; //保证金

    const INCOME_TYPE_DOOR = 5; //上门

    const INCOME_TYPE_LOGISTIC = 6; //物流

    const INCOME_TYPE_INSTALL = 7; //安装




    public function submitSupplierOrderPay($data)
    {
       return DB::transaction(function () use($data){
            $order = $data['order'];
            $income_type = $data['income_type'];
            $supplier_id = $data['supplier_id']?:0;
            $pay_form = $data['pay_form'];
            $pay_price = $data['pay_price'];
            $thumb = $data['thumb'];
           // $refund_id = $data['refund_id'];
            $pay_come = $data['income'];
            $payment_stage = $data['payment_stage'];
            $pay_sn = \app\frontend\modules\order\services\OrderService::createPaySN();
            $result = OrderPay::where('order_id',$order->id)->where('income_type',$income_type)->where('pay_form',$pay_form)
                ->where('income',$pay_come)->where('supplier_id',$supplier_id)->where('payment_stage',$payment_stage)->first();
            if($result){
                return true;
            }

            $pay_data = [
                'pay_sn'=>$pay_sn,
                'status'=>1,
                'order_id'=>$order->id,
                'project_id'=>$order->project_id,
                'supplier_id'=>$supplier_id,
                'pay_type_id'=>16,
               // 'refund_id'=>$refund_id,
                'pay_time'=>time(),
                'pay_form'=>$pay_form,
                'amount'=>$pay_price,
                'thumb'=>$thumb,
                'uid'=>0,
                'income_type'=>$income_type,
                'payment_stage'=>$payment_stage,
                'income'=>$pay_come
            ];

            $model = new OrderPay();
            $model->setRawAttributes($pay_data);
            //字段检测
            $validator = $model->validator($model->getAttributes());
            if ($validator->fails()) {//检测失败
                throw new AppException($validator->messages());
            } else {
                //数据保存
                if ($model->save()) {
                    $order->receive_status = 1;
                    $order->save();
                    //显示信息并跳转
                    return $model->id;
                } else {
                    throw new AppException("操作失败");
                }
            }
        });

    }

    public function revokeSupplierOrderPay($data)
    {

        DB::transaction(function () use($data) {


            $order = $data['order'];
            $income_type = $data['income_type'];
            $supplier_id = $data['supplier_id']?:0;
            $pay_form = $data['pay_form'];
            $payment_stage = $data['payment_stage'];
            $income = $data['income'];
            $orderPay = OrderPay::where('order_id',$order->id)->where('income',$income)->where('income_type',$income_type)
                ->where('pay_form',$pay_form)->where('supplier_id',$supplier_id)->where('payment_stage',$payment_stage)->first();
            if($orderPay){
                $orderPay->delete();
            }
            $order->receive_status = 1;
            $order->save();

        });

    }
}