<?php
/**
 * Created by PhpStorm.
 * 
 *
 *
 * Date: 2021/12/23
 * Time: 15:39
 */

namespace app\frontend\modules\refund\services\operation;


use app\backend\modules\refund\services\operation\RefundOperation;
use app\common\events\order\refund\BeforeRefundApplyEvent;
use app\common\exceptions\AppException;
use app\common\models\OrderGoods;
use app\common\models\project\OrderStage;
use app\common\models\refund\RefundGoodsLog;
use app\common\models\refund\RefundProcessLog;
use app\common\modules\refund\RefundOrderFactory;
use app\frontend\modules\member\listeners\Order;
use app\frontend\modules\refund\services\RefundMessageService;
use app\common\events\order\OrderRefundApplyEvent;
use app\common\services\SystemMsgService;
use app\frontend\modules\refund\services\RefundService;
use app\common\models\refund\RefundOrderGoods;
use app\Jobs\UploadModelJob;
use Illuminate\Support\Carbon;
use app\Jobs\RefundJob;
use Illuminate\Contracts\Bus\Dispatcher;
class RefundApply extends RefundOperation
{
    protected $statusAfterChanged = self::WAIT_CHECK;
    protected $name = '申请退款';
    protected $timeField = 'create_time'; //操作时间

    protected $refundGoods;


    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (!isset($this->uid) && \YunShop::app()->getMemberId()) {
            $this->uid = \YunShop::app()->getMemberId();
        }
    }


    protected function updateBefore()
    {

        //售后记录必须是这笔订单所属会员的
        if (!isset($this->uid) || $this->uid != $this->order->uid) {
            $this->uid  = $this->order->uid;
        }

        $refundApplyData = $this->getRequest()->only([
            'reason', 'content', 'refund_type', 'order_id','receive_status','refund_way_type',
        ]);


        if (is_array($this->getRequest()->input('images'))) {
            $refundApplyData['images'] = $this->getRequest()->input('images');
        } else {
            $refundApplyData['images'] = $this->getRequest()->input('images') ? json_decode($this->getRequest()->input('images'), true):[];
        }



        //退款总金额,换货售后退款金额为0
        if ($refundApplyData['refund_type'] == static::REFUND_TYPE_EXCHANGE_GOODS) {

            $refundApplyData['price'] = 0;

        } else {
            //申请退款金额
            $refundApplyData['apply_price'] = $this->getRefundApplyPrice();

            //退款运费金额
            $refundApplyData['freight_price'] = $this->getFreightPrice();

            //退款安装金额
            $refundApplyData['install_price'] = $this->getInstallPrice();

            //退款其他金额
            $refundApplyData['other_price'] =  $this->getOtherPrice();

            $refundApplyData['order_id'] = $this->order->id;

            //售后金额不能大于订单实付金额
            $price = bcadd($refundApplyData['apply_price'], ($refundApplyData['freight_price'] + $refundApplyData['install_price']),2);

            $refundApplyData['price'] =  $price;



        }
        $refundApplyData['order_type'] = $this->order->order_type;
         //退款超时时间
        $refundApplyData['timeout_time'] = Carbon::now()->addHours(24)->timestamp;
       // $refundApplyData['is_all'] = request()->input('is_all');
        $refundApplyData['service_status'] = request()->input('service_status')?:1;
        $threeDaysLater = $this->order->first_pay_time->copy()->addHours(72);
        $now = \Carbon\Carbon::now();
        if($this->order->pay_type_id == 16 && $now->lessThan($threeDaysLater)){
            $status = 1;
            $this->statusAfterChanged = $this->order->parent_id == 0?0:$status;
            $refundApplyData['progress_status'] = $status;
            $refundApplyData['supplier_pass_time'] = time();
        }


        $this->fill($this->finalFillAttribute($refundApplyData));

        $this->relatedPluginOrder()->handleAfterSales($this);//交给对应的订单做最后的业务处理


        $this->backWay()->init($this);  //走退货方式验证
    }

    protected function updateAfter()
    {

        $this->order->refund_id = $this->id;
        if (!$this->order->save()) {
            throw new AppException('订单退款状态更变失败');
        }

        if ($this->getRefundGoods()) {
            $this->createOrderGoodsRefundLog($this->getRefundGoods());
        }

        $this->setBackWay();    //保存退货方式内容
    }


    //订单商品申请退款金额和
    protected function getRefundApplyPrice()
    {

        /*$refund_price = collect($this->getRefundGoods())->sum('refund_price');
        if(in_array($this->order->order_type,[2,3,4])){
            return $this->order->price;
        }else if($this->getRequest()->input('is_all') == 1){
            return $this->order->price;
        }else if($this->getRequest()->input('is_all') == 2) {
            return $this->order->price;
        }else{
            return min($this->order->price, $refund_price);
        }*/
        if(in_array($this->order->order_type,[2,3,4,5])){
            return $this->order->price;
        }elseif($this->order->order_type == 1){
            return OrderStage::where('order_id',$this->order->id)->where('status',1)->sum('price');
        }

    }

    protected function getFreightPrice()
    {
        //return $this->getRequest()->input('freight_price',0);
        if($this->order->choose_logistics == 1 && $this->order->status>=1 && $this->order->orderStatus ==3){
            return $this->order->freight_price;
        }
        return 0;
    }

    protected function getInstallPrice()
    {
        //return $this->getRequest()->input('freight_price',0);
        if($this->order->choose_install == 1 && $this->order->status>=1 && $this->order->orderStatus ==3){
            return $this->order->install_price;
        }
        return 0;
    }

    protected function getOtherPrice()
    {
        return $this->getRequest()->input('other_price',0);
    }

    //最终退款金额,插件订单可以修改最终退款金额
    protected function finalAmount($price)
    {
        return min($this->order->price, $price);
    }

    protected function finalFillAttribute($data)
    {
        if (!isset($data['part_refund'])) {
            $data['part_refund'] = $this->getPartRefund();

        }

        if (!isset($data['refund_sn'])) {
            $data['refund_sn'] = RefundService::createOrderRN();

        }

        return $data;
    }

    public function getPartRefund()
    {

        return $this->relatedPluginOrder()->applyPratRefundStatus($this->getRefundGoods(),  $this->getRequest());


//        if ($this->getRequest()->input('refund_type') == RefundApply::REFUND_TYPE_EXCHANGE_GOODS) {
//            return 0;
//        }
//
//        //订单商品总数量
//        $orderGoodsNum = $this->order->orderGoods->sum('total');
//
//        $currentApplyNum = array_sum(array_column($this->getRefundGoods(), 'total'));
//
//
//        //一次性申请全部商品退款
//        if ($orderGoodsNum == $currentApplyNum) {
//            return 3; //申请全额退款
//        }
//
//        //todo 问题：已经换过一次货的商品，是否需要过滤掉换货售后记录的退款数量？
//        //订单已售后总数量，这里要不要过滤换货售后
//        $refundedNum = RefundGoodsLog::where('order_id',$this->order->id)
//            //->where('refund_type', '!=', self::REFUND_TYPE_EXCHANGE_GOODS)
//            ->sum('refund_total');
//
//        if ($orderGoodsNum == ($currentApplyNum + $refundedNum)) {
//            return 2; //最后一次退款
//        }
//
//        return 1; //部分退款


    }

    public function requestRefundGoods()
    {

        if (is_array($this->getRequest()->input('order_goods'))) {
            $refundGoods = $this->getRequest()->input('order_goods');
        } else {
            $refundGoods = json_decode($this->getRequest()->input('order_goods'), true);
        }

        return $refundGoods;
    }



    /*protected function getRefundGoods()
    {
        if (isset($refundGoods)) {
            return $this->refundGoods;
        }

        $refundGoods = $this->requestRefundGoods();

        if (!$refundGoods) {
            $refundGoods = $this->order->orderGoods->map(function (OrderGoods $orderGoods) {

                //已退款金额
                $refundedAmount = $orderGoods->manyRefundedGoodsLog->sum('refund_price');
                //已退款数量
                $refundedTotal = $orderGoods->getRefundTotal();

                return [
                    'id'=> $orderGoods->id,
                    'total'=> max($orderGoods->total - $refundedTotal,0),
                    'refund_price'=> max($orderGoods->payment_amount - $refundedAmount,0),
                ];
            })->values()->all();
        } else {

            $totalArrays = array_column($refundGoods,'total','id');
            $refundGoods = $this->order->orderGoods->whereIn('id',array_keys($totalArrays))->map(function ($orderGoods) use ($totalArrays) {
                $refund_price = ($orderGoods->payment_amount / $orderGoods->total) * $totalArrays[$orderGoods->id];
                return ['id'=> $orderGoods->id, 'total'=>$totalArrays[$orderGoods->id],'refund_price'=> $refund_price];
            })->values()->all();

        }
        if(!in_array($this->order->order_type,[2,3,4])){
            if (!$refundGoods) {
                throw new AppException('无商品可售后');
            }
        }


        return $this->refundGoods = $refundGoods;
    }*/

    /**
     * @return array
     * @throws AppException
     */
    protected function getRefundGoods()
    {
        if (isset($refundGoods)) {
            return $this->refundGoods;
        }

        $refundGoods = $this->requestRefundGoods();

        if (!$refundGoods) {
            $refundGoods = $this->order->orderGoods->map(function (OrderGoods $orderGoods) {
                //已退款金额
                $refundedAmount = $orderGoods->manyRefundedGoodsLog->sum('refund_price');
                //已退款数量
                $refundedTotal = $orderGoods->getRefundTotal();

                return [
                    'id'=> $orderGoods->id,
                    'total'=> max($orderGoods->total - $refundedTotal,0),
                    'refund_price'=> max($orderGoods->payment_amount - $refundedAmount,0),
                ];
            })->values()->all();
        } else {
            $filteredGoods = array_filter($refundGoods, function($item) {
                return $item['order_id'] == $this->order->id;
            });
            $totalArrays = array_column($filteredGoods,'total','id');
            $refundGoods = $this->order->orderGoods->whereIn('id',array_keys($totalArrays))->map(function ($orderGoods) use ($totalArrays) {
                $refund_price = ($orderGoods->payment_amount / $orderGoods->total) * $totalArrays[$orderGoods->id];
                return ['id'=> $orderGoods->id, 'total'=>$totalArrays[$orderGoods->id],'refund_price'=> $refund_price];
            })->values()->all();

        }
        /*if(!in_array($this->order->order_type,[2,3,4])){
            if (!$refundGoods) {
                throw new AppException('无商品可售后');
            }
        }*/




        return $this->refundGoods = $refundGoods;
    }

    protected function beforeEventClass()
    {
        return new BeforeRefundApplyEvent($this);
    }

    //售后申请更新后监听事件
    protected function afterEventClass()
    {
        return new OrderRefundApplyEvent($this);
    }



    protected function writeLog()
    {
        $detail = [
            '售后类型：'. $this->getRefundTypeName()[$this->refund_type],
            $this->refund_type == static::REFUND_TYPE_EXCHANGE_GOODS ? '': '退款金额：'.$this->price,
            $this->freight_price?'运费:'. $this->freight_price :'',
            $this->other_price?'其他费用:'. $this->other_price :'',
            '售后原因：'.$this->reason,
            '说明：'.$this->content,
        ];
        $processLog = RefundProcessLog::logInstance($this, RefundProcessLog::OPERATOR_MEMBER);
        $processLog->setAttribute('operate_type', RefundProcessLog::OPERATE_APPLY);
        
        $processLog->saveLog($detail,request()->input());
        $RefundJob = new RefundJob($this->uniacid, 1, $this->id);
        $RefundJob->handle();
        //dispatch(new RefundJob($this->uniacid, 1, $this->id));
    }

    protected function sendMessage()
    {
        //通知买家
        RefundMessageService::applyRefundNotice($this);
        RefundMessageService::applyRefundNoticeBuyer($this);

        //【系統消息通知】
        (new SystemMsgService())->applyRefundNotice($this);

        if (app('plugins')->isEnabled('instation-message')) {
            //开启了站内消息插件
            event(new \Yunshop\InstationMessage\event\OrderRefundApplyEvent($this));
        }
    }
}