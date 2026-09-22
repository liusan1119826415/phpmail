<?php

namespace app\common\services\finance;

use app\common\events\member\MemberBalanceChangeEvent;
use app\common\exceptions\ShopException;
use app\common\facades\Setting;
use app\common\models\Member;
use app\common\services\credit\ConstService;
use Illuminate\Support\Facades\DB;

class TransferDeductPointService extends DeductPointService
{

    /**
     * @var MemberBalanceChangeEvent
     */
    protected $event;

    public function __construct()
    {
        $this->balanceSet = Setting::get('finance.balance');
        $this->deductBalance = $this->balanceSet['deduct_balance'] ?: 1;
        $this->deductPoint = $this->balanceSet['deduct_point'] ?: 1;
    }

    /**
     * 验证
     * @return bool
     */
    protected function validate(): bool
    {
        $record = $this->event->getRecordData();
        if (!$this->balanceSet['deduct_status']) {
            return false;
        }

        //不是转账
        if (ConstService::SOURCE_TRANSFER != $this->event->getSource()) {
            return false;
        }

        //转账的操作者不是同一人
        if ($record['operator_id'] != $record['member_id']) {
            return false;
        }

        return true;
    }

    /**
     * 扣除积分
     * @param MemberBalanceChangeEvent $event
     * @return void
     */
    public function deductPoint(MemberBalanceChangeEvent $event)
    {
        $this->event = $event;

        if (!$this->validate()) {
            return;
        }
        $change_money = abs($event->getChangeValue());
        $need_deduct_point = $this->getNeedDeductPoint($change_money);
        $record = $event->getRecordData();
        $credit1 = Member::where('uid', $event->getMember()->uid)->value('credit1') ?: 0;

        if ($need_deduct_point > $credit1) {
            throw new ShopException("转让需扣除" . $need_deduct_point . self::getPointName() . ",您的" . self::getPointName() . "不足");
        }

        try {
            $remark = self::getBalanceName() . "转账{$change_money},
            扣除{$need_deduct_point}" . self::getPointName() . ",
            扣除比例{$this->deductBalance}:{$this->deductPoint}.
            转账记录编号【{$record['serial_number']}】
            ";
            $this->changePoint($need_deduct_point, PointService::BALANCE_TRANSFER_DEDUCT_PONINT, $remark, PointService::POINT_INCOME_LOSE);
        } catch (ShopException $e) {
            \Log::debug('--余额转账扣除积分--失败', [
                'uid' => $event->getMember()->uid,
                'msg' => $e->getMessage(),
                'transfer_balance_sn' => $record['serial_number'],
            ]);
            throw $e;
        }

    }

    protected function changePoint($point, string $pointMode, string $remark, int $pointIncomeType, $memberId = 0, $relationId = 0)
    {
        $memberId = $this->event->getMember()->uid;
        $relationId = $this->event->getRecordData()['serial_number'];
        return parent::changePoint($point, $pointMode, $remark, $pointIncomeType, $memberId, $relationId);
    }

}
