<?php


namespace app\frontend\modules\project\services;


use app\common\exceptions\AppException;
use app\common\models\Order;
use app\common\modules\pcnotice\Template;
use app\frontend\modules\order\services\OrderService as BaseOrderService;
use Yunshop\Supplier\common\models\Supplier;
use Yunshop\Supplier\common\models\SupplierCharge;
use Yunshop\Supplier\common\models\SupplierOrder;

class OrderService
{

      public $data;
      public function __construct($data)
      {
          $this->data = $data;
      }

    /**
     * 生成投标订单业务逻辑
     */
      /*public function generateOrder()
      {

          $member_id = \YunShop::app()->getMemberId();
          $prices = $this->getFee();
          $time = time();
          //投标订单之前的业务将取消订单关闭状态
          $updateData['status'] = Order::CLOSE;
          Order::where('bid_id',$this->data['bid_id'])->update($updateData);
          $order_data =[
              [
                  'uniacid'=> \YunShop::app()->uniacid,
                  'uid'=>$member_id,
                  'order_sn'=>BaseOrderService::createOrderSN(),
                  'create_time'=>$time,
                  'order_type'=>2,
                  'price'=>$prices['bid_fee'],
                  'project_id'=>$this->data['project_id'],
                  'bid_id'=>$this->data['bid_id'],
                  'supp_id'=>$this->data['supplier_id'],
                  'created_at'=>$time,
                  'updated_at'=>$time
              ],
              [
                  'uniacid'=> \YunShop::app()->uniacid,
                  'uid'=>$member_id,
                  'order_sn'=>BaseOrderService::createOrderSN(),
                  'create_time'=>$time,
                  'order_type'=>3,
                  'price'=>$prices['brand_fee'],
                  'project_id'=>$this->data['project_id'],
                  'bid_id'=>$this->data['bid_id'],
                  'supp_id'=>$this->data['supplier_id'],
                  'created_at'=>$time,
                  'updated_at'=>$time
              ],

          ];
          if($this->data['bid_type'] == 2){
              $arr2 = [
                  [   //项目保证金
                      'uniacid'=> \YunShop::app()->uniacid,
                      'uid'=>$member_id,
                      'order_sn'=>BaseOrderService::createOrderSN(),
                      'create_time'=>$time,
                      'order_type'=>4,
                      'price'=>$prices['brand_fee'],
                      'project_id'=>$this->data['project_id'],
                      'bid_id'=>$this->data['bid_id'],
                      'supp_id'=>$this->data['supplier_id'],
                      'created_at'=>$time,
                      'updated_at'=>$time
                  ]
              ];
              $order_data = array_merge($order_data,$arr2);
          }
          Order::insert($order_data);

      }*/

    public function generateOrder()
    {
        try {
            $member_id = \YunShop::app()->getMemberId();
            $prices = $this->getFee();
            $time = time();

            // 取消之前的投标订单
            Order::where('bid_id', $this->data['bid_id'])->update(['status' => Order::CLOSE,'cancel_time'=>time()]);
            //如果项目在助手上门升级了服务费那么不用生成品牌订单
            $brand_order = Order::where('project_id',$this->data['project_id'])->where('status','!=','-1')->where('order_type',2)->first();
            // 订单数据
            $order_data = [
                [
                    'order_type' => 3,
                    'price' => $prices['brand_fee'],
                ]
            ];

            // 如果 bid_type 为 1，增加保证金订单
            if ($this->data['bid_type'] == 1 && $this->data['need_bid_bond'] == 1) {
                $order_data[] = [
                    'order_type' => 4,
                    'price' => $this->data['amount'],
                    'goods_price'=>$this->data['amount'],
                ];
            }
            //2 标书制作费订单  3 品牌使用费订单 4 项目保证金订单
            if($this->data['bid_type'] == 2 && $this->data['need_bid_document'] == 1){
                if(!$brand_order){
                    $order_data[] = [
                        'order_type' => 2,
                        'price' => $prices['bid_fee'],
                        'goods_price'=>$prices['bid_fee'],
                    ];
                }

            }
            if($this->data['bid_type'] == 1){
                if(!$brand_order) {
                    $order_data[] = [
                        'order_type' => 2,
                        'price' => $prices['bid_fee'],
                        'goods_price' => $prices['bid_fee'],
                    ];
                }
            }

            $order_ids = []; // 存储插入的订单 ID

            // 遍历插入订单
            foreach ($order_data as $data) {
                $data['uniacid'] = \YunShop::app()->uniacid;
                $data['uid'] = $member_id;
                $data['order_sn'] = BaseOrderService::createOrderSN();
                $data['create_time'] = $time;
                $data['project_id'] = $this->data['project_id'];
                $data['bid_id'] = $this->data['bid_id'];
                $data['supp_id'] = $this->data['supplier_id'];
                $data['created_at'] = $time;
                $data['updated_at'] = $time;

                // 插入订单并获取 ID
                $order_id = Order::insertGetId($data);
                $order_ids[] = $order_id;

                $option['related_id'] = $order_id;
                app('notification')->send(
                    $member_id, // 用户ID
                    Template::ORDER,
                    Template::ORDER_TYPE[$data['order_type']],
                    getNoticeTitle($data['order_type'],"SUBMIT"),
                    ['order_no' => $data['order_sn'],'amount'=>$data['price']],
                    $option

                );

            }

            // 插入 SupplierOrder 记录
            $supplier = Supplier::where('id',$this->data['supplier_id'])->first();
            foreach ($order_ids as $order_id) {
                SupplierOrder::insert([
                    'order_id' => $order_id,
                    'supplier_id' => $this->data['supplier_id'],
                    "member_id"=>$supplier->member_id,
                    'created_at' => $time,
                    'updated_at' => $time,
                ]);
            }
        } catch (\Exception $e) {
             throw new AppException($e->getMessage());
        }
    }

      protected function getFee()
      {
          $supplierCharges = SupplierCharge::where('supplier_id', $this->data['supplier_id'])
              ->first();
          return ['bid_fee'=>$supplierCharges->bid_document_fee?:0,'brand_fee'=>$supplierCharges->brand_fee?:0];
      }

}