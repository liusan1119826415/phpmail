<?php
namespace app\frontend\modules\refund\services;

use app\common\exceptions\AppException;
use app\common\models\Address;
use app\common\models\Order;
use app\common\models\refund\RefundApply;
use app\common\modules\refund\product\RefundOrderTypeBase;
use app\common\modules\refund\RefundOrderFactory;
use Carbon\Carbon;

/**
 * Created by PhpStorm.
 * Author:
 * Date: 2017/4/13
 * Time: 下午2:21
 */
class RefundService
{

    /**
     * @param Order $order
     * @return mixed
     * @throws AppException
     */
    public static function refundApplyData(Order $order)
    {
        //todo 优化处理：1、把这个门店商品预约的代码干掉
        //2、把统一的处理全部放到 售后退款工厂里 RefundOrderTypeBase
        //3、进行业务划分，对应的插件业务全部放到对应插件 通过订单类型 shop-foundation.refund.order-type 配置获取

        $refundOrder = RefundOrderFactory::getInstance()->getRefundOrder($order);

        $refundOrder->applyBeforeValidate();

        return self::afterSalesApply($refundOrder);
    }
    public static function editRefundApply(Order $order)
    {
        $refundOrder = RefundOrderFactory::getInstance()->getRefundOrder($order);

        return self::afterSalesApply($refundOrder);
    }

    public static function afterSalesApply(RefundOrderTypeBase $refundOrder)
    {
        //0,92,120
        //支持部分退款的订单类型，平台订单，供应商订单，中台供应链
       // $data['support_batch'] = $refundOrder->multipleRefund();

        $data['order'] = $refundOrder->frontendFormatArray();

        $project_data = Order::select('id',"project_id","order_sn","status","goods_total","goods_price","pay_time",'first_pay_time','order_type','last_pay_time')->with(['project'=>function($query){
            $query->select("id","name");
        },'orderPayments','myOrderAddress'=>function($query){
            $query->select("id","order_main_id","address","mobile","realname");
        }])->find($data['order']['id']);

        $payTime = $project_data->first_pay_time;

        // 计算3天后的时间
        $threeDaysLater = $payTime->copy()->addHours(72);
        $now = Carbon::now();
        $data['hour72'] = $now->lessThan($threeDaysLater)?1:0;



        $project_data = self::getRefundPrice($project_data);

        $data['project_data'] = $project_data;
       // $refundedPrice = $refundOrder->getAfterSales();

       // $orderOtherPrice = $refundOrder->getOrderOtherPrice();

       // $orderFreightPrice = $refundOrder->getOrderFreightPrice();

        //这里减去运费和其他费用是因为前端直接拿这个字段当订单金额，但是售后现在把运费分离出来了。
       // $data['order_goods_price'] = max($data['order']['price'] - $orderFreightPrice - $orderOtherPrice,0);

        //可退运费
       // $data['refundable_freight'] = max(bcsub($orderFreightPrice, $refundedPrice->sum('freight_price'),2),0);
        //订单可退其他费用
      //  $data['refundable_other'] = max(bcsub($orderOtherPrice, $refundedPrice->sum('other_price'),2),0);


        //$data['send_back_way'] = RefundService::getSendBackWay($refundOrder->getOrder());

        $data['refundTypes'] = $refundOrder->getOptionalTypes();
        $data['refund_status'] = 1;

        return $data;
    }


    private static function getRefundPrice($project_data)
    {

         switch ($project_data->order_type){
             case 1:
                 $refund_price = 0;
                 foreach ($project_data->orderPayments as $item){
                     if($item->status == 1){
                         $refund_price+=$item->price;
                     }
                 }

                 if($project_data->choose_logistics == 1){
                     $refund_price += $project_data->freight_price;

                 }
                 if($project_data->choose_install == 1){
                     $refund_price += $project_data->install_price;

                 }
                 $project_data->refund_price = $refund_price;
                 break;
             case 2:
             case 3:
             case 4:
             case 5:
                 $project_data->refund_price = $project_data->price;
                 break;
         }
         return $project_data;
    }

    public static function getOptionalType(Order $order)
    {
        $refundTypes = [];

        if ($order->status >= \app\common\models\Order::WAIT_SEND) {
            $refundTypes[] = [
                'name'  => '退款(仅退款不退货)',
                'value' => RefundApply::REFUND_TYPE_REFUND_MONEY,
                'desc'  => '未收到货或者不用退货只退款',
                'icon'  =>  'icon-fontclass-daizhifu',
                'reasons' => [
                    'not_received' => [
                        '拍错/多拍/不想要',
                        '货物破损',
                        '快递送货问题',
                        '差价',
                        '其他原因'
                    ],
                    'received' => [
                        '货物破损',
                        '少件、漏发',
                        '差价',
                        '商品质量问题',
                        '其他原因',
                    ],
                ],
            ];
        }

        if (!$order->isVirtual() && $order->status >= \app\common\models\Order::WAIT_RECEIVE) {

            $refundTypes[] = [
                'name' => '退款退货',
                'value' =>  RefundApply::REFUND_TYPE_RETURN_GOODS,
                'desc'  => '已收到货，需要退款退货',
                'icon'  =>  'icon-fontclass-daishouhuo',
                'reasons' => [
                    'not_received' => [],
                    'received' => [
                        '包装或商品破损、少商品',
                        '质量问题',
                        '配送问题',
                        '拍错/多拍/不想要',
                        '其他原因（可填）',
                    ]
                ],
            ];

            $refundTypes[] = [
                'name' => '换货',
                'value' => RefundApply::REFUND_TYPE_EXCHANGE_GOODS,
                'desc'  => '已收到货，需要更换',
                'icon'  =>  'icon-fontclass-daifahuo',
                'reasons' => [
                    'not_received' => [],
                    'received' => [],
                ],
            ];
        }

        return $refundTypes;
    }

    public static function getSendBackWay($order)
    {
        return RefundBackWayService::getBackWay($order);
    }

    public static function getSendBackWayData($refundApply)
    {
        return RefundBackWayService::getBackWayClassData($refundApply);
    }

    public static function getSendBackWayDetailData($refundApply)
    {
        return RefundBackWayService::getBackWayDetailData($refundApply);
    }

    public static function createOrderRN()
    {
        $refundSN = createNo('RN', true);
        while (1) {
            if (!RefundApply::where('refund_sn', $refundSN)->first()) {
                break;
            }
            $refundSN = createNo('RN', true);
        }
        return $refundSN;
    }

}
