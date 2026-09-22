<?php

namespace app\common\services\finance;

use app\common\events\member\MemberBalanceChangeEvent;
use app\common\exceptions\AppException;
use app\common\facades\Setting;
use app\common\services\credit\ConstService;

class TransferIncreasePointService
{


    public function __construct()
    {

    }

    public function getNeedPoint($money = 0)
    {
        // (提现余额 / 比例余额) * 比例积分 = 需要扣除的积分
        $point = bcmul(bcdiv($money, $this->increaseBalance, 2), $this->increasePoint, 2);
        return floatval($point) ?: 0;
    }

    public function rewardPoint(MemberBalanceChangeEvent $event)
    {

        $record = $event->getRecordData();
        if (
            $event->getSource() != ConstService::SOURCE_TRANSFER ||
            $record['operator_id'] != $event->getMember()->uid ||
            $record['operator'] != ConstService::OPERATOR_MEMBER ||
            !Setting::get('finance.balance.transfer_deduct_point')
        ) {
            return;
        }


        $change_money = abs($event->getChangeValue());

        $ratio = Setting::get('finance.balance.transfer_deduct_ratio');

        if ($ratio <= 0){
            return;
        }


        /**
         * 原转账金额
         */
        $origin_money = bcdiv($change_money, bcadd(1, bcdiv($ratio, 100, 2), 2), 2);


        /**
         * 手续费兼赠送积分数量
         */
        $point = bcsub($change_money, $origin_money, 2);


        if ($point <= 0){
            return;
        }


        try {
            $remark = self::getBalanceName() . "转账{$origin_money},赠送{$point}" . self::getPointName() . ",赠送比例1:1转账记录编号【{$record['serial_number']}】";

            $change_data = [
                'point_mode' => 212,
                'member_id' => $event->getMember()->uid,
                'point' => $point,
                'remark' => $remark,
                'point_income_type' => PointService::POINT_INCOME_GET,
                'relation_id' => $record['serial_number']
            ];
            return (new PointService($change_data))->changePoint();
        } catch (\Exception $e) {
            \Log::debug('--余额转账赠送积分--失败', [
                'uid' => $event->getMember()->uid,
                'msg' => $e->getMessage(),
                'transfer_balance_sn' => $record['serial_number'],
            ]);
            throw $e;
        }

    }


    /**
     * 获取积分名称
     * @return string
     */
    public static function getPointName(): string
    {
        return Setting::get('shop.lang.zh_cn.member_center.credit1') ?: "积分";
    }

    /**
     * 余额名称
     * @return string
     */
    public static function getBalanceName(): string
    {
        return Setting::get('shop.lang.zh_cn.member_center.credit') ?: '余额';
    }
}
