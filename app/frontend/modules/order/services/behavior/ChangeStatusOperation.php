<?php
/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/3/20
 * Time: 下午8:16
 */

namespace app\frontend\modules\order\services\behavior;

use app\common\models\Order;
use app\common\models\OrderAddress;
use app\common\models\project\InstallOrder;
use app\common\models\project\InstallTracks;
use app\common\models\project\LogisticsTracks;
use app\common\models\project\OrderStage;
use app\common\models\project\ProjectBid;
use app\frontend\modules\project\models\Project;
use Yunshop\Supplier\common\models\SupplierInstallPrice;
use Yunshop\Supplier\common\models\SupplierLogisticPrice;
use Yunshop\Supplier\common\models\SupplierOrderReads;

abstract class ChangeStatusOperation extends OrderOperation
{
    /**
     * @var int 改变后状态
     */
    protected $statusAfterChanged;
    /**
     * 更新订单表
     * @return bool
     */
    protected function updateTable(){

      /*  if($this->order_type == 1 && $this->statusAfterChanged == 1){
           $isPaid = OrderStage::where('order_id',$this->id)->where('stage',1)->where('pay_id',$this->order_pay_id)->where('status',1)->first();
           if($isPaid){
               \Log::debug("商品订单已经支付");
               return true;
           }
        }
        if(in_array($this->order_type,[2,3,4,5]) && $this->statusAfterChanged == 1){
            $isPaid = Order::where('order_id',$this->id)->where('status',1)->first();
            if($isPaid){
                \Log::debug("其他上门投标已经支付");
                return true;
            }
        }*/
        $this->status = $this->statusAfterChanged;
        $orderIds = Order::where('parent_id',$this->id)->pluck('id')->toArray();
        $mergedOrderIds = array_merge($orderIds, [$this->id]);
        $time = time();
       if($this->statusAfterChanged == 1){
           //如果有收到款更改时间
            Project::where('id',$this->project_id)->update(
               ['change_time'=>time()]
           );
       }
        if($this->order_type == 2 && $this->statusAfterChanged == 1){
            ProjectBid::where('id',$this->bid_id)->update(
                [
                    'bid_order_status'=>1
                ]
            );
        }
        if($this->order_type == 3 && $this->statusAfterChanged == 1){
            ProjectBid::where('id',$this->bid_id)->update(
                [
                    'brand_order_status'=>1
                ]
            );
        }

        if($this->order_type == 4 && $this->statusAfterChanged == 1){
            ProjectBid::where('id',$this->bid_id)->update(
                [
                    'project_order_status'=>1
                ]
            );
        }

        if($this->orderStatus == 0 && $this->statusAfterChanged == 1){
           //获取子订单

           $this->orderStatus = 1;
           $this->first_pay_time = $time;
           $this->payment_stage = 2;
           //阶段一付款
           OrderStage::whereIn('order_id',$mergedOrderIds)->where('stage',1)->update(
               [
                   'status'=>1,
                   'pay_id'=>$this->order_pay_id,
                   'pay_type_id'=>$this->pay_type_id
               ]
           );


           $save['orderStatus'] = 1;
           $save['first_pay_time'] = $time;
           $save['status'] = 1;
           $save['pay_time'] = $time;
           $save['payment_stage'] = 2;
           $save['pay_type_id'] = $this->pay_type_id;
           $save['order_pay_id'] = $this->order_pay_id;

        }/*elseif($this->orderStatus == 1 && $this->statusAfterChanged == 1){
               $this->orderStatus = 2;
        }*/elseif($this->orderStatus == 2 && $this->statusAfterChanged == 1){
            $order_new = Order::find($this->id);
            //如果有选择物流
            if($order_new->choose_logistics == 1){
                //分配物流
                $this->dispenseLogistic();
                //更新物流状态
                $receiv_address = OrderAddress::where('order_main_id', $this->id)->first();
                $data['address'] =$receiv_address->address;
                $data['current_status'] = 1;
                $data['description'] = "接单完成";
                LogisticsTracks::updateLogisticsStatus($this->id,$data);
            }

            if($order_new->choose_install == 1){
                //分配安装
                $this->dispenseInstall();
                $receiv_address = OrderAddress::where('order_main_id', $this->id)->first();
                $data['address'] =$receiv_address->address;
                $data['current_status'] = 1;
                $data['description'] = "接单完成";
                InstallTracks::updateInstallStatus($this->id,$data);
            }



            $this->orderStatus = 3;
            $this->last_pay_time = $time;
            $logisticOrderSn = generateLogisticsNumber();
            $save['orderStatus'] = 3;
            $save['last_pay_time'] = $time;
            $save['pay_time'] = $time;
            $save['status'] = 1;
            $save['logistics_order_sn'] = $logisticOrderSn;
            $save['pay_type_id'] = $this->pay_type_id;
            $save['order_pay_id'] = $this->order_pay_id;

            OrderStage::whereIn('order_id',$mergedOrderIds)->where('stage',2)->update([
                'status'=>1,
                'pay_id'=>$this->order_pay_id,
                'pay_type_id'=>$this->pay_type_id
            ]);

        }
        if($this->time_field == "restore_time" && $this->statusAfterChanged == -1){
            $this->cancel_time = 0;
            $save['cancel_time'] = 0;
            $save['status'] = 0;
        }/*elseif($this->time_field == "cancel_time" && $this->statusAfterChanged == -1){
            $this->cancel_time = $time;
            $save['cancel_time'] = $time;
            $save['status'] = -1;
        }*/else{
            if(isset($this->time_field)){
                $time_fields = $this->time_field;
                $this->$time_fields = $time;
                $save[$time_fields] = $time;
            }
        }

        Order::whereIn('id',$orderIds)->update($save);
        return $this->save();
    }


    //分配安装
    private function dispenseInstall()
    {
        $fenpei = SupplierInstallPrice::where('lock_status',1)->where('order_id',$this->id)->first();

        $fenpei->bidding_status = 4;
        $fenpei->save();

        $order = Order::find($fenpei->order_id);
        $order->install_price = $fenpei->freight_price;
        $order->install_dispense_status = 4;
        $order->save();

        $installOrder = InstallOrder::where('order_id',$fenpei->order_id)->first();
        $installOrder->install_dispense_time = time();
        $installOrder->install_bidding_time = time();
        $installOrder->save();
        //发布通知
        $existsRead = SupplierOrderReads::where('supplier_id', $fenpei->supplier_id)
            ->where('order_id', $fenpei->order_id)->where('type',2)
            ->exists();
        if(!$existsRead){
            SupplierOrderReads::create(
                [
                    'supplier_id' => $fenpei->supplier_id,
                    'order_id' => $fenpei->order_id,
                    'type' => 2,
                ]
            );
        }
        //其他物流公司更改为未中标
        SupplierInstallPrice::where('order_id',$fenpei->order_id)->where('id','!=',$fenpei->id)->update(
            [
                'bidding_status'=>3,
                'notbid_time'=>time()
            ]
        );
    }


    //分配物流
    private function dispenseLogistic()
    {
        $fenpei = SupplierLogisticPrice::where('lock_status',1)->where('order_id',$this->id)->first();

        $fenpei->logistics_status = 1;
        $fenpei->bidding_status = 4;
        $fenpei->bid_time = time();
        $fenpei->save();

        $order = Order::find($this->id);
        $order->freight_price = $fenpei->freight_price;
        $order->bid_time = time();
        $order->logistics_status = 2;
        $order->dispense_status = 4;
        $order->save();
        //发布通知
        $existsRead = SupplierOrderReads::where('supplier_id', $fenpei->supplier_id)
            ->where('order_id', $this->id)->where('type',1)
            ->exists();
        if(!$existsRead){
            SupplierOrderReads::create(
                [
                    'supplier_id' => $fenpei->supplier_id,
                    'order_id' => $fenpei->order_id,
                    'type' => 2,
                ]
            );
        }

        //其他物流公司更改为未中标
        SupplierLogisticPrice::where('order_id',$fenpei->order_id)->where('id','!=',$fenpei->id)->update(
            [
                'bidding_status'=>3,
                'notbid_time'=>time()
            ]
        );
    }

    /**
     * 执行订单操作
     * @return bool|void
     * @throws \app\common\exceptions\AppException
     */
    public function handle()
    {

        parent::handle();
        $this->updateTable();
        $this->_fireEvent();
    }
}