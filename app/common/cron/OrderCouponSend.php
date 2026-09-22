<?php
/**
 * Created by PhpStorm.
 * User: CGOD
 * Date: 2019/12/18
 * Time: 16:01
 */

namespace app\common\cron;


use app\frontend\modules\coupon\services\CronSendService;
use Illuminate\Foundation\Bus\DispatchesJobs;
use app\common\models\Order;
use app\common\facades\Setting;
use app\common\models\UniAccount;
use app\common\models\coupon\OrderGoodsCoupon;

//商品购买订单完成赠送优惠券
class OrderCouponSend
{
    use DispatchesJobs;

    public function handle()
    {
        set_time_limit(0);
        $uniAccount = UniAccount::getEnable() ?: [];
        foreach ($uniAccount as $u) {
            Setting::$uniqueAccountId = \YunShop::app()->uniacid = $u->uniacid;
            $this->orderCouponSend();
            $this->orderPaidCouponSend();
            $this->orderPaidCouponIntervalSend();
            $this->orderIntervalEffectiveType();
        }
    }

    public function orderCouponSend()
    {
        $records = OrderGoodsCoupon::uniacid()
            ->where(['send_type'=>OrderGoodsCoupon::ORDER_TYPE,'status'=>OrderGoodsCoupon::WAIT_STATUS])
            ->whereIn('order_goods_id',function ($query) {
                $query->select('id')
                    ->from('yz_order_goods')
                    ->whereIn('order_id',function ($query) {
                        $query->select('id')
                            ->from('yz_order')
                            ->where('uniacid',\YunShop::app()->uniacid)
                            ->where('status',Order::COMPLETE);
                    });
            })
            ->get();
        if($records->isEmpty()) {
            return;
        }
        foreach ($records as $record) {
            $numReason = $record->num_reason?$record->num_reason.'||':'';
            (new CronSendService($record,$numReason,1))->sendCoupon();
        }
    }

    public function orderPaidCouponSend()
    {
        $records = OrderGoodsCoupon::uniacid()
            ->where(['send_type'=>OrderGoodsCoupon::ORDER_PAID_TYPE,'status'=>OrderGoodsCoupon::WAIT_STATUS])
            ->whereIn('order_goods_id',function ($query) {
                $query->select('id')
                    ->from('yz_order_goods')
                    ->whereIn('order_id',function ($query) {
                        $query->select('id')
                            ->from('yz_order')
                            ->where('uniacid',\YunShop::app()->uniacid)
                            ->whereIn('status',[Order::WAIT_SEND,Order::WAIT_RECEIVE,Order::COMPLETE]);
                    });
            })
            ->get();
        if($records->isEmpty()) {
            return;
        }
        foreach ($records as $record) {
            $numReason = $record->num_reason?$record->num_reason.'||':'';
            (new CronSendService($record,$numReason,1))->sendCoupon();
        }
    }

    public function orderPaidCouponIntervalSend()
    {
        $records = OrderGoodsCoupon::uniacid()
            ->where(['send_type'=>OrderGoodsCoupon::ORDER_PAID_INTERVAL_TYPE,'status'=>OrderGoodsCoupon::WAIT_STATUS])
            ->where('next_send_time','<=',time())
            ->whereIn('order_goods_id',function ($query) {
                $query->select('id')
                    ->from('yz_order_goods')
                    ->whereIn('order_id',function ($query) {
                        $query->select('id')
                            ->from('yz_order')
                            ->where('uniacid',\YunShop::app()->uniacid)
                            ->whereIn('status',[Order::WAIT_SEND,Order::WAIT_RECEIVE,Order::COMPLETE]);
                    });
            })
            ->get();
        if($records->isEmpty()) {
            return;
        }
        foreach ($records as $record) {
            $numReason = $record->num_reason?$record->num_reason.'||':'';
            (new CronSendService($record,$numReason,4))->sendCoupon();
        }
    }

    public function orderIntervalEffectiveType()
    {
        $records = OrderGoodsCoupon::uniacid()
            ->where(['send_type'=>OrderGoodsCoupon::ORDER_INTERVAL_EFFECTIVE_TYPE,'status'=>OrderGoodsCoupon::WAIT_STATUS])
            ->whereIn('order_goods_id',function ($query) {
                $query->select('id')
                    ->from('yz_order_goods')
                    ->whereIn('order_id',function ($query) {
                        $query->select('id')
                            ->from('yz_order')
                            ->where('uniacid',\YunShop::app()->uniacid)
                            ->whereIn('status',[Order::WAIT_SEND,Order::WAIT_RECEIVE,Order::COMPLETE]);
                    });
            })
            ->get();
        if($records->isEmpty()) {
            return;
        }
        foreach ($records as $record) {
            $numReason = $record->num_reason?$record->num_reason.'||':'';
            (new CronSendService($record,$numReason,5))->sendCoupon();
        }
    }
}