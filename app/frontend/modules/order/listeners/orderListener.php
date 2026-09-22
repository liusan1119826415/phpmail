<?php

namespace app\frontend\modules\order\listeners;

use app\backend\modules\job\models\FailedJob;
use app\common\events\order\AfterOrderCanceledEvent;
use app\common\events\order\AfterOrderCancelSentEvent;
use app\common\events\order\AfterOrderCreatedEvent;
use app\common\events\order\AfterOrderPackageSentEvent;
use app\common\events\order\AfterOrderPaidEvent;
use app\common\events\order\AfterOrderReceivedEvent;
use app\common\events\order\AfterOrderSentEvent;
use app\common\facades\Setting;
use app\common\listeners\order\FirstOrderListener;
use app\common\models\DispatchType;
use app\common\models\Order;
use app\common\models\UniAccount;
use app\common\modules\pcnotice\Template;
use app\common\services\SystemMsgService;
use app\framework\Support\Facades\Log;
use app\frontend\modules\order\services\MessageService;
use app\frontend\modules\order\services\MiniMessageService;
use app\frontend\modules\order\services\OrderService;
use app\frontend\modules\order\services\OtherMessageService;
use app\frontend\modules\order\services\SmsMessageService;
use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Created by PhpStorm.
 * User: shenyang
 * Date: 2017/6/5
 * Time: 下午8:53
 */
class orderListener
{
    public function onCreated(AfterOrderCreatedEvent $event)
    {
        $order = Order::find($event->getOrderModel()->id);
        /*if($order->notSendMessage()) {
            return;
        }*/
        //(new MessageService($order))->created();

        //(new OtherMessageService($order))->created();
        if($order->parent_id == 0){
            $option['related_id'] = $order->id;
            app('notification')->send(
                $order->uid, // 用户ID
                Template::ORDER,
                Template::ORDER_TYPE[$order->order_type],
                getNoticeTitle($order->order_type,"SUBMIT"),
                ['order_no' => $order->order_sn,'amount'=>$order->goods_price],
                $option

            );
        }

    }




    public function onPaid(AfterOrderPaidEvent $event)
    {
        $order = Order::find($event->getOrderModel()->id);

       /* if($order->notSendMessage()) {
            return;
        }
        (new MiniMessageService($order))->received();
        (new MessageService($order))->paid();
        (new OtherMessageService($order))->paid();*/
        if($order->parent_id == 0) {
            (new SystemMsgService())->paid($order);

            $option['related_id'] = $order->id;
            if($order->order_type == 1 && $order->hasOneOrderPay->payment_stage == 1){
                 $code = "DEPOSIT_PAID";
            }elseif($order->order_type == 1 && $order->hasOneOrderPay->payment_stage == 2){
                 $code = "FINAL_PAYMENT";
            }else{
                $code = "PAYMENT";
            }
            app('notification')->send(
                $order->uid, // 用户ID
                Template::ORDER,
                Template::ORDER_TYPE[$order->order_type],
                getNoticeTitle($order->order_type, $code),
                ['order_no' => $order->order_sn,'amount'=>$order->price],
                $option

            );
        }
        // todo 预扣库存转化为实际库存
    }

    public function onCanceled(AfterOrderCanceledEvent $event)
    {
        $order = Order::find($event->getOrderModel()->id);
        if($order->parent_id == 0){

            $option['related_id'] = $order->id;
            app('notification')->send(
                $order->uid, // 用户ID
                Template::ORDER,
                Template::ORDER_TYPE[$order->order_type],
                getNoticeTitle($order->order_type,"CLOSE"),
                ['order_no' => $order->order_sn],
                $option

            );
        }

        /*if($order->notSendMessage()|| in_array($order->order_type,[1,2,3,4])) {
            return;
        }
        (new MessageService($order))->canceled();*/
    }

    public function onSent(AfterOrderSentEvent $event)
    {
        $order = Order::find($event->getOrderModel()->id);
        if($order->notSendMessage()) {
            return;
        }

        //门店自提 发货不发送通知
        if ($order->dispatch_type_id == DispatchType::SELF_DELIVERY) {
            return;
        }

        if (!$order->isVirtual()) {
            //多包裹发货微信通知走包裹发送事件 AfterOrderPackageSentEvent
            if(!$order->is_all_send_goods){
                (new MessageService($order))->sent();
            }
            (new OtherMessageService($order))->sent();
            (new SmsMessageService($order))->sent();
        }
    }

    public function onPackageSent(AfterOrderPackageSentEvent $event)
    {
        $order = Order::find($event->getOrderModel()->id);
        if($order->notSendMessage()) {
            return;
        }

        //门店自提 发货不发送通知
        if ($order->dispatch_type_id == DispatchType::SELF_DELIVERY) {
            return;
        }

        if (!$order->isVirtual()) {
            (new MessageService($order))->packageSent();
        }
    }

    public function onCanceledSent(AfterOrderCancelSentEvent $event)
    {
        $order = Order::find($event->getOrderModel()->id);

        if ($order) {
            $order->delOrderSent();
        }

    }

    public function onReceived(AfterOrderReceivedEvent $event)
    {
        $order = Order::find($event->getOrderModel()->id);
       /* if($order->notSendMessage()) {
            return;
        }
        (new MessageService($order))->received();
        (new OtherMessageService($order))->received();
        (new SystemMsgService($order->uniacid))->received($order);*/

        if($order->parent_id == 0) {
            (new SystemMsgService($order->uniacid))->received($order);

            $option['related_id'] = $order->id;
            $code = "COMPLETED";
            app('notification')->send(
                $order->uid, // 用户ID
                Template::ORDER,
                Template::ORDER_TYPE[$order->order_type],
                getNoticeTitle($order->order_type, $code),
                ['order_no' => $order->order_sn],
                $option

            );
        }
    }

    public function subscribe(Dispatcher $events)
    {
        $events->listen(AfterOrderCreatedEvent::class, self::class . '@onCreated');

        // 首单
        //$events->listen(AfterOrderPaidImmediatelyEvent::class, FirstOrderListener::class . '@handle');
        $events->listen(AfterOrderPaidEvent::class, FirstOrderListener::class . '@handle');
        // 订单取消,取消首单标识
        $events->listen(AfterOrderCanceledEvent::class, FirstOrderListener::class . '@cancel');

        $events->listen(AfterOrderPaidEvent::class, self::class . '@onPaid');
        $events->listen(AfterOrderCanceledEvent::class, self::class . '@onCanceled');
        $events->listen(AfterOrderSentEvent::class, self::class . '@onSent');
        $events->listen(AfterOrderCancelSentEvent::class, self::class . '@onCanceledSent'); //订单取消发货
        $events->listen(AfterOrderReceivedEvent::class, self::class . '@onReceived');
        $events->listen(AfterOrderPaidEvent::class, \app\common\listeners\member\AfterOrderPaidListener::class . '@handle', 1);
        $events->listen(AfterOrderReceivedEvent::class, \app\common\listeners\member\AfterOrderReceivedListener::class . '@handle', 1);
        $events->listen(AfterOrderPackageSentEvent::class, self::class . '@onPackageSent');
        $events->listen(TransactionCommitted::class, function (TransactionCommitted $event) {
            app(\Illuminate\Contracts\Bus\Dispatcher::class)->dbTransactionCommitted($event);
        });
        $events->listen(TransactionRolledBack::class, function ($event) {
            app(\Illuminate\Contracts\Bus\Dispatcher::class)->dbTransactionRollBack($event);
        });
		$events->listen(TransactionBeginning::class, function ($event) {
			app(\Illuminate\Contracts\Bus\Dispatcher::class)->dbTransactionBeginning($event);
		});
    }
}
