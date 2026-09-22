<?php
/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2018/8/1
 * Time: 下午6:43
 */

namespace app\frontend\modules\order\operations\member;

use app\frontend\modules\order\operations\OrderOperation;

class CheckInvoice extends OrderOperation
{
    public function getApi()
    {
        return 'order.operation.check-invoice';
    }
    public function getName()
    {
        if(is_null($this->order->orderInvoice2)){
            return "申请发票";
        }else{
            return '查看发票';
        }

    }
    public function getValue()
    {
        if(is_null($this->order->orderInvoice2)){
            return 60;
        }else{
            return static::CHECK_INVOICE;
        }

    }

    public function enable()
    {
       // $trade = \Setting::get('shop.trade')['invoice'];
       if(in_array($this->order->order_type,[3,4])){
           return false;
       }
        /*if (is_null($this->order->orderInvoice) || empty($this->order->orderInvoice->collect_name)) {
            return false;
        }*/
       /* if(is_null($this->order->orderInvoice2)){
            return false;
        }*/
        return true;
    }
}