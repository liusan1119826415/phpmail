<?php

namespace app\common\listeners\point;

use app\common\events\withdraw\BeforeWithdrawManyApplyEvent;
use app\common\events\withdraw\WithdrawAppliedEvent;
use app\common\events\withdraw\WithdrawAuditedEvent;
use app\common\events\withdraw\WithdrawRebutAuditEvent;
use app\common\exceptions\AppException;
use app\common\facades\Setting;
use app\common\models\finance\PointLog;
use app\common\models\Member;
use app\common\services\finance\PointService;

class WithdrawPointDeductListener
{
    public function subscribe($events)
    {
        /**
         * 申请提现前验证积分是否充足
         */
        $events->listen(
            BeforeWithdrawManyApplyEvent::class,
            WithdrawPointDeductListener::class . '@verifyPoint'
        );
        /**
         * 申请提现后扣除积分
         */
        $events->listen(
            WithdrawAppliedEvent::class,
            WithdrawPointDeductListener::class . '@changePoint'
        );

        /**
         * 监听后台审核事件，如全部驳回/无效返还之前提现扣除积分
         */
        $events->listen(
            WithdrawAuditedEvent::class,
            WithdrawPointDeductListener::class . '@auditedReturnPoint'
        );
    }

    public function verifyPoint(BeforeWithdrawManyApplyEvent $event)
    {
        $set = Setting::get('point.set');
        $point_name = \Setting::get('shop.shop')['credit1'] ?: '积分';

        if ($set['income_withdraw_deduct_scale']) {
            $member = Member::getMemberById($event->getUid());
            $rate = $set['income_withdraw_deduct_scale_point'];
            $amount = $event->getAmount();
            //排除收入不扣除爱心值

            $deduction = proportionMath($amount, $rate);//需要扣除的积分

            if (bccomp($deduction, $member['credit1'], 2) == 1) {
                throw new AppException('提现需扣除' . $deduction . $point_name . ',您的' . $point_name . '不足');
            }
        }
    }

    public function changePoint(WithdrawAppliedEvent $event)
    {
        $withdraw = $event->getWithdrawModel();
        $set = Setting::get('point.set');

        if ($set['income_withdraw_deduct_scale']) {
            $rate = $set['income_withdraw_deduct_scale_point'];
            //排除收入不扣除爱心值

            $deduction = proportionMath($withdraw['amounts'], $rate);//需要扣除的积分
            if ($deduction < 0) {
                return;
            }
            $pointData = array(
                'point_income_type' => PointService::POINT_INCOME_LOSE,
                'member_id'         => $withdraw['member_id'],
                'point_mode'        => 180,
                'point'             => bcsub(0, $deduction, 2),
                'remark'            => '收入提现扣除' . $deduction . ' 个',
            );

            $pointService = new PointService($pointData);
            $pointService->changePoint($withdraw->id);
        }
    }

    public function auditedReturnPoint(WithdrawAuditedEvent $event)
    {
        $withdraw = $event->getWithdrawModel();
        if (!in_array($withdraw->status, [-1, 3])) {
            return;
        }

        if (PointLog::uniacid()->where('point_mode', 188)->where('member_id', $withdraw->member_id)->where(
            'relation_id',
            $withdraw->id
        )->first()) {
            return;
        }

        $pointLog = $this->getChangePointLog($withdraw);
        if (!$pointLog) {
            return;
        }
        $change = max($pointLog->point, bcmul(-1, $pointLog->point, 2));
        $pointData = array(
            'point_income_type' => PointService::POINT_INCOME_GET,
            'member_id'         => $withdraw->member_id,
            'point_mode'        => 188,
            'point'             => $change,
            'remark'            => '收入提现(' . $withdraw->withdraw_sn . ')失效|驳回，返还' . $change . ' 个',
        );

        $pointService = new PointService($pointData);
        $pointService->changePoint($withdraw->id);
    }

    private function getChangePointLog($withdraw)
    {
        return PointLog::uniacid()
            ->where('point_mode', 180)
            ->where('member_id', $withdraw->member_id)
            ->where('relation_id', $withdraw->id)
            ->first();
    }

}