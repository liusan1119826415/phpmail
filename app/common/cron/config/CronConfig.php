<?php

namespace app\common\cron\config;


use app\backend\modules\goods\services\ExtractImageVector;
use app\common\cron\ClearWithholdStock;
use app\common\cron\CollectHost;
use app\common\cron\CouponExpired;
use app\common\cron\CouponExpireNotice;
use app\common\cron\CouponSend;
use app\common\cron\CouponSysMessage;
use app\common\cron\DaemonQueue;
use app\common\cron\DisableUserAccount;
use app\common\cron\GoodsDefaultComment;
use app\common\cron\HeartbeatStatusLog;
use app\common\cron\LimitBuy;
use app\common\cron\LklIntegrationPayWithdraw;
use app\common\cron\MemberLevelValidity;
use app\common\cron\MemberLower;
use app\common\cron\MonthCouponSend;
use app\common\cron\MonthParentRewardPoint;
use app\common\cron\OrderCouponSend;
use app\common\cron\PayExceptionRefund;
use app\common\cron\PhoneAttributions;
use app\common\cron\PointQueue;
use app\common\cron\PointToLoveQueue;
use app\common\cron\ShopOrderStatistics;
use app\common\cron\SmsBalance;
use app\common\cron\SystemMsgDestroy;
use app\common\cron\UpdateCache;
use app\common\cron\UpperLowerShelves;
use app\common\cron\WechatWithdraw;
use app\common\models\UniAccount;
use app\frontend\modules\order\services\OrderService;
use Illuminate\Support\Facades\DB;

class CronConfig
{
    public function addCron()
    {
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('CollectHost', rand(0, 59) . " " . rand(0, 12) . ' * * *', function () {
                (new CollectHost())->handle();
                return;
            });
        });

        /**
         * 积分每月赠送
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('PointQueue', '*/30 * * * *', function () {
                (new PointQueue())->handle();
            });
        });

        /**
         * 积分自动转入爱心值
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('PointToLoveQueue', '*/10 * * * *', function () {
                (new PointToLoveQueue())->handle();
            });
        });

        /**
         * 每月初定时更新缓存
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('Update-cache', '0 0 1 * *', function () {
                (new UpdateCache())->handle();
                return;
            });
        });

        /**
         * 商品购买每月赠送优惠券
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('MonthParentRewardPoint', '*/13 * * * *', function () {
                (new MonthParentRewardPoint())->handle();
                return;
            });
        });

        /**
         * 商城订单统计
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('OrderStatistics', '0 1 * * *', function () {
                (new ShopOrderStatistics())->handle();
                return;
            });
        });

        /**
         * 会员下线统计定时任务
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('MemberLower', '0 1 * * *', function () {
                (new MemberLower())->handle();
                return;
            });
        });

        /**
         * 微信v3提现
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('WechatWithdraw', '*/5 * * * *', function () {
                (new WechatWithdraw())->handle();
                return;
            });
        });

        \Event::listen('cron.collectJobs', function () {
            \Cron::add('PhoneAttributions', '0 1 * * *', function () {
                (new PhoneAttributions())->handle();
                return;
            });
        });

        /**
         * 余额短信提醒定时任务
         */
        \Event::listen('cron.collectJobs', function () {
            foreach (UniAccount::getEnable() as $uniAccount) {
                \Setting::$uniqueAccountId = \YunShop::app()->uniacid = $uniAccount->uniacid;
                if (!\Setting::get('finance.balance.sms_send')) continue;
                $cron_time = (string)\Setting::get('finance.balance.sms_hour') ?: '0';
                \Cron::add('SmsMessage' . $uniAccount->uniacid, '0 ' . $cron_time . ' * * *', function () use ($uniAccount) {
                    (new SmsBalance())->handle($uniAccount->uniacid);
                });
            }
        });

        /**
         * 商品默认好评
         */
        \Event::listen('cron.collectJobs', function () {
            //该功能对时效性要求不高，选个任务少的时间段执行即可
            \Cron::add('GoodsDefaultComment', '*/30 3 * * *', function () {
                (new GoodsDefaultComment())->handle();
            });
        });

        /**
         * 商品上下架
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('UpperLowerShelves', '*/5 * * * *', function () {
                (new UpperLowerShelves())->handle();
            });
        });

        /**
         * 商品限时购
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('Limit-buy', '*/10 * * * *', function () {
                (new LimitBuy())->handle();
                return;
            });
        });

        /**
         * 定时任务、队列情况记录
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('HeartbeatStatusLog', '*/1 * * * *', function () {
                (new HeartbeatStatusLog())->handle();
            });
        });

        /**
         * 删除系统消息
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('SystemMsgDestroy', '0 1 * * *', function () {//每天1点删
                (new SystemMsgDestroy())->handle();
            });
        });

        /**
         * 每分钟清除超时的预扣库存记录
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add("ClearWithholdStock", '*/1 * * * *', function () {
                (new ClearWithholdStock())->handle();
            });
        });

        /**
         * 订单自动任务
         */
        \Event::listen('cron.collectJobs', function () {
            // 订单支付回调时间超过订单关闭时间，报错误自动退款
            \Cron::add('PayExceptionRefund', '*/30 * * * *', function () {
                (new PayExceptionRefund())->handle();
            });

            \Cron::add("DaemonQueue", '*/1 * * * *', function () {
                (new DaemonQueue())->handle();
            });

            // 虚拟订单修复
            \Log::info("--虚拟订单修复--");
            \Cron::add("VirtualOrderFix", '* */1 * * *', function () {
                $orders = DB::table('yz_order')->whereIn('status', [1, 2])->where('is_virtual', 1)
                    ->where('refund_id', 0)
                    ->where('is_pending', 0)
                    ->where('pay_time','<',time() - 300)//支付时间超过5分钟的执行，避免与虚拟订单本身的自动发|收货冲突，导致重复执行同步发|收货事件
                    ->get();
                // 所有超时未收货的订单,遍历执行收货
                $orders->each(function ($order) {
                    OrderService::fixVirtualOrder($order);

                });
                // todo 使用队列执行
            });

            $uniAccount = UniAccount::getEnable();
            // 订单自动发货执行间隔时间 默认60分钟
            \Cron::add("OrderSend", '* * * * *', function () use ($uniAccount) {
                foreach ($uniAccount as $u) {
                    \Setting::$uniqueAccountId = \YunShop::app()->uniacid = $accountId = $u->uniacid;
                    if ((int)\Setting::get('shop.trade.send')) {
                        \Log::info("--{$u->uniacid}订单自动发货开始执行--");
                        OrderService::autoSend($accountId);
                    }
                }
            });

            // 订单自动收货执行间隔时间 默认60分钟
            $receive_min = 5;//(int)\Setting::get('shop.trade.receive_time') ?: 60;
            \Cron::add("OrderReceive", '*/' . $receive_min . ' * * * *', function () use ($uniAccount) {
                foreach ($uniAccount as $u) {
                    \Setting::$uniqueAccountId = \YunShop::app()->uniacid = $accountId = $u->uniacid;
                    if ((int)\Setting::get('shop.trade.receive')) {
                        \Log::info("--{$u->uniacid}订单自动完成开始执行--");
                        // 所有超时未收货的订单,遍历执行收货
                        OrderService::autoReceive($accountId);
                        // todo 使用队列执行
                        //售后类型为换货,且状态为重新收货/商家发货的订单也可以自动收货  #27753
                        OrderService::autoReceiveExchangeRefund($accountId);
                    }
                }
            });

            // 订单自动关闭执行间隔时间 默认60分钟
            $close_min = 5;//(int)\Setting::get('shop.trade.close_order_time') ?: 59;
            \Cron::add("OrderClose", '*/' . $close_min . ' * * * * ', function () use ($uniAccount) {
                foreach ($uniAccount as $u) {
                    \Setting::$uniqueAccountId = \YunShop::app()->uniacid = $accountId = $u->uniacid;
                    if ((int)\Setting::get('shop.trade.close_order_days')) {
                        \Log::info("--{$u->uniacid}订单自动关闭开始执行--");
                        // 所有超时付款的订单,遍历执行关闭
                        OrderService::autoClose($accountId);
                    }
                }
            });
        });

        /**
         * 优惠券定时任务
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('CouponExpired', '*/10 * * * *', function () {
                (new CouponExpired())->handle();
            });
            \Cron::add('CouponExpiredNear', '0 2 * * *', function () {
                (new CouponExpired())->handle(1);
            });
            \Cron::add('CouponSysMessage', '0 2 * * *', function () {
                (new CouponSysMessage())->handle();
            });

            /**
             * 购买商品按月发放优惠券
             */
            \Cron::add('MonthCouponSend', '0 1 1 * *', function() {
                (new MonthCouponSend())->handle();
            });

            /**
             * 购买商品订单完成发放优惠券
             */
            \Cron::add('OrderCouponSend', '*/1 * * * *', function() {
                (new OrderCouponSend())->handle();
            });

            \Cron::add('CouponExpireNotice', '*/10 * * * *', function () {
                (new CouponExpireNotice())->handle();
            });
            \Cron::add('CouponSend', '*/10 * * * *', function () {
                (new CouponSend())->handle();
            });
        });

        /**
         * 会员等级过期
         */
        \Event::listen('cron.collectJobs', function () {
            \Cron::add('MemberLevelValidity', '*/10 * * * *', function () {
                (new MemberLevelValidity())->handle();
            });
        });

        \Event::listen('cron.collectJobs', function () {
            \Cron::add('DisableUserAccount', '0 * * * *', function () {
                (new DisableUserAccount())->handle();
            });
        });

        \Event::listen('cron.collectJobs', function () {
            \Cron::add('LklIntegrationPayWithdraw', '*/30 * * * *', function () {
                (new LklIntegrationPayWithdraw())->handle();
            });
        });

        /**
         * 定时每天00点提取商品向量
         *
         */

        \Event::listen('cron.collectJobs', function () {
            \Cron::add('extractImageVector', '30 0 * * *', function () {
                (new ExtractImageVector())->handle();
            });
        });



    }
}