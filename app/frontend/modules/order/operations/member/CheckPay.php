<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/1
 * Time: 下午6:43
 */

namespace app\frontend\modules\order\operations\member;

use app\common\models\OrderPay;
use app\common\modules\payType\remittance\models\flows\RemittanceAuditFlow;
use app\common\modules\payType\remittance\models\process\RemittanceAuditProcess;
use app\frontend\modules\order\operations\OrderOperation;

class CheckPay extends OrderOperation
{
    public function getApi()
    {
        return '';
    }

    public function getName()
    {
       /*if(($this->order->pay_type_id == 16 && $this->order->status == 0) || ($this->order->pay_type_id == 16 && $this->order->status == 1 && $this->order->orderStatus == 2)){
           $remittanceAuditFlow = RemittanceAuditFlow::first();
           $processBuilder = RemittanceAuditProcess::where('flow_id', $remittanceAuditFlow->id)->whereHas('remittanceRecord', function ($query)  {
               $query->where('order_pay_id', $this->order->order_pay_id);
           })->first();
           if($this->order->order_type == 1){
               $orderPay = OrderPay::find($this->order->order_pay_id);
               if ($processBuilder->state == "processing") {
                   return $orderPay->payment_stage == 1?'预付款支付查验中':"尾款支付查验中";
               } elseif ($processBuilder->state == "completed") {

                   return $orderPay->payment_stage == 1?'预付款支付成功':"尾款支付成功";
               } else {

                   return $orderPay->payment_stage == 1?'预付款支付驳回':"尾款支付驳回";
               }
           }else{
               if ($processBuilder->state == "processing") {
                   return '支付查验中';
               } elseif ($processBuilder->state == "completed") {

                   return "支付成功";
               } else {

                   return "支付驳回";
               }
           }

       }*/

    }

    public function getValue()
    {
        return 71;
    }

    public function enable()
    {

       /* if(($this->order->pay_type_id == 16 && $this->order->status == 0) || ($this->order->pay_type_id == 16 && $this->order->status == 1 && $this->order->orderStatus == 2)){
            return true;
        }*/
       

    }
}
