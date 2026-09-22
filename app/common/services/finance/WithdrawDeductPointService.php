<?php

namespace app\common\services\finance;

use app\common\events\withdraw\BalanceWithdrawRejectEvent;
use app\common\events\withdraw\WithdrawBalanceAppliedEvent;
use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\models\finance\PointLog;
use Illuminate\Support\Facades\DB;

class WithdrawDeductPointService extends DeductPointService
{

    protected $balanceSet;
    protected $withdraw;

    public function __construct()
    {
        $this->balanceSet = Setting::get('withdraw.balance');
        $this->deductBalance = $this->balanceSet['deduct_balance'] ?: 1;
        $this->deductPoint = $this->balanceSet['deduct_point'] ?: 1;
    }


    /**
     * 提现余额扣除积分
     * @param WithdrawBalanceAppliedEvent $event
     * @return void
     */
    public function deductPoint(WithdrawBalanceAppliedEvent $event)
    {
        if (!$this->balanceSet['deduct_status']) {
            \Log::debug('--余额提现扣除积分--未开启');
            return;
        }

        $this->withdraw = $event->getWithdrawModel();

        $need_deduct_point = $this->getNeedDeductPoint($this->withdraw->amounts);

        \Log::debug("--余额提现扣除积分--开始{$this->withdraw->id}", [
            'deduct_balance' => $this->deductBalance,
            'deduct_point' => $this->deductPoint,
            '扣除积分' => $need_deduct_point,
        ]);

        if ($need_deduct_point > $this->withdraw->member->credit1) {
            throw new ShopException("转让需扣除" . $need_deduct_point . self::getPointName() . ",您的" . self::getPointName() . "不足");
        }

        try {
            $remark = "提现" . self::getBalanceName() . "{$this->withdraw->amounts}，扣除{$need_deduct_point}" . self::getPointName() . "，
            扣除比例{$this->deductBalance}:{$this->deductPoint}。
            提现记录ID【{$this->withdraw->id}】";
            $this->changePoint($need_deduct_point, PointService::BALANCE_WITHDRAW_DEDUCT_PONINT, $remark, PointService::POINT_INCOME_LOSE);
        } catch (ShopException $e) {
            \Log::debug('--余额提现扣除积分--扣除报错', $e->getMessage());
            throw $e;
        }

    }

    /**
     * 提现驳回返还积分
     * @param BalanceWithdrawRejectEvent $event
     * @return void
     */
    public function refundPoint(BalanceWithdrawRejectEvent $event)
    {
        if (!$this->balanceSet['deduct_status']) {
            \Log::debug('--余额提现扣除积分返还--未开启');
            return;
        }
        $this->withdraw = $event->getWithdrawModel();
        \Log::debug("--余额提现扣除积分返还--开始{$this->withdraw->id}");

        $condition = [
            'point_mode' => PointService::BALANCE_WITHDRAW_DEDUCT_PONINT,
            'relation_id' => $this->withdraw->id,
            'point_income_type' => PointService::POINT_INCOME_LOSE,
            'member_id' => $this->withdraw->member_id,
        ];

        $point_log = PointLog::uniacid()->where($condition)->first();

        if (!$point_log) {
            \Log::debug('--余额提现扣除积分返还--没有查询到返还记录', $condition);
            return;
        }

        try {
            $point = abs($point_log->point);
            $remark = "提现" . self::getBalanceName() . "驳回，返还{$point}" . self::getPointName() . "。提现记录ID【{$this->withdraw->id}】";
            $this->changePoint($point, PointService::REFUND_WITHDRAW_DEDUCT_PONINT, $remark, PointService::POINT_INCOME_GET);
        } catch (ShopException $e) {
            \Log::debug('--余额提现扣除积分返还--返还报错', $e->getMessage());
            throw $e;
        }
    }

    protected function changePoint($point, string $pointMode, string $remark, int $pointIncomeType, $memberId = 0, $relationId = 0)
    {
        $memberId = $this->withdraw->member_id;
        $relationId = $this->withdraw->id;
        return parent::changePoint($point, $pointMode, $remark, $pointIncomeType, $memberId, $relationId);
    }

}
